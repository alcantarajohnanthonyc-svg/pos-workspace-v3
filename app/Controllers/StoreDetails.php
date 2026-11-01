<?php

namespace App\Controllers;

use App\Libraries\ApprovalRequests;
use App\Libraries\Notifier;
use App\Libraries\Rbac;
use App\Models\Rbac_model;

/**
 * Store Details (table: store_details) with company / branch scope.
 *
 * RBAC module slug "store_details":  read, create, update, delete, import, export
 *
 *  - Not logged in               -> auth/login
 *  - Import: the CSV is read in the browser, the user can edit the list, every line is checked on the server
 *    (company, branch, access, duplicates), then only the valid lines are saved in batches with progress + a report
 *  - Super Admin (role_id == 1)  -> bypasses every permission AND scope check
 *  - Scope: if rbac_modules.has_company_filter / has_branch_filter is ON for this module, the user only
 *    sees stores of the branches assigned in usr_account_branches (and of their companies).
 *    No assigned branch -> zero results.
 *
 * Approval: if "Requires approval" is ticked for create / update / delete in Module Settings > Module Administrators,
 *           a normal user does not change anything directly: a request (with before / after) goes to the Approval Queue
 *           and is applied when the module administrator approves it. Super Admin and the module's administrators apply directly.
 *
 * NOTE: guards are private (storeDeny / storeAbilities) so they cannot clash with any denyUnless()/abilities()
 *       you may already have in BaseController. If you have them, swap the two calls in this file.
 */
class StoreDetails extends BaseController
{
    private const SLUG     = 'store_details';
    private const PER_PAGE = 25;
    private const IMPORT_MAX = 5000;   // lines per file (the browser reads the file)
    private const CHUNK_MAX  = 500;    // lines per check / commit request
    private const ACTIONS  = ['read', 'create', 'update', 'delete', 'import', 'export'];

    /** @var array<string,mixed>|null */
    private ?array $colCache = null;

    // ------------------------------------------------------------------ pages

