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
    private const SUBMIT_MAX = 5000;   // lines of one import sent for approval
    private const BULK_MAX   = 200;    // stores of one bulk update / delete
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

        // rows per page (the selector under the table)
        $perPage = (int) $this->request->getGet('per_page');
        $perPage = in_array($perPage, [5, 10, 25, 50, 100], true) ? $perPage : self::PER_PAGE;

        $total = $this->query($db, $scope, $f)->countAllResults();
        $pages = max(1, (int) ceil($total / $perPage));
        $page  = min($page, $pages);

        $rows = $this->query($db, $scope, $f)
            ->orderBy('s.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
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
            'perPage'    => $perPage,
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
            'importNeedsApproval' => $this->allowed('import') && $this->needsApproval('import'),
            'exportNeedsApproval' => $this->allowed('export') && $this->needsApproval('export'),
            'bulkUpdateNeedsApproval' => $this->allowed('update') && $this->needsApproval('update'),
            'bulkDeleteNeedsApproval' => $this->allowed('delete') && $this->needsApproval('delete'),
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

    /**
     * POST import/submit: "Requires approval" is ON for Import, so the lines are NOT saved. They are checked and sent
     * as ONE request (all valid lines) to the Approval Queue; they are inserted when an administrator approves it.
     */
    public function importSubmit()
    {
        if ($deny = $this->ajaxDeny('import')) {
            return $deny;
        }
        if (! $this->needsApproval('import')) {
            return $this->jsonOut(['ok' => false, 'error' => 'This import does not need approval. Import the lines normally.'], 409);
        }
        $rows = $this->jsonRows(self::SUBMIT_MAX);
        if ($rows === null) {
            return $this->jsonOut(['ok' => false, 'error' => 'Invalid data was sent.'], 400);
        }
        $body = json_decode((string) $this->request->getBody(), true);
        $file = is_array($body) ? mb_substr(trim((string) ($body['file'] ?? '')), 0, 150) : '';
        $file = $file !== '' ? $file : 'stores.csv';

        $db    = \Config\Database::connect();
        $scope = $this->scope($db);
        [$results, $insert] = $this->validateImportRows($db, $scope, $rows);
        if (! $insert) {
            return $this->jsonOut(['ok' => false, 'error' => 'None of the lines is valid, so nothing was sent for approval.'], 422);
        }

        $options = [];
        foreach ($this->branchOptions($db, $scope) as $o) {
            $options[(int) $o['id']] = $o;
        }
        $branchNames = [];
        $sample      = [];
        foreach ($insert as $d) {
            $o = $options[(int) ($d['branch_id'] ?? 0)] ?? null;
            if ($o) {
                $branchNames[$o['name']] = true;
            }
            if (count($sample) < 5) {
                $sample[] = (string) ($d['outlet_description'] ?? '');
            }
        }
        $rowsOut = [];
        foreach ($insert as $d) {
            $o           = $options[(int) ($d['branch_id'] ?? 0)] ?? null;
            $d['branch'] = $o ? $o['name'] . (($o['company'] ?? '') !== '' ? ' (' . $o['company'] . ')' : '') : '';
            $rowsOut[]   = $d;
        }
        $branchList = array_keys($branchNames);
        $shown      = implode(', ', array_slice($branchList, 0, 4)) . (count($branchList) > 4 ? ' +' . (count($branchList) - 4) . ' more' : '');

        $uid = (int) (session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id'));
        $id  = ApprovalRequests::submit([
            'module_slug'  => self::SLUG,
            'action_key'   => 'import',
            'action_label' => 'Import Stores',
            'requester_id' => $uid,
            'target_table' => 'store_details',
            'target_label' => $file,
            'summary'      => 'Import ' . count($insert) . ' store(s) from "' . $file . '".',
            'scope_text'   => $shown !== '' ? $shown : null,
            'priority'     => 'medium',
            'before'       => null,
            'after'        => [
                'file'     => $file,
                'lines'    => count($insert),
                'skipped'  => count($rows) - count($insert),
                'branches' => $shown,
                'sample'   => implode('; ', $sample) . (count($insert) > 5 ? ' …' : ''),
                'rows'     => $rowsOut,
            ],
            'field_labels' => ['file' => 'File', 'lines' => 'Lines to import', 'skipped' => 'Lines with errors (not sent)', 'branches' => 'Branches', 'sample' => 'First lines', 'rows' => false],
        ]);
        if (! $id) {
            return $this->jsonOut(['ok' => false, 'error' => 'The request could not be sent for approval. Please try again.'], 500);
        }

        foreach ($results as &$res) {
            if ($res['status'] === 'ready') {
                $res['status'] = 'queued';
            }
        }
        unset($res);

        return $this->jsonOut([
            'ok'       => true,
            'ref'      => ApprovalRequests::reference($id),
            'approver' => ApprovalRequests::currentApproverName($id),
            'results'  => $results,
        ]);
    }

    // ---------------------------------------------------------------- bulk (checkboxes in the list)

    /** POST store-details/bulk/update  {ids:[...], changes:{store_status, ownership_type}} */
    public function bulkUpdate()
    {
        return $this->bulk('update');
    }

    /** POST store-details/bulk/delete  {ids:[...]} */
    public function bulkDelete()
    {
        return $this->bulk('delete');
    }

    /**
     * Change or delete many selected stores at once.
     * "Requires approval" ON for this action -> ONE request with every store (the approver sees all of them);
     * Super Admin / module administrators, or approval OFF -> applied now.
     */
    private function bulk(string $action)
    {
        if ($deny = $this->ajaxDeny($action)) {
            return $deny;
        }
        $body = json_decode((string) $this->request->getBody(), true);
        $body = is_array($body) ? $body : [];
        $ids  = array_values(array_unique(array_filter(array_map('intval', (array) ($body['ids'] ?? [])))));
        if (! $ids) {
            return $this->jsonOut(['ok' => false, 'error' => 'Select at least one store.'], 422);
        }
        if (count($ids) > self::BULK_MAX) {
            return $this->jsonOut(['ok' => false, 'error' => 'You can change up to ' . self::BULK_MAX . ' stores at a time.'], 422);
        }

        $changes = [];
        if ($action === 'update') {
            foreach (['store_status' => 50, 'ownership_type' => 100] as $k => $len) {
                $v = trim(mb_substr((string) (($body['changes'] ?? [])[$k] ?? ''), 0, $len));
                if ($v !== '') {
                    $changes[$k] = $v;
                }
            }
            if (! $changes) {
                return $this->jsonOut(['ok' => false, 'error' => 'Choose what to change: the status or the ownership.'], 422);
            }
        }

        $db     = \Config\Database::connect();
        $scope  = $this->scope($db);
        $stores = $this->query($db, $scope, [])->whereIn('s.id', $ids)->get()->getResultArray();   // only stores inside the user's access
        if (! $stores) {
            return $this->jsonOut(['ok' => false, 'error' => 'The selected stores were not found.'], 404);
        }

        $pend    = ApprovalRequests::pendingMap(self::SLUG, 'store_details', array_column($stores, 'id'));
        $options = [];
        foreach ($this->branchOptions($db, $scope) as $o) {
            $options[(int) $o['id']] = $o;
        }

        $items       = [];
        $skipPending = 0;
        $skipSame    = 0;
        foreach ($stores as $s) {
            $sid = (int) $s['id'];
            if (! empty($pend[$sid]['delete']) || ! empty($pend[$sid][$action])) {
                $skipPending++;

                continue;
            }
            $o    = $options[(int) $s['branch_id']] ?? null;
            $item = [
                'id'         => $sid,
                'store_code' => (string) ($s['store_code'] ?? ''),
                'outlet'     => (string) $s['outlet_description'],
                'branch'     => $o ? $o['name'] . (($o['company'] ?? '') !== '' ? ' (' . $o['company'] . ')' : '') : '',
                'status'     => (string) ($s['store_status'] ?? ''),
            ];
            if ($action === 'update') {
                $before = [];
                $after  = [];
                foreach ($changes as $k => $v) {
                    if ((string) ($s[$k] ?? '') !== $v) {
                        $before[$k] = (string) ($s[$k] ?? '');
                        $after[$k]  = $v;
                    }
                }
                if (! $after) {
                    $skipSame++;

                    continue;
                }
                $item['before'] = $before;
                $item['after']  = $after;
            }
            $items[] = $item;
        }

        if (! $items) {
            $why = $skipPending > 0 ? 'The selected stores already have a request waiting for approval.' : 'The selected stores already have these values.';

            return $this->jsonOut(['ok' => false, 'error' => $why], 422);
        }
        $n       = count($items);
        $skipped = $skipPending + $skipSame;

        $label = ['store_status' => 'Status', 'ownership_type' => 'Ownership'];
        $text  = [];
        foreach ($changes as $k => $v) {
            $text[] = $label[$k] . ' → ' . $v;
        }
        $changeText = $action === 'update' ? implode('; ', $text) : 'Delete the selected stores';

        if ($this->needsApproval($action)) {
            $uid      = (int) (session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id'));
            $branches = array_values(array_unique(array_column($items, 'branch')));
            $shown    = implode(', ', array_slice($branches, 0, 3)) . (count($branches) > 3 ? ' +' . (count($branches) - 3) . ' more' : '');
            $word     = $n . ' store' . ($n === 1 ? '' : 's');

            $id = ApprovalRequests::submit([
                'module_slug'  => self::SLUG,
                'action_key'   => $action,
                'action_label' => ($action === 'update' ? 'Update ' : 'Delete ') . $word,
                'requester_id' => $uid,
                'target_table' => 'store_details',
                'target_id'    => null,
                'target_label' => $word,
                'summary'      => ($action === 'update' ? 'Change ' . implode(', ', $text) . ' for ' : 'Delete ') . $word . '.',
                'scope_text'   => $shown !== '' ? $shown : null,
                'priority'     => $action === 'delete' ? 'high' : 'medium',
                'before'       => null,
                'after'        => ['mode' => 'bulk', 'count' => $n, 'change_text' => $changeText, 'changes' => $changes, 'items' => $items],
                'field_labels' => ['mode' => false, 'items' => false, 'changes' => false, 'count' => 'Stores', 'change_text' => 'Change'],
            ]);
            if (! $id) {
                return $this->jsonOut(['ok' => false, 'error' => 'The request could not be sent for approval. Please try again.'], 500);
            }

            return $this->jsonOut([
                'ok'       => true,
                'queued'   => true,
                'count'    => $n,
                'skipped'  => $skipped,
                'ref'      => ApprovalRequests::reference($id),
                'approver' => ApprovalRequests::currentApproverName($id),
                'name'     => $word,
                'action'   => $action,
            ]);
        }

        // applied now
        $applyIds = array_column($items, 'id');
        $db->transStart();
        if ($action === 'update') {
            $db->table('store_details')->whereIn('id', $applyIds)->update($changes);
        } else {
            $db->table('store_details')->whereIn('id', $applyIds)->delete();
        }
        $db->transComplete();
        if ($db->transStatus() === false) {
            return $this->jsonOut(['ok' => false, 'error' => 'The change could not be saved. Please try again.'], 500);
        }
        $this->notifyChange($action, $n . ' store' . ($n === 1 ? '' : 's'));

        return $this->jsonOut(['ok' => true, 'queued' => false, 'count' => $n, 'skipped' => $skipped]);
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
    private function jsonRows(?int $max = null): ?array
    {
        $body = json_decode((string) $this->request->getBody(), true);
        if (! is_array($body) || ! isset($body['rows']) || ! is_array($body['rows'])) {
            return null;
        }
        $n = count($body['rows']);
        if ($n < 1 || $n > ($max ?? self::CHUNK_MAX)) {
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

        // "Requires approval" for Export: no direct download. The request goes to the Approval Queue; once approved, the
        // requester gets a notification with a one-time link (?request=ID, valid 24 hours) that downloads the same filter.
        $filters   = $this->filters();
        $requestId = (int) $this->request->getGet('request');
        if (! $template) {
            $dbx = \Config\Database::connect();
            if ($requestId > 0) {
                [$approved, $why] = $this->approvedExportFilters($dbx, $requestId);
                if ($approved === null) {
                    return redirect()->to(base_url('store-details'))->with('error', $why);
                }
                $filters = $approved;
            } elseif ($this->needsApproval('export')) {
                return $this->sendExportForApproval($dbx, $this->scope($dbx), $filters);
            }
        }

        $head = ['company', 'branch', 'store_code', 'erp_code', 'outlet_description', 'address', 'ownership_type', 'store_status', 'date_opened', 'last_pos_selling_date'];
        $out  = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $head);

        if (! $template) {
            $db    = \Config\Database::connect();
            $scope = $this->scope($db);
            $rows  = $this->query($db, $scope, $filters)->orderBy('s.id', 'ASC')->get()->getResultArray();
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

    // ---------------------------------------------------------------- export approval

    /**
     * An approved export request -> the filters to export with. One download, within 24 hours, by the requester.
     *
     * @return array{0:?array<string,mixed>, 1:string}  [filters|null, message]
     */
    private function approvedExportFilters($db, int $requestId): array
    {
        $uid = (int) (session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id'));
        $r   = $db->table('approval_requests')->where('id', $requestId)->where('module_slug', self::SLUG)->where('action_key', 'export')->get()->getRowArray();
        if (! $r || ((int) $r['requester_id'] !== $uid && ! $this->isSuper())) {
            return [null, 'That export request was not found.'];
        }
        if ($r['status'] === 'pending') {
            return [null, 'This export is still waiting for approval (' . $r['reference_no'] . ').'];
        }
        if ($r['status'] !== 'approved') {
            return [null, 'This export request was ' . $r['status'] . ' (' . $r['reference_no'] . ').'];
        }
        $when = strtotime((string) $r['decided_at']);
        if (! $when || time() - $when > 86400) {
            return [null, 'The approval of this export expired (24 hours). Please send a new export request.'];
        }
        if ($db->table('approval_request_logs')->where('request_id', $requestId)->where('event', 'downloaded')->countAllResults() > 0) {
            return [null, 'This export was already downloaded. Send a new export request if you need it again.'];
        }

        $after = json_decode((string) $r['payload_after'], true);
        $f     = is_array($after) && is_array($after['filters'] ?? null) ? $after['filters'] : [];

        $db->table('approval_request_logs')->insert([
            'request_id' => $requestId,
            'actor_id'   => $uid,
            'event'      => 'downloaded',
            'note'       => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return [[
            'q'       => mb_substr((string) ($f['q'] ?? ''), 0, 100),
            'company' => (int) ($f['company'] ?? 0),
            'branch'  => (int) ($f['branch'] ?? 0),
            'status'  => mb_substr((string) ($f['status'] ?? ''), 0, 50),
            'pending' => (int) ($f['pending'] ?? 0),
        ], ''];
    }

    /** Export needs approval: send the request (with the filters and how many stores) instead of downloading. */
    private function sendExportForApproval($db, array $scope, array $filters)
    {
        $uid = (int) (session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id'));
        $dup = $db->table('approval_requests')->select('reference_no')
            ->where('module_slug', self::SLUG)->where('action_key', 'export')->where('requester_id', $uid)->where('status', 'pending')
            ->get()->getRowArray();
        if ($dup) {
            return redirect()->back()->with('error', 'You already have an export waiting for approval (' . $dup['reference_no'] . ').');
        }

        $count = (int) $this->query($db, $scope, $filters)->countAllResults();
        if ($count < 1) {
            return redirect()->back()->with('error', 'There are no stores to export with these filters.');
        }

        $options = $this->branchOptions($db, $scope);
        $parts   = [];
        if ($filters['q'] !== '') {
            $parts[] = 'Search: "' . $filters['q'] . '"';
        }
        if ($filters['company'] > 0) {
            foreach ($options as $o) {
                if ((int) $o['company_id'] === (int) $filters['company']) {
                    $parts[] = 'Company: ' . $o['company'];
                    break;
                }
            }
        }
        if ($filters['branch'] > 0) {
            foreach ($options as $o) {
                if ((int) $o['id'] === (int) $filters['branch']) {
                    $parts[] = 'Branch: ' . $o['name'];
                    break;
                }
            }
        }
        if ($filters['status'] !== '') {
            $parts[] = 'Status: ' . $filters['status'];
        }
        if (! empty($filters['pending'])) {
            $parts[] = 'Only stores pending approval';
        }
        $text  = $parts ? implode(' · ', $parts) : 'No filter (all the stores this user can see)';
        $label = 'Store export (' . $count . ' store' . ($count === 1 ? '' : 's') . ')';

        $id = ApprovalRequests::submit([
            'module_slug'  => self::SLUG,
            'action_key'   => 'export',
            'action_label' => 'Export Stores',
            'requester_id' => $uid,
            'target_table' => 'store_details',
            'target_label' => $label,
            'summary'      => 'Export ' . $count . ' store(s) to CSV. ' . $text . '.',
            'priority'     => 'low',
            'before'       => null,
            'after'        => ['stores' => $count, 'filter_text' => $text, 'filters' => $filters],
            'field_labels' => ['stores' => 'Stores to export', 'filter_text' => 'Filters', 'filters' => false],
        ]);
        if (! $id) {
            return redirect()->back()->with('error', 'The export request could not be sent for approval. Please try again.');
        }

        return redirect()->back()->with('approval_sent', [
            'ref'      => ApprovalRequests::reference($id),
            'name'     => $label,
            'action'   => 'export',
            'approver' => ApprovalRequests::currentApproverName($id),
        ]);
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