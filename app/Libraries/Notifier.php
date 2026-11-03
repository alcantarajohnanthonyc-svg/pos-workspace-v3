<?php

namespace App\Libraries;

use Throwable;

/**
 * Notifications for POS Workspace: in-app (the bell) and email, controlled per module and per event
 * from Module Settings > Notifications.
 *
 *   Notifier::send('store_details', 'approval_request', [$approverId], 'Approval needed', 'Juan asked to update ...', 'approval-queue/show/5');
 *
 * For every event the module has an In-App switch and an Email switch:
 *   both on -> both are sent;  one on -> that channel only;  both off -> nobody is notified.
 * An event the administrator never saved uses the module's "Default Notification Preference".
 *
 * send() never throws: a mail problem must not break the action the user just did (it is written to the log).
 */
class Notifier
{
    /**
     * Events shown in the Notifications tab.
     * wired = the application already sends it; the others are listed so the switches are ready when they are wired.
     * in_app / email = what is on when the administrator has not saved anything yet (also limited by the default preference).
     */
    public const EVENTS = [
        'approval_request'  => ['label' => 'Approval Request',  'recipients' => 'Assigned approvers',     'wired' => true,  'in_app' => 1, 'email' => 1, 'help' => 'A request is waiting for approval.'],
        'approval_approved' => ['label' => 'Approval Approved', 'recipients' => 'Requester, approvers',   'wired' => true,  'in_app' => 1, 'email' => 1, 'help' => 'A request was approved and applied.'],
        'approval_rejected' => ['label' => 'Approval Rejected', 'recipients' => 'Requester, approvers',   'wired' => true,  'in_app' => 1, 'email' => 1, 'help' => 'A request was rejected (with the reason).'],
        'record_changed'    => ['label' => 'Record Changed',    'recipients' => 'Module administrators',  'wired' => true,  'in_app' => 1, 'email' => 0, 'help' => 'Someone added, changed or deleted a record directly.'],
        'import_completed'  => ['label' => 'Import Completed',  'recipients' => 'User who imported',      'wired' => false, 'in_app' => 1, 'email' => 0, 'help' => 'A file import has finished.'],
        'report_generated'  => ['label' => 'Report Generated',  'recipients' => 'Report recipients',      'wired' => false, 'in_app' => 1, 'email' => 0, 'help' => 'A scheduled report is ready.'],
        'system_alert'      => ['label' => 'System Alerts',     'recipients' => 'All assigned users',     'wired' => false, 'in_app' => 1, 'email' => 1, 'help' => 'Important messages about this module.'],
    ];

    // ------------------------------------------------------------------ settings

    public static function defaultPref(int $moduleId): string
    {
        $row = \Config\Database::connect()->table('module_notification_prefs')->where('module_id', $moduleId)->get()->getRowArray();
        $p   = (string) ($row['default_pref'] ?? 'both');

        return in_array($p, ['in_app', 'email', 'both'], true) ? $p : 'both';
    }

    /**
     * Switches of every event of a module.
     *
     * @return array<string,array{in_app:bool,email:bool,saved:bool}>
     */
    public static function settingsForModule(int $moduleId): array
    {
        $db   = \Config\Database::connect();
        $pref = self::defaultPref($moduleId);
        $rows = [];
        foreach ($db->table('module_notification_settings')->where('module_id', $moduleId)->get()->getResultArray() as $r) {
            $rows[(string) $r['event_key']] = $r;
        }

        $out = [];
        foreach (self::EVENTS as $key => $ev) {
            if (isset($rows[$key])) {
                $out[$key] = ['in_app' => (bool) $rows[$key]['in_app'], 'email' => (bool) $rows[$key]['email'], 'saved' => true];
            } else {
                $out[$key] = [
                    'in_app' => (bool) $ev['in_app'] && $pref !== 'email',
                    'email'  => (bool) $ev['email'] && $pref !== 'in_app',
                    'saved'  => false,
                ];
            }
        }

        return $out;
    }

    /**
     * Save the Notifications tab.
     *
     * @param array<string,array<string,mixed>> $events  event_key => ['in_app' => 1, 'email' => 1] (missing = off)
     */
    public static function saveSettings(int $moduleId, array $events, string $pref, int $byAccountId): bool
    {
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        $pref = in_array($pref, ['in_app', 'email', 'both'], true) ? $pref : 'both';

        $db->transBegin();
        try {
            foreach (array_keys(self::EVENTS) as $key) {
                $db->query(
                    'INSERT INTO module_notification_settings (module_id, event_key, in_app, email, updated_by, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE in_app = VALUES(in_app), email = VALUES(email), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)',
                    [$moduleId, $key, empty($events[$key]['in_app']) ? 0 : 1, empty($events[$key]['email']) ? 0 : 1, $byAccountId, $now]
                );
            }
            $db->query(
                'INSERT INTO module_notification_prefs (module_id, default_pref, updated_by, updated_at) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE default_pref = VALUES(default_pref), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)',
                [$moduleId, $pref, $byAccountId, $now]
            );

            if ($db->transStatus() === false) {
                $db->transRollback();

                return false;
            }
            $db->transCommit();

            return true;
        } catch (Throwable $e) {
            $db->transRollback();
            log_message('error', 'Notifier::saveSettings failed: {m}', ['m' => $e->getMessage()]);

            return false;
        }
    }