    public function index()
    {
        if ($deny = $this->storeDeny('read', true)) {
            return $deny;
        }

        $db    = \Config\Database::connect();
        $scope = $this->scope($db);
        $f     = $this->filters();
        $page  = max(1, (int) $this->request->getGet('page'));

        $total = $this->query($db, $scope, $f)->countAllResults();
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        $rows = $this->query($db, $scope, $f)
            ->orderBy('s.id', 'DESC')
            ->limit(self::PER_PAGE, ($page - 1) * self::PER_PAGE)
            ->get()->getResultArray();

        // which stores on this page are waiting for approval (update / delete), and the pending "new store" requests
        $uidNow  = (int) (session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id'));
        $isAdmin = $this->isSuper() || ApprovalRequests::isModuleAdmin(self::SLUG, $uidNow);
        $pending = ApprovalRequests::pendingMap(self::SLUG, 'store_details', array_column($rows, 'id'));
        $pendingNew    = ApprovalRequests::pendingNew(self::SLUG, $isAdmin ? null : $uidNow);
        $pendingStores = (int) ($db->query(
            "SELECT COUNT(DISTINCT target_id) AS n FROM approval_requests WHERE module_slug = ? AND target_table = 'store_details' AND status = 'pending' AND target_id IS NOT NULL",
            [self::SLUG]
        )->getRowArray()['n'] ?? 0);

        // Summary cards follow the user's access scope AND the search / filters.
        // The status filter itself is left out of the per-status counts (the view hides the other statuses once one is selected).
        $fNoStatus  = $f;
        $fNoStatus['status'] = '';
        $statusRows = $this->query($db, $scope, $fNoStatus, "COALESCE(NULLIF(TRIM(s.store_status), ''), 'No status') AS status, COUNT(*) AS n", false)
            ->groupBy("COALESCE(NULLIF(TRIM(s.store_status), ''), 'No status')", false)
            ->orderBy('n', 'DESC')
            ->get()->getResultArray();
        $stats = ['n' => $total, 'by' => []];   // total = every active filter applied
        foreach ($statusRows as $sr) {
            $stats['by'][(string) $sr['status']] = (int) $sr['n'];
        }
        // stores inside the user's scope with no search / filter (only used for the "no assigned branch" notice)
        $scopeTotal = $this->query($db, $scope, [], 'COUNT(*) AS n', false)->get()->getRowArray();

        $statuses = array_column(
            $this->query($db, $scope, [], 'DISTINCT s.store_status AS v', false)->where('s.store_status IS NOT NULL')->where("s.store_status <> ''")->orderBy('v', 'ASC')->get()->getResultArray(),
            'v'
        );
        $ownerships = array_column(
            $db->table('store_details')->select('DISTINCT ownership_type AS v', false)->where('ownership_type IS NOT NULL')->where("ownership_type <> ''")->orderBy('v', 'ASC')->get()->getResultArray(),
            'v'
        );

        return view('store_details/index', $this->baseData('Store Details', 'Store Details', [
            ['label' => 'Home', 'url' => 'dashboard'],
            ['label' => 'Store Details'],
        ]) + [
            'rows'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'pages'      => $pages,
            'perPage'    => self::PER_PAGE,
            'filters'    => $f,
            'stats'      => $stats,
            'scopeTotal' => (int) ($scopeTotal['n'] ?? 0),
            'statuses'   => $statuses,
            'ownerships' => $ownerships,
            'branches'   => $this->branchOptions($db, $scope),
            'importMax'    => self::IMPORT_MAX,
            'importRef'    => $this->allowed('import') ? $this->importReference($db, $scope) : [],
            'scoped'     => empty($scope['all']),
            'scopeFlags' => ['company' => ! empty($scope['fc']), 'branch' => ! empty($scope['fb'])],
            'canQueue'   => $this->isSuper() || Rbac::can('approval_queue', 'read'),
            'pending'       => $pending,
            'pendingNew'    => $pendingNew,
            'pendingStores' => $pendingStores,
        ]);
    }

    // ---------------------------------------------------------------- actions

    public function create()
    {
        if ($deny = $this->storeDeny('create', false)) {
            return $deny;
        }

        $db    = \Config\Database::connect();
        $scope = $this->scope($db);
        [$data, $error] = $this->collect($db, $scope, 0);
        if ($error) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        if ($this->needsApproval('create')) {
            return $this->sendForApproval($db, $scope, 'create', null, $data);
        }

        $db->table('store_details')->insert($data);
        $this->notifyChange('create', (string) $data['outlet_description']);

        return redirect()->to(base_url('store-details'))->with('success', 'Store "' . $data['outlet_description'] . '" was added.');
    }

    public function update($id = null)
    {
        if ($deny = $this->storeDeny('update', false)) {
            return $deny;
        }

        $db    = \Config\Database::connect();
        $scope = $this->scope($db);
        $store = $this->findScoped($db, $scope, (int) $id);
        if (! $store) {
            return redirect()->back()->with('error', 'Store not found (or outside your company / branch scope).');
        }

        [$data, $error] = $this->collect($db, $scope, (int) $store['id']);
        if ($error) {
            return redirect()->back()->withInput()->with('error', $error);
        }

        if ($this->needsApproval('update')) {
            return $this->sendForApproval($db, $scope, 'update', $store, $data);
        }

        $db->table('store_details')->where('id', $store['id'])->update($data);
        $this->notifyChange('update', (string) $data['outlet_description']);

        return redirect()->back()->with('success', 'Store "' . $data['outlet_description'] . '" was updated.');
    }

    public function delete($id = null)
    {
        if ($deny = $this->storeDeny('delete', false)) {
            return $deny;
        }

        $db    = \Config\Database::connect();
        $scope = $this->scope($db);
        $store = $this->findScoped($db, $scope, (int) $id);
        if (! $store) {
            return redirect()->back()->with('error', 'Store not found (or outside your company / branch scope).');
        }

        if ($this->needsApproval('delete')) {
            return $this->sendForApproval($db, $scope, 'delete', $store, null);
        }

        $db->table('store_details')->where('id', $store['id'])->delete();
        $this->notifyChange('delete', (string) $store['outlet_description']);

        return redirect()->back()->with('success', 'Store "' . $store['outlet_description'] . '" was deleted.');
    }

    // ----------------------------------------------------------------- import (AJAX / JSON)
    // The browser reads the CSV and lets the user edit the list. Then:
    //   POST import/check  : checks the lines (company, branch, user access, fields, duplicates). Saves nothing.
    //   POST import/commit : checks again and saves the valid lines. The browser sends them in small batches,
    //                        so it can show progress, elapsed time and the time left.

    public function importCheck()
    {
        if ($deny = $this->ajaxDeny('import')) {
            return $deny;
        }
        $rows = $this->jsonRows();
        if ($rows === null) {
            return $this->jsonOut(['ok' => false, 'error' => 'Invalid data was sent.'], 400);
        }

        $db = \Config\Database::connect();
        [$results] = $this->validateImportRows($db, $this->scope($db), $rows);

        return $this->jsonOut(['ok' => true, 'results' => $results]);
    }

    public function importCommit()
    {
        if ($deny = $this->ajaxDeny('import')) {
            return $deny;
        }
        $rows = $this->jsonRows();
        if ($rows === null) {
            return $this->jsonOut(['ok' => false, 'error' => 'Invalid data was sent.'], 400);
        }

        $db = \Config\Database::connect();
        // checked again: the data or the user's access may have changed since the preview
        [$results, $insert] = $this->validateImportRows($db, $this->scope($db), $rows);

        if ($insert) {
            $db->transStart();
            foreach (array_chunk(array_values($insert), 200) as $chunk) {
                $db->table('store_details')->insertBatch($chunk);
            }
            $db->transComplete();

            $saved = $db->transStatus() !== false;
            foreach ($results as &$res) {
                if ($res['status'] === 'ready') {
                    $res['status'] = $saved ? 'imported' : 'skipped';
                    $res['reason'] = $saved ? '' : 'Database error: this batch was not saved.';
                }
            }
            unset($res);
        }

        return $this->jsonOut(['ok' => true, 'results' => $results]);
    }

    /** JSON guard for the import requests. */
    private function ajaxDeny(string $action)
    {
        if (! session()->get('isLoggedIn')) {
            return $this->jsonOut(['ok' => false, 'error' => 'Your session expired. Please log in again.'], 401);
        }
        if (! $this->allowed($action)) {
            return $this->jsonOut(['ok' => false, 'error' => 'You do not have permission to do this (Store Details: ' . $action . ').'], 403);
        }

        return null;
    }

    private function jsonOut(array $data, int $status = 200)
    {
        helper('security');

        return $this->response->setStatusCode($status)->setJSON($data + ['csrf' => csrf_hash()]);
    }

    /**
     * Body {"rows":[{line, company, branch, store_code, ...}]} -> [['line' => int, 'raw' => [...]]], or null if malformed.
     *
     * @return list<array{line:int,raw:array<string,string>}>|null
     */
    private function jsonRows(): ?array
    {
        $body = json_decode((string) $this->request->getBody(), true);
        if (! is_array($body) || ! isset($body['rows']) || ! is_array($body['rows'])) {
            return null;
        }
        $n = count($body['rows']);
        if ($n < 1 || $n > self::CHUNK_MAX) {
            return null;
        }

        $keys = ['company', 'branch', 'store_code', 'erp_code', 'outlet_description', 'address', 'ownership_type', 'store_status', 'date_opened', 'last_pos_selling_date'];
        $out  = [];
        foreach ($body['rows'] as $r) {
            if (! is_array($r) || ! isset($r['line']) || ! is_numeric($r['line'])) {
                return null;
            }
            $raw = [];
            foreach ($keys as $k) {
                $v       = $r[$k] ?? '';
                $raw[$k] = is_scalar($v) ? mb_substr(trim((string) $v), 0, 5100) : '';
            }
            $out[] = ['line' => (int) $r['line'], 'raw' => $raw];
        }

        return $out;
    }

    /**
     * Check every parsed line.
     *
     * @param list<array{line:int,raw:array<string,string>}> $parsed
     *
     * @return array{0:list<array<string,string|int>>,1:array<int,array>}  [results, insertRowsByLine]
     *   result = line, company, branch, store_code, outlet, status (ready|skipped), reason
     */
    private function validateImportRows($db, array $scope, array $parsed): array
    {
        $c = $this->cols($db);

        // every company / branch that exists (to tell "not found" apart from "not yours")
        $companies = [];
        if ($db->tableExists('com_company')) {
            $short = $this->pickCol($db, 'com_company', ['short_name'], null);
            $q     = $db->table('com_company')->select('id')->select($c['companyName'] . ' AS name');
            $short ? $q->select($short . ' AS short_name') : $q->select("'' AS short_name", false);
            $companies = $q->get()->getResultArray();
        }
        // Users type NAMES (not ids): company by name or short name, branch by name. Case / extra spaces do not matter.
        $norm     = static fn ($v) => strtolower(preg_replace('/\s+/', ' ', trim((string) $v)));
        $coById   = [];
        $coByName = [];
        foreach ($companies as $co) {
            $coById[(int) $co['id']] = $co['name'];
            $coByName[$norm($co['name'])] ??= (int) $co['id'];
            if (trim((string) $co['short_name']) !== '') {
                $coByName[$norm($co['short_name'])] ??= (int) $co['id'];
            }
        }

        $branchesAll = $this->branchOptions($db, ['all' => true]);   // id, name, company, company_id
        $brById = [];
        $brByName = [];
        foreach ($branchesAll as $br) {
            $brById[(int) $br['id']] = $br;
            $brByName[$norm($br['name'])][] = $br;
        }

        $allowedIds = array_flip(array_map('intval', array_column($this->branchOptions($db, $scope), 'id')));

        $seen = array_flip(array_map('strtolower', array_column(
            $db->table('store_details')->select('store_code')->where("store_code IS NOT NULL AND store_code <> ''")->get()->getResultArray(),
            'store_code'
        )));
        $firstLine = [];

        $pick = static function (array $raw, array $keys): string {
            foreach ($keys as $k) {
                if (isset($raw[$k]) && trim($raw[$k]) !== '') {
                    return trim($raw[$k]);
                }
            }

            return '';
        };

        $results = [];
        $insert  = [];

        foreach ($parsed as $p) {
            $line = (int) $p['line'];
            $raw  = $p['raw'];
            $coRef = $pick($raw, ['company', 'company_name']);
            $brRef = $pick($raw, ['branch', 'branch_name']);

            $res = [
                'line' => $line, 'company' => $coRef, 'branch' => $brRef,
                'store_code' => $pick($raw, ['store_code']), 'outlet' => $pick($raw, ['outlet_description']),
                'status' => 'skipped', 'reason' => '',
            ];
            $fail = static function (string $why) use (&$res) {
                $res['reason'] = $why;

                return $res;
            };

            // ---- company
            $companyId = 0;
            if ($coRef !== '') {
                $companyId = $coByName[$norm($coRef)] ?? 0;
                if ($companyId === 0) {
                    $results[] = $fail('Company not found: "' . $coRef . '".');

                    continue;
                }
                $res['company'] = $coById[$companyId];
            }

            // ---- branch
            if ($brRef === '') {
                $results[] = $fail('Branch is required.');

                continue;
            }
            $cands = $brByName[$norm($brRef)] ?? [];
            if (! $cands) {
                $results[] = $fail('Branch not found: "' . $brRef . '".');

                continue;
            }
            if ($companyId && $c['companyFk']) {
                $inCompany = array_values(array_filter($cands, static fn ($b) => (int) $b['company_id'] === $companyId));
                if (! $inCompany) {
                    $results[] = $fail('Branch "' . $brRef . '" does not belong to company "' . $res['company'] . '".');

                    continue;
                }
                $cands = $inCompany;
            }
            if (count($cands) > 1) {
                $results[] = $fail('Branch name "' . $brRef . '" matches several companies. Fill in the company column.');

                continue;
            }
            $branch = $cands[0];
            $res['branch'] = (string) $branch['name'];
            if (! $companyId && ! empty($branch['company_id'])) {
                $companyId     = (int) $branch['company_id'];
                $res['company'] = (string) ($branch['company'] ?? '');
            }

            // ---- the user's access
            if (empty($scope['all'])) {
                if (! empty($scope['fc']) && ! in_array($companyId, $scope['companyIds'], true)) {
                    $results[] = $fail('Company is outside your access.');

                    continue;
                }
                if (! isset($allowedIds[(int) $branch['id']])) {
                    $results[] = $fail('Branch is outside your access.');

                    continue;
                }
            }

            // ---- fields
            [$data, $error] = $this->normalize([
                'branch_id'             => (int) $branch['id'],
                'store_code'            => $pick($raw, ['store_code']),
                'erp_code'              => $pick($raw, ['erp_code']),
                'outlet_description'    => $pick($raw, ['outlet_description']),
                'address'               => $pick($raw, ['address']),
                'ownership_type'        => $pick($raw, ['ownership_type']),
                'store_status'          => $pick($raw, ['store_status']),
                'date_opened'           => $pick($raw, ['date_opened']),
                'last_pos_selling_date' => $pick($raw, ['last_pos_selling_date']),
            ]);
            if ($error) {
                $results[] = $fail($error);

                continue;
            }

            // ---- duplicates (database, then earlier lines of this file)
            if ($data['store_code'] !== null) {
                $k = strtolower($data['store_code']);
                if (isset($seen[$k])) {
                    $results[] = $fail(isset($firstLine[$k]) ? 'Store code "' . $data['store_code'] . '" is repeated (first on line ' . $firstLine[$k] . ').' : 'Store code "' . $data['store_code'] . '" already exists.');

                    continue;
                }
                $seen[$k]      = true;
                $firstLine[$k] = $line;
            }

            $res['status'] = 'ready';
            $results[]     = $res;
            $insert[$line] = $data;
        }

        return [$results, $insert];
    }

    /** GET store-details/export  (add ?template=1 for an empty import template) */
    public function export()
    {
        $template = (bool) $this->request->getGet('template');
        if ($deny = $this->storeDeny($template ? 'import' : 'export', false)) {
            return $deny;
        }

        $head = ['company', 'branch', 'store_code', 'erp_code', 'outlet_description', 'address', 'ownership_type', 'store_status', 'date_opened', 'last_pos_selling_date'];
        $out  = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $head);

        if (! $template) {
            $db    = \Config\Database::connect();
            $scope = $this->scope($db);
            $rows  = $this->query($db, $scope, $this->filters())->orderBy('s.id', 'ASC')->get()->getResultArray();
            foreach ($rows as $r) {
                fputcsv($out, array_map([$this, 'csvSafe'], [
                    $r['company_name'] ?? '', $r['branch_name'] ?? '', $r['store_code'], $r['erp_code'], $r['outlet_description'],
                    $r['address'], $r['ownership_type'], $r['store_status'], $r['date_opened'], $r['last_pos_selling_date'],
                ]));
            }
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . ($template ? 'store_details_template' : 'store_details_' . date('Ymd_His')) . '.csv"')
            ->setBody($csv);
    }

