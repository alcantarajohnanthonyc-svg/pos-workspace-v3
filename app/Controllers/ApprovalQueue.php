<?php

namespace App\Controllers;

use App\Libraries\ApprovalApplier;
use App\Libraries\Notifier;
use App\Libraries\Rbac;
use App\Models\Rbac_model;
use Throwable;

/**
 * Approval Queue  (tables: approval_requests, approval_request_steps, approval_request_logs)
 *
 * RBAC module slug "approval_queue":  read, approve, reject
 *
 *  - Not logged in               -> auth/login
 *  - Super Admin (role_id == 1)  -> bypasses every permission check and can act on any request
 *  - Visibility: Super Admin sees every request. Everybody else sees the requests where they are an
 *    approver (current / backup / final) or the requester.
 *  - Acting (approve / reject) needs BOTH the RBAC action AND being the approver of the step that is
 *    waiting right now (current or backup, then final). Nobody but Super Admin approves their own request.
 *  - Flow: stage 1 = current approver OR backup approver (whoever acts first); stage 2 = final approver
 *    (only if one is assigned). Reject at any stage closes the request.
 *  - "Details" = the same partial (_details.php) used by the side drawer and by the full page;
 *    for updates it shows BEFORE / AFTER of every field that changed.
 *
 * NOTE: guards are private (queueDeny / queueAbilities) like StoreDetails.php. If your BaseController already has
 *       denyUnless() / abilities(), swap the calls in queueDeny(), ajaxDeny() and queueAbilities().
 */
class ApprovalQueue extends BaseController
{
    private const SLUG     = 'approval_queue';
    private const PER_PAGE = 15;
    private const BULK_MAX = 50;
    private const ACTIONS  = ['read', 'approve', 'reject'];

    /** @var array{name:?string,icon:?string}|null */
    private ?array $modCols = null;

    // ------------------------------------------------------------------ pages

    public function index()
    {
        if ($deny = $this->queueDeny('read', true)) {
            return $deny;
        }

        $db    = \Config\Database::connect();
        $uid   = $this->userId();
        $super = $this->isSuper();
        $f     = $this->filters();
        $page  = max(1, (int) $this->request->getGet('page'));

        $total = $this->listQuery($db, $uid, $super, $f)->countAllResults();
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        $rows = $this->listQuery($db, $uid, $super, $f, true)
            ->orderBy('r.created_at', 'DESC')
            ->orderBy('r.id', 'DESC')
            ->limit(self::PER_PAGE, ($page - 1) * self::PER_PAGE)
            ->get()->getResultArray();

        $ids   = array_map('intval', array_column($rows, 'id'));
        $steps = $this->stepsFor($db, $ids);
        foreach ($rows as &$r) {
            $r['can_act'] = $this->canAct($r, $steps[(int) $r['id']] ?? [], $uid, $super);
        }
        unset($r);

        // summary cards (not affected by the filters, only by what the user may see)
        $today = date('Y-m-d');
        $stats = [
            'pending'        => $this->visible($db, $uid, $super)->where('r.status', 'pending')->countAllResults(),
            'approved_today' => $this->visible($db, $uid, $super)->where('r.status', 'approved')
                                    ->where('r.decided_at >=', $today . ' 00:00:00')->where('r.decided_at <=', $today . ' 23:59:59')->countAllResults(),
            'rejected_today' => $this->visible($db, $uid, $super)->where('r.status', 'rejected')
                                    ->where('r.decided_at >=', $today . ' 00:00:00')->where('r.decided_at <=', $today . ' 23:59:59')->countAllResults(),
            'modules'        => $this->assignedModules($db, $uid, $super),
        ];

        // filter dropdowns
        $mc      = $this->modCols($db);
        $nameSel = $mc['name'] ? "MAX(m.{$mc['name']})" : 'NULL';
        $modules = $this->visible($db, $uid, $super)
            ->join('rbac_modules m', 'm.slug = r.module_slug', 'left')
            ->select("r.module_slug AS slug, {$nameSel} AS name", false)
            ->groupBy('r.module_slug')->orderBy('r.module_slug', 'ASC')
            ->get()->getResultArray();
        $actions = $this->visible($db, $uid, $super)
            ->select('r.action_key AS k', false)->distinct()->orderBy('r.action_key', 'ASC')
            ->get()->getResultArray();

        return view('approval_queue/index', $this->baseData('Approval Queue', 'Approval Queue', [
            ['label' => 'Home', 'url' => 'dashboard'],
            ['label' => 'Approval Queue'],
        ]) + [
            'rows'    => $rows,
            'total'   => $total,
            'page'    => $page,
            'pages'   => $pages,
            'perPage' => self::PER_PAGE,
            'filters' => $f,
            'stats'   => $stats,
            'modules' => $modules,
            'actions' => array_column($actions, 'k'),
        ]);
    }