    // ------------------------------------------------------------------ sending

    /**
     * @param list<int|string|null> $accountIds
     * @param string|null           $url relative to the site, e.g. "approval-queue/show/5"
     */
    public static function send(string $moduleSlug, string $event, array $accountIds, string $title, string $body = '', ?string $url = null): void
    {
        try {
            if (! isset(self::EVENTS[$event])) {
                log_message('notice', 'Notifier: unknown event {e}', ['e' => $event]);

                return;
            }
            $db  = \Config\Database::connect();
            $mod = $db->table('rbac_modules')->select('id')->where('slug', $moduleSlug)->get()->getRowArray();
            $set = $mod
                ? (self::settingsForModule((int) $mod['id'])[$event] ?? ['in_app' => false, 'email' => false])
                : ['in_app' => (bool) self::EVENTS[$event]['in_app'], 'email' => (bool) self::EVENTS[$event]['email']];

            $ids = array_values(array_unique(array_filter(array_map('intval', $accountIds))));
            if (! $ids) {
                log_message('notice', 'Notifier: {e} for {m} has no recipient.', ['e' => $event, 'm' => $moduleSlug]);

                return;
            }
            if (! $set['in_app'] && ! $set['email']) {
                log_message('notice', 'Notifier: {e} for {m} is switched off (In-App and Email are both off).', ['e' => $event, 'm' => $moduleSlug]);

                return;
            }

            // active accounts only
            $users = $db->table('usr_account')->select('id, email_workspace')->whereIn('id', $ids)->where('account_status', 'active')->get()->getResultArray();
            if (! $users) {
                log_message('notice', 'Notifier: {e} for {m}: none of the recipients ({r}) is an active account.', ['e' => $event, 'm' => $moduleSlug, 'r' => implode(',', $ids)]);

                return;
            }

            $url = $url !== null ? self::relativeUrl($url) : null;

            if ($set['in_app']) {
                $now  = date('Y-m-d H:i:s');
                $rows = [];
                foreach ($users as $u) {
                    $rows[] = [
                        'account_id'  => (int) $u['id'],
                        'module_slug' => $moduleSlug,
                        'event_key'   => $event,
                        'title'       => mb_substr($title, 0, 190),
                        'body'        => $body !== '' ? mb_substr($body, 0, 500) : null,
                        'url'         => $url,
                        'is_read'     => 0,
                        'created_at'  => $now,
                    ];
                }
                $db->table('notifications')->insertBatch($rows);
            }

            if ($set['email']) {
                self::sendEmails($users, $title, $body, $url);
            }
        } catch (Throwable $e) {
            log_message('error', 'Notifier::send failed: {m}', ['m' => $e->getMessage()]);
        }
    }

    /** @param list<array<string,mixed>> $users */
    private static function sendEmails(array $users, string $title, string $body, ?string $url): void
    {
        $cfg = config('Email');
        if (trim((string) $cfg->fromEmail) === '') {
            log_message('error', 'Notifier: email is not set up (Config\\Email fromEmail is empty), so no email was sent.');

            return;
        }

        $mailer = \Config\Services::email();
        $html   = self::emailHtml($title, $body, $url);

        foreach ($users as $u) {
            $to = trim((string) ($u['email_workspace'] ?? ''));
            if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $mailer->clear(true);
            $mailer->setMailType('html');
            $mailer->setFrom((string) $cfg->fromEmail, (string) ($cfg->fromName ?: 'POS Workspace'));
            $mailer->setTo($to);
            $mailer->setSubject($title);
            $mailer->setMessage($html);
            if (! $mailer->send(false)) {
                log_message('error', 'Notifier email to {to} failed: {d}', ['to' => $to, 'd' => strip_tags($mailer->printDebugger(['headers']))]);
            }
        }
    }

