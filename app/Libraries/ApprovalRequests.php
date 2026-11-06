<?php

namespace App\Libraries;

use Throwable;

/**
 * Other modules (Store Details, Terminals, ...) call this when a lower position changes something
 * that needs a superior's approval. The request then appears in the Approval Queue.
 *
 *   ApprovalRequests::submit([
 *       'module_slug'  => 'store_details',
 *       'action_key'   => 'update',                       // create | update | delete | import | export ...
 *       'action_label' => 'Update Store Information',
 *       'requester_id' => $userId,
 *       'target_table' => 'store_details',
 *       'target_id'    => 1001,
 *       'target_label' => 'Davao Main Store',
 *       'summary'      => 'Update store information for Davao Main Store.',
 *       'scope_text'   => 'Company A - Davao',
 *       'priority'     => 'high',                         // low | medium | high
 *       'before'       => ['store_name' => 'Old', 'store_address' => 'Old address'],
 *       'after'        => ['store_name' => 'New', 'store_address' => 'New address'],
 *       'field_labels' => ['store_name' => 'Store Name'], // optional
 *       // 'approvers' => ['current' => 5, 'backup' => 9, 'final' => null],   // optional, see resolveApprovers()
 *   ]);
 *
 * Returns the new request id, or null when it could not be saved (see the log).
 */