    /** Side-drawer content (HTML fragment). */
    public function details(int $id)
    {
        if (! session()->get('isLoggedIn')) {
            return $this->response->setStatusCode(401)->setBody('<div class="p-6 text-sm text-rose-600">Your session expired. Please log in again.</div>');
        }
        if (! $this->allowed('read')) {
            return $this->response->setStatusCode(403)->setBody('<div class="p-6 text-sm text-rose-600">You do not have permission to view this request.</div>');
        }

        $data = $this->loadRequest($id, false);
        if (! $data) {
            return $this->response->setStatusCode(404)->setBody('<div class="p-6 text-sm text-slate-500">Request not found.</div>');
        }

        return $this->response->setBody(view('approval_queue/_details', $data + ['mode' => 'drawer', 'can' => $this->queueAbilities()]));
    }

    /** Full page ("View Full Details"). */
    public function show(int $id)
    {
        if ($deny = $this->queueDeny('read', true)) {
            return $deny;
        }

        $data = $this->loadRequest($id, true);
        if (! $data) {
            return redirect()->to(base_url('approval-queue'))->with('error', 'Request not found.');
        }

        return view('approval_queue/show', $this->baseData('Request ' . $data['req']['reference_no'], 'Request Details', [
            ['label' => 'Home', 'url' => 'dashboard'],
            ['label' => 'Approval Queue', 'url' => 'approval-queue'],
            ['label' => (string) $data['req']['reference_no']],
        ]) + $data);
    }

    // ---------------------------------------------------------------- actions (JSON)

    public function approve(int $id)
    {
        if ($deny = $this->ajaxDeny('approve')) {
            return $deny;
        }
        $body = $this->request->getJSON(true) ?: [];
        $res  = $this->decide($id, 'approve', trim((string) ($body['note'] ?? '')));

        return $this->jsonOut($res['body'], $res['code']);
    }

    public function reject(int $id)
    {
        if ($deny = $this->ajaxDeny('reject')) {
            return $deny;
        }
        $body = $this->request->getJSON(true) ?: [];
        $note = trim((string) ($body['note'] ?? ''));
        if ($note === '') {
            return $this->jsonOut(['ok' => false, 'error' => 'Please write the reason for rejecting this request.'], 422);
        }
        $res = $this->decide($id, 'reject', $note);

        return $this->jsonOut($res['body'], $res['code']);
    }

    /** Approve many at once (only the ones the user may act on; the rest are reported back). */
    public function bulkApprove()
    {
        if ($deny = $this->ajaxDeny('approve')) {
            return $deny;
        }
        $body = $this->request->getJSON(true) ?: [];
        $ids  = array_values(array_unique(array_filter(array_map('intval', (array) ($body['ids'] ?? [])))));
        if (! $ids) {
            return $this->jsonOut(['ok' => false, 'error' => 'No request selected.'], 422);
        }
        $ids = array_slice($ids, 0, self::BULK_MAX);

        $done   = 0;
        $failed = [];
        foreach ($ids as $id) {
            $res = $this->decide($id, 'approve', '');
            if ($res['code'] === 200) {
                $done++;
            } else {
                $failed[] = ['id' => $id, 'error' => (string) ($res['body']['error'] ?? 'Failed')];
            }
        }

        return $this->jsonOut(['ok' => $done > 0, 'approved' => $done, 'failed' => $failed, 'error' => $done === 0 ? 'None of the selected requests could be approved.' : null]);
    }

