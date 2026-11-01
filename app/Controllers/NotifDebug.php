<?php

namespace App\Controllers;

use App\Libraries\ApprovalRequests;
use App\Libraries\Notifier;
use Throwable;

/**
 * TEMPORARY diagnostic page. Open it while logged in as the user who edits a store:
 *     http://localhost:8080/notifdebug
 * It prints (plain text) every step the approval + notification flow needs, and says which one fails.
 * It writes ONE test notification to the logged-in user. Delete this file and its route when you are done.
 */
class NotifDebug extends BaseController
{
    private const SLUG = 'store_details';

    private array $out = [];

    public function index()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        $db   = \Config\Database::connect();
        $uid  = (int) (session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id'));
        $role = (int) (session()->get('user_role_id') ?? session()->get('role_id'));

        $this->h('1. WHO AM I');
        $this->l("account id = {$uid}, role id = {$role}" . ($role === 1 ? '   <-- SUPER ADMIN: edits are applied directly, NO request and NO notification is made for yourself' : ''));

        $this->h('2. ARE THE NEW FILES IN THE PROJECT?');
        $this->l('Notifier class ................ ' . $this->yn(class_exists(Notifier::class)));
        $this->l('ApprovalRequests::pendingMap .. ' . $this->yn(method_exists(ApprovalRequests::class, 'pendingMap')) . '   (new version of ApprovalRequests.php)');
        $this->l('ApprovalRequests::requiresApproval ' . $this->yn(method_exists(ApprovalRequests::class, 'requiresApproval')));
        $this->l('StoreDetails::sendForApproval . ' . $this->yn(method_exists(StoreDetails::class, 'sendForApproval')) . '   (new version of StoreDetails.php)');
        $this->l('StoreDetails::notifyChange .... ' . $this->yn(method_exists(StoreDetails::class, 'notifyChange')));
        $this->l('ApprovalApplier class ......... ' . $this->yn(class_exists(\App\Libraries\ApprovalApplier::class)));
        $src = @file_get_contents(APPPATH . 'Libraries/ApprovalRequests.php') ?: '';
        $this->l('ApprovalRequests.php calls Notifier::send ... ' . $this->yn(str_contains($src, 'Notifier::send')) . '   (if NO: old file, this is why no notification is made)');

        $aq = @file_get_contents(APPPATH . 'Controllers/ApprovalQueue.php') ?: '';
        $this->l('ApprovalQueue.php notifies after approve / reject ... ' . $this->yn(str_contains($aq, 'notifyDecision(')) . '   (if NO: old ApprovalQueue.php, this is why the requester gets nothing)');

        $this->h('3. TABLES');
        foreach (['approval_requests', 'approval_request_steps', 'approval_request_logs', 'notifications', 'module_notification_settings', 'module_notification_prefs', 'rbac_module_admins', 'rbac_permissions', 'rbac_actions'] as $t) {
            $this->l(str_pad($t, 32, '.') . ' ' . $this->yn($db->tableExists($t)));
        }

        $this->h('4. MODULE ' . self::SLUG);
        $mod = $db->table('rbac_modules')->where('slug', self::SLUG)->get()->getRowArray();
        $this->l($mod ? 'module id = ' . $mod['id'] : 'NOT FOUND in rbac_modules (slug ' . self::SLUG . ')');

        $this->h('5. ADMINISTRATORS OF THE MODULE');
        $admins = ApprovalRequests::adminIds($db, self::SLUG);
        $this->l('administrators (rbac_module_admins): ' . ($admins ? implode(', ', $admins) : 'NONE'));
        $isAdmin = ApprovalRequests::isModuleAdmin(self::SLUG, $uid);
        $this->l('am I an administrator of this module? ' . $this->yn($isAdmin) . ($isAdmin ? '   <-- administrators apply directly: NO request' : ''));

        $this->h('6. "REQUIRES APPROVAL" AS THE CODE READS IT');
        foreach (['create', 'update', 'delete', 'import'] as $a) {
            $this->l(str_pad($a, 8, '.') . ' ' . $this->yn(ApprovalRequests::requiresApproval(self::SLUG, $a)));
        }
        if ($mod) {
            $perm = $db->query('SELECT p.id, a.action_key, p.requires_approval FROM rbac_permissions p LEFT JOIN rbac_actions a ON a.id = p.action_id WHERE p.module_id = ?', [(int) $mod['id']])->getResultArray();
            $this->l('rbac_permissions of this module: ' . json_encode($perm));
        }

        $this->h('7. WOULD MY EDIT GO TO APPROVAL?');
        $needs = $role !== 1 && ! $isAdmin && ApprovalRequests::requiresApproval(self::SLUG, 'update');
        $this->l('update by me needs approval: ' . $this->yn($needs));
        if (! $needs) {
            $why = $role === 1 ? 'I am Super Admin' : ($isAdmin ? 'I am an administrator of the module' : '"Requires approval" for update is not found / is OFF');
            $this->l('   reason: ' . $why . '  -> the edit is saved directly, so there is no request and no notification.');
        }

        $this->h('8. WHO WOULD BE ASKED TO APPROVE (and notified)');
        $ap = ApprovalRequests::resolveApprovers($db, self::SLUG, $uid);
        $this->l('current = ' . ($ap['current'] ?? 'none') . ', backup = ' . ($ap['backup'] ?? 'none'));
        foreach (array_filter([$ap['current'] ?? null, $ap['backup'] ?? null]) as $id) {
            $r = $db->table('usr_account')->select('id, email_workspace, account_status')->where('id', (int) $id)->get()->getRowArray();
            $this->l('   account ' . $id . ': ' . json_encode($r) . (($r['account_status'] ?? '') !== 'active' ? '   <-- NOT "active": Notifier skips it' : ''));
        }

        $this->h('9. NOTIFICATION SWITCHES (Module Settings > Notifications)');
        if ($mod) {
            $set = Notifier::settingsForModule((int) $mod['id']);
            $this->l('approval_request: in_app=' . $this->yn($set['approval_request']['in_app']) . ', email=' . $this->yn($set['approval_request']['email']) . ', saved=' . $this->yn($set['approval_request']['saved']));
        }

        $this->h('10. DIRECT WRITE TEST (one row for me)');
        try {
            $ok = $db->table('notifications')->insert([
                'account_id' => $uid, 'module_slug' => self::SLUG, 'event_key' => 'system_alert',
                'title' => 'Debug: database write works', 'body' => 'Created by /notifdebug', 'url' => 'dashboard', 'is_read' => 0, 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->l('insert into notifications: ' . ($ok ? 'OK (id ' . $db->insertID() . ')' : 'FAILED ' . json_encode($db->error())));
        } catch (Throwable $e) {
            $this->l('insert into notifications FAILED: ' . $e->getMessage());
        }

        $this->h('11. Notifier::send TEST (to the approver, as if I sent a request)');
        $target = (int) ($ap['current'] ?? 0);
        if ($target > 0) {
            $before = (int) $db->table('notifications')->where('account_id', $target)->countAllResults();
            Notifier::send(self::SLUG, 'approval_request', [$target], 'Debug: Notifier::send test', 'Created by /notifdebug', 'dashboard');
            $after = (int) $db->table('notifications')->where('account_id', $target)->countAllResults();
            $this->l("notifications of account {$target}: before={$before}, after={$after} -> " . ($after > $before ? 'Notifier::send WORKS' : 'NOTHING WAS WRITTEN (see the log lines below)'));
        } else {
            $this->l('no approver to test with');
        }

        $this->h('12. LAST ROWS');
        $this->l('approval_requests: ' . json_encode($db->table('approval_requests')->select('id, reference_no, action_key, status, requester_id')->orderBy('id', 'DESC')->limit(3)->get()->getResultArray()));
        $this->l('notifications:     ' . json_encode($db->table('notifications')->select('id, account_id, event_key, title, created_at')->orderBy('id', 'DESC')->limit(5)->get()->getResultArray()));

        $this->h('13. LOG LINES (Notifier / ApprovalRequests, today)');
        $log = WRITEPATH . 'logs/log-' . date('Y-m-d') . '.log';
        if (is_file($log)) {
            $lines = array_values(array_filter(explode("\n", (string) file_get_contents($log)), static fn ($l) => stripos($l, 'Notifier') !== false || stripos($l, 'ApprovalRequests') !== false));
            foreach (array_slice($lines, -8) as $l) {
                $this->l(substr($l, 0, 260));
            }
            if (! $lines) {
                $this->l('(none)');
            }
        } else {
            $this->l('(no log file for today)');
        }

        return $this->response->setContentType('text/plain; charset=utf-8')->setBody(implode("\n", $this->out) . "\n");
    }

    private function h(string $t): void
    {
        $this->out[] = '';
        $this->out[] = '== ' . $t;
    }

    private function l(string $t): void
    {
        $this->out[] = $t;
    }

    private function yn($v): string
    {
        return $v ? 'YES' : 'NO';
    }
}