class ApprovalRequests
{
    public static function submit(array $d): ?int
    {
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        $requesterId = (int) ($d['requester_id'] ?? 0);
        $slug        = trim((string) ($d['module_slug'] ?? ''));
        if ($requesterId <= 0 || $slug === '' || trim((string) ($d['action_key'] ?? '')) === '') {
            return null;
        }

        $approvers = $d['approvers'] ?? self::resolveApprovers($db, $slug, $requesterId);
        $priority  = in_array(($d['priority'] ?? 'medium'), ['low', 'medium', 'high'], true) ? $d['priority'] : 'medium';

        $db->transBegin();
        try {
            $id = null;
            for ($try = 0; $try < 5 && ! $id; $try++) {
                $ref = self::nextReference($db, $try);
                $ok  = $db->table('approval_requests')->ignore(true)->insert([
                    'reference_no'    => $ref,
                    'module_slug'     => $slug,
                    'action_key'      => strtolower(trim((string) $d['action_key'])),
                    'action_label'    => (string) ($d['action_label'] ?? ucfirst((string) $d['action_key'])),
                    'target_table'    => $d['target_table'] ?? null,
                    'target_id'       => $d['target_id'] ?? null,
                    'target_label'    => $d['target_label'] ?? null,
                    'summary'         => $d['summary'] ?? null,
                    'requester_id'    => $requesterId,
                    'requester_title' => $d['requester_title'] ?? null,
                    'scope_text'      => $d['scope_text'] ?? null,
                    'company_id'      => $d['company_id'] ?? null,
                    'branch_id'       => $d['branch_id'] ?? null,
                    'priority'        => $priority,
                    'status'          => 'pending',
                    'payload_before'  => isset($d['before']) ? json_encode($d['before'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'payload_after'   => isset($d['after']) ? json_encode($d['after'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    'field_labels'    => isset($d['field_labels']) ? json_encode($d['field_labels'], JSON_UNESCAPED_UNICODE) : null,
                    'created_at'      => $now,
                ]);
                if ($ok && $db->affectedRows() > 0) {
                    $id = (int) $db->insertID();
                }
            }
            if (! $id) {
                $db->transRollback();

                return null;
            }

            foreach (['current', 'backup', 'final'] as $role) {
                $approver = isset($approvers[$role]) ? (int) $approvers[$role] : null;
                $db->table('approval_request_steps')->insert([
                    'request_id'  => $id,
                    'step_role'   => $role,
                    'approver_id' => $approver ?: null,
                    'status'      => $role === 'current' ? 'pending' : 'waiting',
                ]);
            }

            $db->table('approval_request_logs')->insert([
                'request_id' => $id,
                'actor_id'   => $requesterId,
                'event'      => 'submitted',
                'note'       => null,
                'created_at' => $now,
            ]);

            if ($db->transStatus() === false) {
                $db->transRollback();

                return null;
            }
            $db->transCommit();

            // tell the approvers (In-App / Email as set in Module Settings > Notifications)
            $who   = Notifier::nameOf($requesterId);
            $verb  = Notifier::verb((string) $d['action_key'], (string) ($d['action_label'] ?? ''));
            $label = (string) ($d['target_label'] ?? '');
                      $actKey   = strtolower(trim((string) $d['action_key']));
            $afterArr = is_array($d['after'] ?? null) ? $d['after'] : [];
            $hasLines = $actKey === 'import' || (empty($d['target_id']) && in_array($actKey, ['update', 'delete'], true));
            $mode     = (($afterArr['mode'] ?? '') === 'bulk' || in_array($actKey, ['import', 'export'], true)) ? 'info'
                      : (in_array($actKey, ['create', 'delete', 'update'], true) ? $actKey : 'info');
            $modRow   = $db->table('rbac_modules')->select('name')->where('slug', $slug)->get()->getRowArray();

            Notifier::send(
                $slug,
                'approval_request',
                [$approvers['current'] ?? null, $approvers['backup'] ?? null],
                'Approval needed',
                ($label !== '' ? $who . ' asked to ' . $verb . ' “' . $label . '”.' : $who . ' sent a request: ' . (string) ($d['action_label'] ?? $verb) . '.') . "\nRef: " . self::reference($id),
                'approval-queue/show/' . $id,
                [
                    'approval' => ['request_id' => $id, 'by_email' => ! $hasLines],   // many lines: review in the app only
                    'facts'    => [
                        'Module'       => (string) ($modRow['name'] ?? $slug),
                        'Action'       => (string) ($d['action_label'] ?? ''),
                        'Requested by' => $who,
                        'Reference'    => self::reference($id),
                        'Scope'        => (string) ($d['scope_text'] ?? ''),
                    ],
                    'mode'     => $mode,
                    'changes'  => self::emailChanges($d),
                ]
            );

            return $id;
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'ApprovalRequests::submit failed: {m}', ['m' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Who approves a request of this module?
     *   current = first module administrator (rbac_module_admins: module_id, account_id), backup = the second one,
     *   nobody configured -> the Super Admins. The requester never approves his / her own request.
     *
     * @return array{current:?int, backup:?int, final:?int}
     */
    public static function resolveApprovers($db, string $slug, int $requesterId): array
    {
        $ids = self::adminIds($db, $slug);

        $supers = array_map('intval', array_column(
            $db->table('usr_account')->select('id')->where('user_role_id', 1)->orderBy('id', 'ASC')->get()->getResultArray(),
            'id'
        ));

        $ids = array_values(array_filter(array_unique(array_merge($ids, $supers)), static fn ($i) => $i > 0 && $i !== $requesterId));

        return [
            'current' => $ids[0] ?? null,
            'backup'  => $ids[1] ?? null,
            'final'   => null,
        ];
    }

    /** Administrators of a module (Module Settings > Module Administrators), oldest first. @return list<int> */
    public static function adminIds($db, string $slug): array
    {
        $mod = $db->table('rbac_modules')->where('slug', $slug)->get()->getRowArray();
        if (! $mod || ! $db->tableExists('rbac_module_admins')) {
            return [];
        }

        return array_map('intval', array_column(
            $db->table('rbac_module_admins')->select('account_id')->where('module_id', (int) $mod['id'])->orderBy('id', 'ASC')->get()->getResultArray(),
            'account_id'
        ));
    }

    public static function isModuleAdmin(string $slug, int $accountId): bool
    {
        return $accountId > 0 && in_array($accountId, self::adminIds(\Config\Database::connect(), $slug), true);
    }

    /**
     * Is "Requires approval" ticked for this action of the module (Module Settings > Module Administrators > Module Actions)?
     * The flag is looked up in the usual places (a table with module_id + an action reference + a *approval* column).
     * Nothing found -> false (the action is applied directly, nothing breaks).
     */
    public static function requiresApproval(string $slug, string $action): bool
    {
        $db     = \Config\Database::connect();
        $action = strtolower(trim($action));
        $mod    = $db->table('rbac_modules')->where('slug', $slug)->get()->getRowArray();
        if (! $mod || $action === '') {
            return false;
        }

        $flagCols = ['requires_approval', 'require_approval', 'needs_approval', 'need_approval', 'approval_required', 'is_approval_required'];
        $tables   = ['rbac_permissions', 'rbac_module_actions', 'module_actions', 'module_approval_settings', 'rbac_module_approval_settings', 'module_approvals'];

        foreach ($tables as $t) {
            if (! $db->tableExists($t)) {
                continue;
            }
            $f    = $db->getFieldNames($t);
            $flag = null;
            foreach ($flagCols as $c) {
                if (in_array($c, $f, true)) { $flag = $c; break; }
            }
            if ($flag === null || ! in_array('module_id', $f, true)) {
                continue;
            }

            $b = $db->table($t)->where('module_id', (int) $mod['id']);
            if (in_array('action_key', $f, true))      { $b->where('LOWER(action_key) = ' . $db->escape($action), null, false); }
            elseif (in_array('action_slug', $f, true)) { $b->where('LOWER(action_slug) = ' . $db->escape($action), null, false); }
            elseif (in_array('action', $f, true))      { $b->where('LOWER(`action`) = ' . $db->escape($action), null, false); }
            elseif (in_array('action_id', $f, true) && $db->tableExists('rbac_actions')) {
                $af = $db->getFieldNames('rbac_actions');
                $ac = null;
                foreach (['slug', 'action_key', 'key', 'code', 'name'] as $c) {
                    if (in_array($c, $af, true)) { $ac = $c; break; }
                }
                if ($ac === null) { continue; }
                $act = $db->table('rbac_actions')->where('LOWER(`' . $ac . '`) = ' . $db->escape($action), null, false)->get()->getRowArray();
                if (! $act) { continue; }
                $b->where('action_id', (int) $act['id']);
            } else {
                continue;
            }

            $row = $b->get()->getRowArray();
            if ($row) {
                return ! empty($row[$flag]);
            }
        }

        return false;
    }

    /** A pending request for the same record and action already exists? Returns its reference_no. */
    public static function pendingFor(string $slug, string $table, int $targetId, string $action): ?string
    {
        $row = \Config\Database::connect()->table('approval_requests')
            ->select('reference_no')
            ->where('module_slug', $slug)->where('target_table', $table)->where('target_id', $targetId)
            ->where('action_key', strtolower($action))->where('status', 'pending')
            ->get()->getRowArray();

        return $row['reference_no'] ?? null;
    }

    /** Name of the person the request waits for right now (current approver), for the "Sent for approval" popup. */
    public static function currentApproverName(int $id): string
    {
        $row = \Config\Database::connect()->table('approval_request_steps s')
            ->select('au.email_workspace')
            ->select("TRIM(CONCAT(COALESCE(ai.first_name, ''), ' ', COALESCE(ai.last_name, ''))) AS name", false)
            ->join('usr_account au', 'au.id = s.approver_id', 'left')
            ->join('usr_account_info ai', 'ai.account_id = s.approver_id', 'left')
            ->where('s.request_id', $id)->where('s.step_role', 'current')
            ->get()->getRowArray();
        if (! $row) {
            return '';
        }
        $name = trim((string) ($row['name'] ?? ''));

        return $name !== '' ? $name : (string) ($row['email_workspace'] ?? '');
    }

    /**
     * Pending requests of the given records, so a list page can mark them ("Pending update approval").
     *
     * @param  list<int|string> $ids
     * @return array<int,array<string,array{ref:string,by:string,when:string}>>  target_id => action_key => info
     */
    public static function pendingMap(string $slug, string $table, array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (! $ids) {
            return [];
        }
        $rows = \Config\Database::connect()->table('approval_requests r')
            ->select('r.target_id, r.action_key, r.reference_no, r.created_at')
            ->select("TRIM(CONCAT(COALESCE(ui.first_name, ''), ' ', COALESCE(ui.last_name, ''))) AS by_name", false)
            ->join('usr_account_info ui', 'ui.account_id = r.requester_id', 'left')
            ->where('r.module_slug', $slug)->where('r.target_table', $table)->where('r.status', 'pending')
            ->whereIn('r.target_id', $ids)
            ->orderBy('r.id', 'DESC')
            ->get()->getResultArray();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['target_id']][(string) $r['action_key']] = [
                'ref'  => (string) $r['reference_no'],
                'by'   => trim((string) $r['by_name']) !== '' ? trim((string) $r['by_name']) : 'Someone',
                'when' => Notifier::ago((string) $r['created_at']),
            ];
        }

        // bulk requests (many stores in ONE request, no single target_id): mark the stores that are inside them
        $bulk = \Config\Database::connect()->table('approval_requests r')
            ->select('r.action_key, r.reference_no, r.created_at, r.payload_after')
            ->select("TRIM(CONCAT(COALESCE(ui.first_name, ''), ' ', COALESCE(ui.last_name, ''))) AS by_name", false)
            ->join('usr_account_info ui', 'ui.account_id = r.requester_id', 'left')
            ->where('r.module_slug', $slug)->where('r.target_table', $table)->where('r.status', 'pending')
            ->where('r.target_id IS NULL', null, false)->whereIn('r.action_key', ['update', 'delete'])
            ->orderBy('r.id', 'DESC')->limit(50)->get()->getResultArray();
        foreach ($bulk as $b) {
            $after = json_decode((string) $b['payload_after'], true);
            if (! is_array($after) || ($after['mode'] ?? '') !== 'bulk') {
                continue;
            }
            foreach ((array) ($after['items'] ?? []) as $it) {
                $tid = (int) ($it['id'] ?? 0);
                if ($tid > 0 && in_array($tid, $ids, true) && ! isset($map[$tid][(string) $b['action_key']])) {
                    $map[$tid][(string) $b['action_key']] = [
                        'ref'  => (string) $b['reference_no'],
                        'by'   => trim((string) $b['by_name']) !== '' ? trim((string) $b['by_name']) : 'Someone',
                        'when' => Notifier::ago((string) $b['created_at']),
                    ];
                }
            }
        }

        return $map;
    }

    /**
     * Pending "create" requests of a module (the record does not exist yet, so a list cannot mark a row).
     *
     * @param  int|null $requesterId only this person's requests (null = everybody's)
     * @return array{total:int, items:list<array{ref:string,name:string,by:string,when:string}>}
     */
    public static function pendingNew(string $slug, ?int $requesterId, int $limit = 6): array
    {
        $db = \Config\Database::connect();
        $q  = static function () use ($db, $slug, $requesterId) {
            $b = $db->table('approval_requests r')->where('r.module_slug', $slug)->whereIn('r.action_key', ['create', 'import'])->where('r.status', 'pending');
            if ($requesterId !== null) {
                $b->where('r.requester_id', $requesterId);
            }

            return $b;
        };

        $total = $q()->countAllResults();
        $rows  = $total === 0 ? [] : $q()
            ->select('r.reference_no, r.target_label, r.created_at')
            ->select("TRIM(CONCAT(COALESCE(ui.first_name, ''), ' ', COALESCE(ui.last_name, ''))) AS by_name", false)
            ->join('usr_account_info ui', 'ui.account_id = r.requester_id', 'left')
            ->orderBy('r.id', 'DESC')->limit($limit)->get()->getResultArray();

        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'ref'  => (string) $r['reference_no'],
                'name' => (string) ($r['target_label'] ?? ''),
                'by'   => trim((string) $r['by_name']) !== '' ? trim((string) $r['by_name']) : 'Someone',
                'when' => Notifier::ago((string) $r['created_at']),
            ];
        }

        return ['total' => $total, 'items' => $items];
    }

        /** Rows for the approval email: [label, before, after]. Fields whose label is false are technical and hidden. */
    public static function emailChanges(array $d): array
    {
        $before = is_array($d['before'] ?? null) ? $d['before'] : [];
        $after  = is_array($d['after'] ?? null) ? $d['after'] : [];
        $labels = is_array($d['field_labels'] ?? null) ? $d['field_labels'] : [];
        $act    = strtolower(trim((string) ($d['action_key'] ?? '')));
        $flat   = static function ($v): string {
            if ($v === null) {
                return '';
            }
            if (is_bool($v)) {
                return $v ? 'Yes' : 'No';
            }

            return is_array($v) ? (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : trim((string) $v);
        };

        $out = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $k) {
            if (array_key_exists($k, $labels) && $labels[$k] === false) {
                continue;
            }
            $b = $flat($before[$k] ?? null);
            $a = $flat($after[$k] ?? null);
            if ($act === 'update' && $b === $a) {
                continue;   // unchanged field: not shown
            }
            $out[] = [(string) ($labels[$k] ?? ucwords(str_replace(['_', '-'], ' ', (string) $k))), mb_substr($b, 0, 300), mb_substr($a, 0, 300)];
        }

        return array_slice($out, 0, 25);
    }
    
    public static function reference(int $id): string
    {
        $row = \Config\Database::connect()->table('approval_requests')->select('reference_no')->where('id', $id)->get()->getRowArray();

        return (string) ($row['reference_no'] ?? '');
    }

    private static function nextReference($db, int $offset = 0): string
    {
        $prefix = 'REQ-' . date('Ymd') . '-';
        $row    = $db->query('SELECT COUNT(*) AS n FROM approval_requests WHERE reference_no LIKE ?', [$prefix . '%'])->getRowArray();
        $n      = (int) ($row['n'] ?? 0) + 1 + $offset;

        return $prefix . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }
}