    // ------------------------------------------------------------------ decision

    /** @return array{code:int, body:array<string,mixed>} */
    private function decide(int $id, string $decision, string $note): array
    {
        $db    = \Config\Database::connect();
        $uid   = $this->userId();
        $super = $this->isSuper();
        $now   = date('Y-m-d H:i:s');

        $db->transBegin();
        try {
            // lock the row so two people cannot decide the same request at the same time
            $req = $db->query('SELECT * FROM approval_requests WHERE id = ? FOR UPDATE', [$id])->getRowArray();
            if (! $req) {
                $db->transRollback();

                return ['code' => 404, 'body' => ['ok' => false, 'error' => 'Request not found.']];
            }
            if ($req['status'] !== 'pending') {
                $db->transRollback();

                return ['code' => 409, 'body' => ['ok' => false, 'error' => 'This request was already ' . $req['status'] . '.']];
            }
            if (! $super && (int) $req['requester_id'] === $uid) {
                $db->transRollback();

                return ['code' => 403, 'body' => ['ok' => false, 'error' => 'You cannot approve or reject your own request.']];
            }

            $steps = $this->stepsFor($db, [$id])[$id] ?? [];
            [$who, $stage] = $this->actorRole($steps, $uid, $super);
            if ($who === null) {
                $db->transRollback();

                return ['code' => 403, 'body' => ['ok' => false, 'error' => 'This request is not waiting for you.']];
            }

            $outcome = '';   // approved | rejected | to_final (used for the notifications after the commit)

            if ($decision === 'reject') {
                if ($who !== 'none') {
                    $db->table('approval_request_steps')->where('request_id', $id)->where('step_role', $who)
                        ->update(['status' => 'rejected', 'acted_by' => $uid, 'acted_at' => $now, 'note' => $note]);
                }
                $db->table('approval_request_steps')->where('request_id', $id)->whereIn('status', ['pending', 'waiting'])
                    ->update(['status' => 'skipped']);
                $db->table('approval_requests')->where('id', $id)->where('status', 'pending')
                    ->update(['status' => 'rejected', 'decided_by' => $uid, 'decided_at' => $now, 'decision_note' => $note, 'updated_at' => $now]);
                $this->log($db, $id, $uid, 'rejected', $note);
                $msg     = 'Request rejected.';
                $outcome = 'rejected';
            } else {
                $final = false;
                if ($stage === '1' || $stage === '0') {
                    if ($who !== 'none') {
                        $db->table('approval_request_steps')->where('request_id', $id)->where('step_role', $who)
                            ->update(['status' => 'approved', 'acted_by' => $uid, 'acted_at' => $now, 'note' => $note]);
                        $other = $who === 'current' ? 'backup' : 'current';
                        $db->table('approval_request_steps')->where('request_id', $id)->where('step_role', $other)->whereIn('status', ['pending', 'waiting'])
                            ->update(['status' => 'skipped', 'note' => $who === 'backup' ? 'Approved by the backup approver' : null]);
                    }
                    $fin = $steps['final'] ?? null;
                    if ($fin && ! empty($fin['approver_id'])) {
                        // a final approver is assigned: the request now waits for him / her
                        $db->table('approval_request_steps')->where('request_id', $id)->where('step_role', 'final')->update(['status' => 'pending']);
                        $this->log($db, $id, $uid, 'approved_step', $note);
                        $msg     = 'Approved. The request now waits for the final approver.';
                        $outcome = 'to_final';
                    } else {
                        $final = true;
                    }
                } else {
                    $db->table('approval_request_steps')->where('request_id', $id)->where('step_role', 'final')
                        ->update(['status' => 'approved', 'acted_by' => $uid, 'acted_at' => $now, 'note' => $note]);
                    $final = true;
                }

                if ($final) {
                    // apply the approved change to its module (inside this transaction: if it cannot be applied, nothing is approved)
                    $applyError = ApprovalApplier::apply($db, $req);
                    if ($applyError !== null) {
                        $db->transRollback();

                        return ['code' => 422, 'body' => ['ok' => false, 'error' => $applyError]];
                    }

                    $db->table('approval_requests')->where('id', $id)->where('status', 'pending')
                        ->update(['status' => 'approved', 'decided_by' => $uid, 'decided_at' => $now, 'decision_note' => $note, 'applied_at' => $now, 'updated_at' => $now]);
                    $this->log($db, $id, $uid, 'approved', $note);
                    $msg     = 'Request approved and applied.';
                    $outcome = 'approved';
                    // HOOK: notify the requester here (email / notification).
                }
            }

            if ($db->transStatus() === false) {
                $db->transRollback();

                return ['code' => 500, 'body' => ['ok' => false, 'error' => 'Could not save the decision. Please try again.']];
            }
            $db->transCommit();

            $this->notifyDecision($req, $steps, $outcome, $note, $uid);

            return ['code' => 200, 'body' => ['ok' => true, 'message' => $msg]];
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'ApprovalQueue decide failed: {m}', ['m' => $e->getMessage()]);

            return ['code' => 500, 'body' => ['ok' => false, 'error' => 'Could not save the decision. Please try again.']];
        }
    }

    /**
     * Tell the right people after a decision was saved.
     *   rejected / approved -> the requester (own wording) and the approvers of the request (not the one who just decided)
     *   to_final            -> the final approver (it is his / her turn now)
     * Each event follows the In-App / Email switches of the module (Module Settings > Notifications).
     *
     * @param array<string,mixed>               $req
     * @param array<string,array<string,mixed>> $steps
     */
    private function notifyDecision(array $req, array $steps, string $outcome, string $note, int $actorId): void
    {
        if ($outcome === '') {
            return;
        }
        $slug      = (string) $req['module_slug'];
        $actor     = Notifier::nameOf($actorId);
        $requester = (int) $req['requester_id'];
        $reqName   = Notifier::nameOf($requester);
        $verb      = Notifier::verb((string) $req['action_key'], (string) $req['action_label']);
        $obj       = ! empty($req['target_label']) ? ' “' . $req['target_label'] . '”' : '';
        $ref       = "\nRef: " . (string) $req['reference_no'];
        $id        = (int) $req['id'];
        $link      = 'approval-queue/show/' . $id;

        if ($outcome === 'to_final') {
            $fin = (int) ($steps['final']['approver_id'] ?? 0);
            Notifier::send($slug, 'approval_request', [$fin], 'Final approval needed', $actor . ' approved the first step of ' . $reqName . '’s request to ' . $verb . $obj . '. It now waits for you.' . $ref, $link);

            return;
        }

        $approverIds = [];
        foreach (['current', 'backup', 'final'] as $role) {
            $approverIds[] = (int) ($steps[$role]['approver_id'] ?? 0);
        }
        $approverIds = array_values(array_diff(array_filter($approverIds), [$actorId, $requester]));

        if ($outcome === 'approved') {
            $event    = 'approval_approved';
            $toUser   = ['Your request was approved', $actor . ' approved your request to ' . $verb . $obj . '. The change is now applied.' . ($note !== '' ? "\nNote: " . $note : '') . $ref];
            $toOthers = ['Request approved', $actor . ' approved ' . $reqName . '’s request to ' . $verb . $obj . '.' . $ref];
        } else {
            $event    = 'approval_rejected';
            $toUser   = ['Your request was rejected', $actor . ' rejected your request to ' . $verb . $obj . '.' . "\nReason: " . $note . $ref];
            $toOthers = ['Request rejected', $actor . ' rejected ' . $reqName . '’s request to ' . $verb . $obj . '.' . "\nReason: " . $note . $ref];
        }

        // the requester may not have access to the Approval Queue, so he / she is sent to the module page
        if ($requester !== $actorId) {
            Notifier::send($slug, $event, [$requester], $toUser[0], $toUser[1], Notifier::moduleUrl($slug));
        }
        Notifier::send($slug, $event, $approverIds, $toOthers[0], $toOthers[1], $link);
    }

    /**
     * Which step is the user allowed to act on right now?
     *
     * @param  array<string,array<string,mixed>> $steps  keyed by step_role
     * @return array{0:?string,1:?string}  [role|null|'none', stage '1'|'f'|'0']
     */
    private function actorRole(array $steps, int $uid, bool $super): array
    {
        $cur = $steps['current'] ?? null;
        $bak = $steps['backup'] ?? null;
        $fin = $steps['final'] ?? null;

        if (! $steps) {
            return $super ? ['none', '0'] : [null, null];
        }

        if ($cur && $cur['status'] === 'pending') {
            if ((int) $cur['approver_id'] === $uid) {
                return ['current', '1'];
            }
            if ($bak && ! empty($bak['approver_id']) && (int) $bak['approver_id'] === $uid && $bak['status'] === 'waiting') {
                return ['backup', '1'];
            }
            if ($super) {
                return ['current', '1'];
            }
        }
        if ($fin && $fin['status'] === 'pending' && ! empty($fin['approver_id'])) {
            if ((int) $fin['approver_id'] === $uid || $super) {
                return ['final', 'f'];
            }
        }

        return [null, null];
    }

    /** Is this request waiting for the user (regardless of the RBAC buttons)? */
    private function canAct(array $r, array $steps, int $uid, bool $super): bool
    {
        if (($r['status'] ?? '') !== 'pending') {
            return false;
        }
        if (! $super && (int) $r['requester_id'] === $uid) {
            return false;
        }

        return $this->actorRole($steps, $uid, $super)[0] !== null;
    }

    private function log($db, int $requestId, int $actorId, string $event, ?string $note): void
    {
        $db->table('approval_request_logs')->insert([
            'request_id' => $requestId,
            'actor_id'   => $actorId,
            'event'      => $event,
            'note'       => $note !== '' ? $note : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // ------------------------------------------------------------------ queries

    /** Requests the user may see (no joins, alias r). */
    private function visible($db, int $uid, bool $super)
    {
        $b = $db->table('approval_requests r');
        if (! $super) {
            $b->groupStart()
                ->where('r.requester_id', $uid)
                ->orWhere("r.id IN (SELECT request_id FROM approval_request_steps WHERE approver_id = {$uid})", null, false)
              ->groupEnd();
        }

        return $b;
    }

    private function listQuery($db, int $uid, bool $super, array $f, bool $withSelect = false)
    {
        $mc = $this->modCols($db);
        $b  = $this->visible($db, $uid, $super)
            ->join('rbac_modules m', 'm.slug = r.module_slug', 'left')
            ->join('usr_account ua', 'ua.id = r.requester_id', 'left')
            ->join('usr_account_info ui', 'ui.account_id = r.requester_id', 'left');

        if ($withSelect) {
            $b->select('r.*, ua.email_workspace, ua.username, ui.avatar')
              ->select("TRIM(CONCAT(COALESCE(ui.first_name, ''), ' ', COALESCE(ui.last_name, ''))) AS requester_name", false)
              ->select($mc['name'] ? "m.{$mc['name']} AS module_name" : 'NULL AS module_name', false)
              ->select($mc['icon'] ? "m.{$mc['icon']} AS module_icon" : 'NULL AS module_icon', false);
        }

        if (($f['status'] ?? 'all') !== 'all') {
            $b->where('r.status', $f['status']);
        }
        if (($f['module'] ?? '') !== '') {
            $b->where('r.module_slug', $f['module']);
        }
        if (($f['action'] ?? '') !== '') {
            $b->where('r.action_key', $f['action']);
        }
        if (($f['q'] ?? '') !== '') {
            $q = $f['q'];
            $b->groupStart()
                ->like('r.reference_no', $q)
                ->orLike('r.action_label', $q)
                ->orLike('r.target_label', $q)
                ->orLike('r.module_slug', $q)
                ->orLike('ui.first_name', $q)
                ->orLike('ui.last_name', $q)
                ->orLike('ua.email_workspace', $q);
            if ($mc['name']) {
                $b->orLike('m.' . $mc['name'], $q);
            }
            $b->groupEnd();
        }

        return $b;
    }

    private function assignedModules($db, int $uid, bool $super): int
    {
        $b = $db->table('approval_requests r')->select('COUNT(DISTINCT r.module_slug) AS n', false);
        if (! $super) {
            $b->join('approval_request_steps s', 's.request_id = r.id', 'inner')->where('s.approver_id', $uid);
        }
        $row = $b->get()->getRowArray();

        return (int) ($row['n'] ?? 0);
    }

    /**
     * Steps (with approver / actor names) grouped by request id then step_role.
     *
     * @param  list<int> $ids
     * @return array<int,array<string,array<string,mixed>>>
     */
    private function stepsFor($db, array $ids): array
    {
        if (! $ids) {
            return [];
        }
        $rows = $db->table('approval_request_steps s')
            ->select('s.*, au.email_workspace AS approver_email')
            ->select("TRIM(CONCAT(COALESCE(ai.first_name, ''), ' ', COALESCE(ai.last_name, ''))) AS approver_name", false)
            ->select("TRIM(CONCAT(COALESCE(bi.first_name, ''), ' ', COALESCE(bi.last_name, ''))) AS actor_name", false)
            ->join('usr_account au', 'au.id = s.approver_id', 'left')
            ->join('usr_account_info ai', 'ai.account_id = s.approver_id', 'left')
            ->join('usr_account_info bi', 'bi.account_id = s.acted_by', 'left')
            ->whereIn('s.request_id', $ids)
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['request_id']][(string) $r['step_role']] = $r;
        }

        return $out;
    }

    /** @return array<string,mixed>|null */
    private function loadRequest(int $id, bool $withLogs): ?array
    {
        $db    = \Config\Database::connect();
        $uid   = $this->userId();
        $super = $this->isSuper();

        $r = $this->listQuery($db, $uid, $super, ['status' => 'all', 'module' => '', 'action' => '', 'q' => ''], true)
            ->where('r.id', $id)->get()->getRowArray();
        if (! $r) {
            return null;
        }

        $steps = $this->stepsFor($db, [$id])[$id] ?? [];
        $logs  = [];
        if ($withLogs) {
            $logs = $db->table('approval_request_logs l')
                ->select('l.*, ua.email_workspace')
                ->select("TRIM(CONCAT(COALESCE(ui.first_name, ''), ' ', COALESCE(ui.last_name, ''))) AS actor_name", false)
                ->join('usr_account ua', 'ua.id = l.actor_id', 'left')
                ->join('usr_account_info ui', 'ui.account_id = l.actor_id', 'left')
                ->where('l.request_id', $id)
                ->orderBy('l.id', 'ASC')
                ->get()->getResultArray();
        }

        return [
            'req'    => $r,
            'steps'  => $steps,
            'diff'   => $this->buildDiff($r),
            'canAct' => $this->canAct($r, $steps, $uid, $super),
            'logs'   => $logs,
        ];
    }

    /**
     * Field-by-field comparison from payload_before / payload_after (JSON objects).
     *   update -> every field, flagged changed / same
     *   create -> every field is "added"        delete -> every field is "removed"
     *
     * @return array{mode:string, rows:list<array<string,mixed>>, changed:int}
     */
    private function buildDiff(array $r): array
    {
        $before = json_decode((string) ($r['payload_before'] ?? ''), true);
        $after  = json_decode((string) ($r['payload_after'] ?? ''), true);
        $before = is_array($before) ? $before : [];
        $after  = is_array($after) ? $after : [];
        $labels = json_decode((string) ($r['field_labels'] ?? ''), true);
        $labels = is_array($labels) ? $labels : [];

        $key  = strtolower((string) $r['action_key']);
        $mode = $key === 'create' ? 'create' : ($key === 'delete' ? 'delete' : 'update');

        $str = static function ($v): string {
            if ($v === null) {
                return '';
            }
            if (is_bool($v)) {
                return $v ? 'Yes' : 'No';
            }
            if (is_array($v)) {
                return (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return trim((string) $v);
        };

        $keys = array_values(array_unique(array_merge(array_keys($before), array_keys($after))));
        $rows = [];
        $changed = 0;
        foreach ($keys as $k) {
            if (array_key_exists($k, $labels) && $labels[$k] === false) {
                continue;   // technical field (e.g. branch_id): only used to apply the change, not shown
            }
            $b = $str($before[$k] ?? null);
            $a = $str($after[$k] ?? null);
            if ($mode === 'create') {
                $state = 'added';
            } elseif ($mode === 'delete') {
                $state = 'removed';
            } else {
                $state = $b === $a ? 'same' : 'changed';
            }
            if ($state !== 'same') {
                $changed++;
            }
            $rows[] = [
                'key'    => (string) $k,
                'label'  => (string) ($labels[$k] ?? ucwords(str_replace(['_', '-'], ' ', (string) $k))),
                'before' => $b,
                'after'  => $a,
                'state'  => $state,
            ];
        }

        return ['mode' => $mode, 'rows' => $rows, 'changed' => $changed];
    }

    // ---------------------------------------------------------------- helpers

    private function filters(): array
    {
        $status = strtolower(trim((string) $this->request->getGet('status')));
        $status = in_array($status, ['pending', 'approved', 'rejected', 'cancelled', 'all'], true) ? $status : 'pending';

        return [
            'module' => trim((string) $this->request->getGet('module')),
            'action' => trim((string) $this->request->getGet('action')),
            'status' => $status,
            'q'      => trim((string) $this->request->getGet('q')),
        ];
    }

    /** Name / icon columns of rbac_modules (the names differ between projects). */
    private function modCols($db): array
    {
        if ($this->modCols !== null) {
            return $this->modCols;
        }
        $fields = $db->tableExists('rbac_modules') ? $db->getFieldNames('rbac_modules') : [];
        $pick   = static function (array $cands) use ($fields): ?string {
            foreach ($cands as $c) {
                if (in_array($c, $fields, true)) {
                    return $c;
                }
            }

            return null;
        };

        return $this->modCols = [
            'name' => $pick(['name', 'module_name', 'title', 'label']),
            'icon' => $pick(['icon', 'icon_class', 'fa_icon']),
        ];
    }

    private function baseData(string $title, string $pageTitle, array $breadcrumbs): array
    {
        $userId = session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id');
        $roleId = session()->get('user_role_id') ?? session()->get('role_id');

        return [
            'title'           => $title,
            'pageTitle'       => $pageTitle,
            'filteredModules' => (new Rbac_model())->get_user_modules($userId, $roleId),
            'can'             => $this->queueAbilities(),
            'breadcrumbs'     => $breadcrumbs,
        ];
    }

    private function userId(): int
    {
        return (int) (session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id'));
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
    private function queueAbilities(): array
    {
        $out = [];
        foreach (self::ACTIONS as $a) {
            $out[$a] = $this->allowed($a);
        }

        return $out;
    }

    /** Login + permission guard. $page = true for GET pages, false for POST actions. */
    private function queueDeny(string $action, bool $page)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        if (! $this->allowed($action)) {
            return $page
                ? redirect()->to(base_url('dashboard'))->with('error', 'You do not have permission to open the Approval Queue.')
                : redirect()->back()->with('error', 'You do not have permission to do this (Approval Queue: ' . $action . ').');
        }

        return null;
    }

    /** JSON guard for the approve / reject requests. */
    private function ajaxDeny(string $action)
    {
        if (! session()->get('isLoggedIn')) {
            return $this->jsonOut(['ok' => false, 'error' => 'Your session expired. Please log in again.'], 401);
        }
        if (! $this->allowed($action)) {
            return $this->jsonOut(['ok' => false, 'error' => 'You do not have permission to do this (Approval Queue: ' . $action . ').'], 403);
        }

        return null;
    }

    private function jsonOut(array $data, int $status = 200)
    {
        helper('security');

        return $this->response->setStatusCode($status)->setJSON($data + ['csrf' => csrf_hash()]);
    }
}