    // ---------------------------------------------------------------- notifications

    /** "Record Changed": tell the module administrators when someone else changed a store directly. */
    private function notifyChange(string $action, string $name): void
    {
        $uid   = (int) (session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id'));
        $admin = array_values(array_diff(ApprovalRequests::adminIds(\Config\Database::connect(), self::SLUG), [$uid]));
        if (! $admin) {
            return;
        }
        $verb = ['create' => 'added', 'update' => 'updated', 'delete' => 'deleted'][$action] ?? 'changed';
        Notifier::send(self::SLUG, 'record_changed', $admin, 'Store ' . $verb, Notifier::nameOf($uid) . ' ' . $verb . ' “' . $name . '”.', 'store-details');
    }

    // ---------------------------------------------------------------- approval

    /** Editable columns that go into a request (before / after) and are applied on approval. */
    private const REQ_FIELDS = ['branch_id', 'store_code', 'erp_code', 'outlet_description', 'address', 'ownership_type', 'store_status', 'date_opened', 'last_pos_selling_date'];

    /**
     * Must this action wait for an administrator?  Super Admin and the module's own administrators apply directly.
     * Everybody else: only when "Requires approval" is ticked for the action in Module Settings.
     */
    private function needsApproval(string $action): bool
    {
        if ($this->isSuper()) {
            return false;
        }
        $uid = (int) (session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id'));
        if (ApprovalRequests::isModuleAdmin(self::SLUG, $uid)) {
            return false;
        }

        return ApprovalRequests::requiresApproval(self::SLUG, $action);
    }

    /**
     * Save the change as a request in the Approval Queue instead of applying it.
     *
     * @param array<string,mixed>|null $store current row (update / delete)
     * @param array<string,mixed>|null $data  new values (create / update)
     */
    private function sendForApproval($db, array $scope, string $action, ?array $store, ?array $data)
    {
        $uid    = (int) (session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id'));
        $fields = array_flip(self::REQ_FIELDS);
        $before = $store ? array_intersect_key($store, $fields) : null;
        $after  = $data ? array_intersect_key($data, $fields) : null;
        $name   = (string) ($data['outlet_description'] ?? $store['outlet_description'] ?? '');

        if ($action === 'update') {
            $same = true;
            foreach (self::REQ_FIELDS as $k) {
                if ((string) ($before[$k] ?? '') !== (string) ($after[$k] ?? '')) {
                    $same = false;
                    break;
                }
            }
            if ($same) {
                return redirect()->back()->with('error', 'Nothing was changed, so no request was sent.');
            }
        }
        if ($action !== 'create' && ($dup = ApprovalRequests::pendingFor(self::SLUG, 'store_details', (int) $store['id'], $action))) {
            return redirect()->back()->with('error', 'This store already has a ' . $action . ' request waiting for approval (' . $dup . ').');
        }

        // readable branch names for the before / after view (branch_id itself is hidden there)
        $options = [];
        foreach ($this->branchOptions($db, $scope) as $o) {
            $options[(int) $o['id']] = $o;
        }
        $branchText = static function ($bid) use ($options): string {
            $bid = (int) $bid;
            if (! $bid) {
                return '';
            }
            $o = $options[$bid] ?? null;

            return $o ? ($o['name'] . (($o['company'] ?? '') !== '' ? ' (' . $o['company'] . ')' : '')) : 'Branch #' . $bid;
        };
        if ($before) {
            $before['branch'] = $branchText($before['branch_id'] ?? 0);
        }
        if ($after) {
            $after['branch'] = $branchText($after['branch_id'] ?? 0);
        }

        $branchId = (int) ($after['branch_id'] ?? $before['branch_id'] ?? 0);
        $opt      = $options[$branchId] ?? null;
        $scopeTxt = $opt ? trim(($opt['company'] ?? '') . ' - ' . $opt['name'], ' -') : null;

        $labels = [
            'branch_id' => false, 'branch' => 'Branch', 'store_code' => 'Store Code', 'erp_code' => 'ERP Code',
            'outlet_description' => 'Outlet', 'address' => 'Address', 'ownership_type' => 'Ownership',
            'store_status' => 'Status', 'date_opened' => 'Date Opened', 'last_pos_selling_date' => 'Last POS Sale',
        ];
        $actionLabel = ['create' => 'Create New Store', 'update' => 'Update Store Information', 'delete' => 'Delete Store'][$action] ?? ucfirst($action);
        $verb        = ['create' => 'Add the new store', 'update' => 'Update the store', 'delete' => 'Delete the store'][$action] ?? ucfirst($action);

        $id = ApprovalRequests::submit([
            'module_slug'  => self::SLUG,
            'action_key'   => $action,
            'action_label' => $actionLabel,
            'requester_id' => $uid,
            'target_table' => 'store_details',
            'target_id'    => $store['id'] ?? null,
            'target_label' => $name,
            'summary'      => $verb . ' "' . $name . '"' . (! empty($store['store_code']) ? ' (code ' . $store['store_code'] . ')' : '') . '.',
            'scope_text'   => $scopeTxt,
            'branch_id'    => $branchId ?: null,
            'priority'     => $action === 'delete' ? 'high' : 'medium',
            'before'       => $before,
            'after'        => $after,
            'field_labels' => $labels,
        ]);

        if (! $id) {
            return redirect()->back()->withInput()->with('error', 'The request could not be sent for approval. Please try again or contact the administrator.');
        }

        // shown as a popup (modal-approval-sent in the view), not as the normal green bar
        return redirect()->back()->with('approval_sent', [
            'ref'      => ApprovalRequests::reference($id),
            'name'     => $name,
            'action'   => $action,
            'approver' => ApprovalRequests::currentApproverName($id),
        ]);
    }

    // ---------------------------------------------------------------- helpers

    private function baseData(string $title, string $pageTitle, array $breadcrumbs): array
    {
        $userId = session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id');
        $roleId = session()->get('user_role_id') ?? session()->get('role_id');

        return [
            'title'           => $title,
            'pageTitle'       => $pageTitle,
            'filteredModules' => (new Rbac_model())->get_user_modules($userId, $roleId),
            'can'             => $this->storeAbilities(),
            'breadcrumbs'     => $breadcrumbs,
        ];
    }

    private function isSuper(): bool
    {
        return (int) (session()->get('user_role_id') ?? session()->get('role_id')) === 1;
    }

    private function allowed(string $action): bool
    {
        return $this->isSuper() || Rbac::can(self::SLUG, $action);
    }

    /** @return array<string,bool> */
    private function storeAbilities(): array
    {
        $out = [];
        foreach (self::ACTIONS as $a) {
            $out[$a] = $this->allowed($a);
        }

        return $out;
    }

    /** Login + permission guard. $page = true for GET pages, false for POST actions. */
    private function storeDeny(string $action, bool $page)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        if (! $this->allowed($action)) {
            return $page
                ? redirect()->to(base_url('dashboard'))->with('error', 'You do not have permission to open Store Details.')
                : redirect()->back()->with('error', 'You do not have permission to do this (Store Details: ' . $action . ').');
        }

        return null;
    }

    /** First existing column of $table from $candidates (identifier-safe), or $fallback. */
    private function pickCol($db, string $table, array $candidates, ?string $fallback): ?string
    {
        if (! $db->tableExists($table)) {
            return $fallback;
        }
        $fields = $db->getFieldNames($table);
        foreach ($candidates as $c) {
            if (in_array($c, $fields, true)) {
                return $c;
            }
        }

        return $fallback;
    }

    /** Detected column names of com_branch / com_company. */
    private function cols($db): array
    {
        if ($this->colCache === null) {
            $companyFk = $this->pickCol($db, 'com_branch', ['company_id'], null);
            $this->colCache = [
                'branchName'  => $this->pickCol($db, 'com_branch', ['branch_name', 'name', 'branch'], 'id'),
                'companyName' => $this->pickCol($db, 'com_company', ['company_name', 'name', 'company'], 'id'),
                'companyFk'   => $companyFk,
                'companyExpr' => $companyFk ? 'b.' . $companyFk : 'NULL',
            ];
        }

        return $this->colCache;
    }

    /**
     * Company / branch scope of the logged-in user for this module.
     * ['all' => true]                                  no restriction (Super Admin or no filter enabled)
     * ['all' => false, 'fb', 'fc', 'branchIds', 'companyIds']
     */
    private function scope($db): array
    {
        if ($this->isSuper()) {
            return ['all' => true];
        }

        $mod = $db->table('rbac_modules')->where('slug', self::SLUG)->get()->getRowArray() ?: [];
        $fc  = ! empty($mod['has_company_filter']);
        $fb  = ! empty($mod['has_branch_filter']);
        if (! $fc && ! $fb) {
            return ['all' => true];
        }

        $userId = (int) (session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id'));
        $q      = $db->table('usr_account_branches')->where('account_id', $userId);
        $fields = $db->tableExists('usr_account_branches') ? $db->getFieldNames('usr_account_branches') : [];
        if (! empty($mod['id']) && in_array('module_id', $fields, true)) {
            // per-module assignment: this module, or "all modules" (0 / NULL)
            $q->groupStart()->where('module_id', (int) $mod['id'])->orWhere('module_id', 0)->orWhere('module_id IS NULL')->groupEnd();
        }
        $assigned = $db->tableExists('usr_account_branches') ? $q->get()->getResultArray() : [];

        $branchIds = array_values(array_unique(array_filter(array_map('intval', array_column($assigned, 'branch_id')))));

        if (in_array('company_id', $fields, true)) {
            $companyIds = array_values(array_unique(array_filter(array_map('intval', array_column($assigned, 'company_id')))));
        } elseif ($branchIds && ($fk = $this->cols($db)['companyFk'])) {
            $companyIds = array_values(array_unique(array_filter(array_map('intval', array_column(
                $db->table('com_branch')->select($fk)->whereIn('id', $branchIds)->get()->getResultArray(),
                $fk
            )))));
        } else {
            $companyIds = [];
        }

        return ['all' => false, 'fb' => $fb, 'fc' => $fc, 'branchIds' => $branchIds, 'companyIds' => $companyIds];
    }

    /** Apply the scope to a builder whose branch alias is "b" and branch id column is $branchCol. */
    private function applyScope($builder, array $scope, string $branchCol, string $companyCol)
    {
        if (! empty($scope['all'])) {
            return $builder;
        }
        if (! empty($scope['fb'])) {
            $scope['branchIds'] ? $builder->whereIn($branchCol, $scope['branchIds']) : $builder->where('1 = 0', null, false);
        }
        if (! empty($scope['fc'])) {
            ($scope['companyIds'] && $companyCol !== 'NULL') ? $builder->whereIn($companyCol, $scope['companyIds']) : $builder->where('1 = 0', null, false);
        }

        return $builder;
    }

    /** Base query: store_details + branch + company, scoped, optionally filtered. */
    private function query($db, array $scope, array $f = [], ?string $select = null, bool $withNames = true)
    {
        $c = $this->cols($db);
        $b = $db->table('store_details s')->join('com_branch b', 'b.id = s.branch_id', 'left');

        if ($c['companyFk']) {
            $b->join('com_company c', 'c.id = b.' . $c['companyFk'], 'left');
        }

        if ($select !== null) {
            $b->select($select, false);
        } else {
            $b->select('s.*')->select('b.' . $c['branchName'] . ' AS branch_name');
            if ($c['companyFk']) {
                $b->select('b.' . $c['companyFk'] . ' AS company_id')->select('c.' . $c['companyName'] . ' AS company_name');
            } else {
                $b->select('NULL AS company_id, NULL AS company_name', false);
            }
        }

        $this->applyScope($b, $scope, 's.branch_id', $c['companyExpr']);

        if (! empty($f['q'])) {
            $b->groupStart()
                ->like('s.store_code', $f['q'])->orLike('s.erp_code', $f['q'])
                ->orLike('s.outlet_description', $f['q'])->orLike('s.address', $f['q'])
                ->orLike('b.' . $c['branchName'], $f['q'])
                ->groupEnd();
        }
        if (! empty($f['company']) && $c['companyFk']) {
            $b->where('b.' . $c['companyFk'], (int) $f['company']);
        }
        if (! empty($f['branch'])) {
            $b->where('s.branch_id', (int) $f['branch']);
        }
        if (! empty($f['status'])) {
            $b->where('s.store_status', $f['status']);
        }
        if (! empty($f['pending'])) {
            // only stores that have an update / delete request waiting for approval
            $b->where("s.id IN (SELECT target_id FROM approval_requests WHERE module_slug = " . $db->escape(self::SLUG) . " AND target_table = 'store_details' AND status = 'pending' AND target_id IS NOT NULL)", null, false);
        }

        return $b;
    }

    private function filters(): array
    {
        return [
            'q'       => mb_substr(trim((string) $this->request->getGet('q')), 0, 100),
            'company' => (int) $this->request->getGet('company'),
            'branch'  => (int) $this->request->getGet('branch'),
            'status'  => mb_substr(trim((string) $this->request->getGet('status')), 0, 50),
            'pending' => (int) ((string) $this->request->getGet('pending') === '1'),
        ];
    }

    private function findScoped($db, array $scope, int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        return $this->query($db, $scope)->where('s.id', $id)->get()->getRowArray() ?: null;
    }

    /** Branches the user may pick / import into. @return list<array{id:int,name:string,company:?string}> */
    private function branchOptions($db, array $scope): array
    {
        $c = $this->cols($db);
        $b = $db->table('com_branch b')->select('b.id')->select('b.' . $c['branchName'] . ' AS name');
        if ($c['companyFk']) {
            $b->join('com_company c', 'c.id = b.' . $c['companyFk'], 'left')->select('c.' . $c['companyName'] . ' AS company')->select('b.' . $c['companyFk'] . ' AS company_id');
        } else {
            $b->select('NULL AS company, NULL AS company_id', false);
        }
        $this->applyScope($b, $scope, 'b.id', $c['companyExpr']);

        return $b->orderBy('b.' . $c['branchName'], 'ASC')->get()->getResultArray();
    }

    /** Companies -> branches the user may import into (names), shown in the import dialog. */
    private function importReference($db, array $scope): array
    {
        $out = [];
        foreach ($this->branchOptions($db, $scope) as $b) {
            $out[(string) ($b['company'] ?? 'No company')][] = (string) $b['name'];
        }
        ksort($out);

        return $out;
    }

    /** Create / edit form -> [data, error]. */
    private function collect($db, array $scope, int $ignoreId): array
    {
        $p = $this->request->getPost();
        [$data, $error] = $this->normalize([
            'branch_id'             => (int) ($p['branch_id'] ?? 0),
            'store_code'            => $p['store_code'] ?? '',
            'erp_code'              => $p['erp_code'] ?? '',
            'outlet_description'    => $p['outlet_description'] ?? '',
            'address'               => $p['address'] ?? '',
            'ownership_type'        => $p['ownership_type'] ?? '',
            'store_status'          => $p['store_status'] ?? '',
            'date_opened'           => $p['date_opened'] ?? '',
            'last_pos_selling_date' => $p['last_pos_selling_date'] ?? '',
        ]);
        if ($error) {
            return [null, $error];
        }

        $okBranches = array_map('intval', array_column($this->branchOptions($db, $scope), 'id'));
        if (! in_array((int) $data['branch_id'], $okBranches, true)) {
            return [null, 'Please choose a branch within your company / branch scope.'];
        }

        if ($data['store_code'] !== null) {
            $dup = $db->table('store_details')->where('store_code', $data['store_code'])->where('id !=', $ignoreId)->countAllResults();
            if ($dup > 0) {
                return [null, 'Store code "' . $data['store_code'] . '" already exists.'];
            }
        }

        return [$data, null];
    }

    /** Trim, length-check and date-check one store row. Empty optional values become NULL. */
    private function normalize(array $in): array
    {
        $t = static fn ($v) => trim((string) $v);

        $data = [
            'branch_id'             => (int) $in['branch_id'],
            'store_code'            => $t($in['store_code']),
            'erp_code'              => $t($in['erp_code']),
            'outlet_description'    => $t($in['outlet_description']),
            'address'               => $t($in['address']),
            'ownership_type'        => $t($in['ownership_type']),
            'store_status'          => $t($in['store_status']),
            'date_opened'           => $t($in['date_opened']),
            'last_pos_selling_date' => $t($in['last_pos_selling_date']),
        ];

        if ($data['outlet_description'] === '') {
            return [null, 'Outlet description is required.'];
        }
        $max = ['store_code' => 50, 'erp_code' => 50, 'outlet_description' => 255, 'ownership_type' => 100, 'store_status' => 50, 'address' => 5000];
        foreach ($max as $k => $len) {
            if (mb_strlen($data[$k]) > $len) {
                return [null, str_replace('_', ' ', $k) . ' is too long (max ' . $len . ').'];
            }
        }
        foreach (['date_opened', 'last_pos_selling_date'] as $k) {
            if ($data[$k] !== '') {
                $d = \DateTime::createFromFormat('Y-m-d', $data[$k]);
                if (! $d || $d->format('Y-m-d') !== $data[$k]) {
                    return [null, str_replace('_', ' ', $k) . ' must be a date like 2026-10-27.'];
                }
            }
        }

        foreach (['store_code', 'erp_code', 'address', 'ownership_type', 'store_status', 'date_opened', 'last_pos_selling_date'] as $k) {
            if ($data[$k] === '') {
                $data[$k] = null;
            }
        }

        return [$data, null];
    }

    /** Stop spreadsheet formula injection in exported cells. */
    private function csvSafe($v): string
    {
        $v = (string) $v;

        return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
    }
}