    private static function emailHtml(string $title, string $body, ?string $url): string
    {
        $t    = esc($title);
        $b    = nl2br(esc($body));
        $link = $url ? '<p style="margin:18px 0 0"><a href="' . esc(base_url($url), 'attr') . '" style="background:#059669;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:600;display:inline-block">Open in POS Workspace</a></p>' : '';

        return '<div style="font-family:Segoe UI,Arial,sans-serif;background:#f1f5f9;padding:24px">'
            . '<div style="max-width:520px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:24px;color:#1e293b">'
            . '<p style="margin:0 0 4px;font-size:12px;color:#64748b">POS Workspace V3</p>'
            . '<h2 style="margin:0 0 12px;font-size:18px">' . $t . '</h2>'
            . '<p style="margin:0;font-size:14px;line-height:1.55;color:#334155">' . $b . '</p>'
            . $link
            . '</div><p style="text-align:center;font-size:11px;color:#94a3b8;margin-top:12px">You receive this because of the notification settings of this module.</p></div>';
    }

    // ------------------------------------------------------------------ the bell

    public static function unreadCount(int $accountId): int
    {
        if ($accountId < 1) {
            return 0;
        }

        return (int) \Config\Database::connect()->table('notifications')->where('account_id', $accountId)->where('is_read', 0)->countAllResults();
    }

    /**
     * Latest notifications in the shape the layout bell uses: title, message, time, url (+ id, event).
     * $tab = 'unread' (the badge counts only these) or 'read' (already opened, shown dimmed in the Read tab).
     *
     * @return list<array<string,mixed>>
     */
    public static function forBell(int $accountId, int $limit = 10, string $tab = 'unread'): array
    {
        if ($accountId < 1) {
            return [];
        }
        $b = \Config\Database::connect()->table('notifications')
            ->where('account_id', $accountId)->where('is_read', $tab === 'read' ? 1 : 0)
            ->orderBy('id', 'DESC')->limit($limit);
        if ($tab === 'read') {
            $b->where('created_at >=', date('Y-m-d H:i:s', strtotime('-30 days')));   // the Read tab keeps the last 30 days
        }
        $rows = $b->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'      => (int) $r['id'],
                'event'   => (string) $r['event_key'],
                'title'   => (string) $r['title'],
                'message' => (string) ($r['body'] ?? ''),
                'time'    => self::ago((string) $r['created_at']),
                'url'     => 'notifications/open/' . (int) $r['id'],
            ];
        }

        return $out;
    }

    /** "update" / "create" / ... as a verb for a sentence: "asked to update ...". */
    public static function verb(string $actionKey, string $actionLabel = ''): string
    {
        return ['create' => 'add', 'update' => 'update', 'delete' => 'delete', 'import' => 'import', 'export' => 'export'][strtolower($actionKey)]
            ?? (trim($actionLabel) !== '' ? strtolower(trim($actionLabel)) : 'change');
    }

    public static function ago(string $ts): string
    {
        $t = strtotime($ts);
        if (! $t) {
            return '';
        }
        $d = max(0, time() - $t);
        if ($d < 60)    { return 'Just now'; }
        if ($d < 3600)  { return floor($d / 60) . ' min ago'; }
        if ($d < 86400) { return floor($d / 3600) . ' hr ago'; }
        if ($d < 172800) { return 'Yesterday'; }

        return date('M j, Y', $t);
    }

    // ------------------------------------------------------------------ helpers for the callers

    public static function nameOf(int $accountId): string
    {
        $row = \Config\Database::connect()->table('usr_account ua')
            ->select('ua.email_workspace, ua.username')
            ->select("TRIM(CONCAT(COALESCE(ui.first_name, ''), ' ', COALESCE(ui.last_name, ''))) AS name", false)
            ->join('usr_account_info ui', 'ui.account_id = ua.id', 'left')
            ->where('ua.id', $accountId)->get()->getRowArray();
        if (! $row) {
            return 'Someone';
        }
        foreach (['name', 'username'] as $k) {
            if (trim((string) ($row[$k] ?? '')) !== '') {
                return trim((string) $row[$k]);
            }
        }

        return ucfirst(explode('@', (string) ($row['email_workspace'] ?? 'user'))[0]);
    }

    /** Page of a module (rbac_modules.url or similar), used as the link of notifications for people without Approval Queue access. */
    public static function moduleUrl(string $slug): string
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('rbac_modules')) {
            return 'dashboard';
        }
        $fields = $db->getFieldNames('rbac_modules');
        foreach (['url', 'route', 'path', 'page_url', 'link'] as $c) {
            if (in_array($c, $fields, true)) {
                $row = $db->table('rbac_modules')->select($c)->where('slug', $slug)->get()->getRowArray();
                $v   = trim((string) ($row[$c] ?? ''), " /\t\n\r");
                if ($v !== '') {
                    return $v;
                }
            }
        }

        return 'dashboard';
    }

    /** Keep links inside the site: "/foo", "foo" and "http://host/foo" all become "foo". */
    private static function relativeUrl(string $url): string
    {
        $base = rtrim(base_url(), '/');
        if (str_starts_with($url, $base)) {
            $url = substr($url, strlen($base));
        }
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url) || str_starts_with($url, '//')) {
            return 'dashboard';
        }

        return ltrim($url, '/');
    }
}