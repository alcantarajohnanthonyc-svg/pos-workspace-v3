<?php

namespace App\Libraries;

use Throwable;

/**
 * User activity log (table usr_activity_logs). Writing a log NEVER breaks the action: every failure is only written to the log file.
 *
 *   ActivityLog::record('store_details', 'update', 'store', 12, 'Updated store "X"', ['changes' => ActivityLog::diff($before, $after)]);
 *
 * Two kinds of entries:
 *   source = app      written by a module with the details (before / after, counts, reference no.)
 *   source = request  written automatically by ActivityLogFilter for every other POST / PUT / PATCH / DELETE,
 *                     so no action is ever missing even in a module that has no explicit line yet.
 */
class ActivityLog
{
    /** an "app" entry was written during this request: the automatic one is skipped (no double lines) */
    private static bool $written = false;

    public static function wasWritten(): bool
    {
        return self::$written;
    }

    /**
     * @param int|string|null $recordId
     * @param array<string,mixed> $details
     */
    public static function record(string $module, string $action, ?string $recordType = null, $recordId = null, string $description = '', array $details = [], ?int $accountId = null): void
    {
        self::insert($module, $action, $recordType, $recordId, $description, $details, $accountId, 'app', null);
    }

    /** Written by ActivityLogFilter. */
    public static function request(string $module, string $action, ?string $recordId, string $description, array $details, int $status): void
    {
        self::insert($module, $action, null, $recordId, $description, $details, null, 'request', $status);
    }

    /** Fields that differ: field => [before, after]. Only the keys of $after are compared. */
    public static function diff(array $before, array $after): array
    {
        $out = [];
        foreach ($after as $k => $v) {
            $old = $before[$k] ?? null;
            if ((string) (is_scalar($old) || $old === null ? $old : json_encode($old)) !== (string) (is_scalar($v) || $v === null ? $v : json_encode($v))) {
                $out[$k] = [self::clean($old), self::clean($v)];
            }
        }

        return $out;
    }

    /** Secrets are hidden, long text and long lists are cut. */
    public static function clean($v, int $depth = 0)
    {
        if (is_array($v)) {
            if ($depth >= 4) {
                return '[…]';
            }
            $out = [];
            $n   = 0;
            foreach ($v as $k => $x) {
                if (++$n > 50) {
                    $out['…'] = (count($v) - 50) . ' more';
                    break;
                }
                $out[$k] = is_string($k) && preg_match('/pass|pwd|secret|token|csrf|authorization|api[_-]?key/i', $k) ? '[hidden]' : self::clean($x, $depth + 1);
            }

            return $out;
        }
        if (is_string($v) && mb_strlen($v) > 300) {
            return mb_substr($v, 0, 300) . '…';
        }

        return $v;
    }

    private static function insert(string $module, string $action, ?string $recordType, $recordId, string $description, array $details, ?int $accountId, string $source, ?int $status): void
    {
        try {
            $db = \Config\Database::connect();
            if (! $db->tableExists('usr_activity_logs')) {
                return;
            }
            $req   = service('request');
            $uid   = $accountId ?? (int) (session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id'));
            $role  = session()->get('user_role_id') ?? session()->get('role_id');
            $json  = $details ? (string) json_encode(self::clean($details), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : null;
            if ($json !== null && strlen($json) > 60000) {
                $json = substr($json, 0, 60000);
            }
            $db->table('usr_activity_logs')->insert([
                'account_id'  => $uid > 0 ? $uid : null,
                'role_id'     => $role !== null ? (int) $role : null,
                'module_slug' => mb_substr($module, 0, 80),
                'action'      => mb_substr($action, 0, 60),
                'record_type' => $recordType !== null ? mb_substr($recordType, 0, 60) : null,
                'record_id'   => $recordId !== null && $recordId !== '' ? mb_substr((string) $recordId, 0, 40) : null,
                'description' => $description !== '' ? mb_substr($description, 0, 255) : null,
                'details'     => $json,
                'method'      => $req ? strtoupper((string) $req->getMethod()) : null,
                'url'         => $req ? mb_substr('/' . trim((string) $req->getUri()->getPath(), '/'), 0, 255) : null,
                'status_code' => $status,
                'ip_address'  => $req ? mb_substr((string) $req->getIPAddress(), 0, 45) : null,
                'user_agent'  => $req ? mb_substr((string) $req->getUserAgent(), 0, 255) : null,
                'source'      => $source,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
            if ($source === 'app') {
                self::$written = true;
            }
        } catch (Throwable $e) {
            log_message('error', 'ActivityLog failed: {m}', ['m' => $e->getMessage()]);
        }
    }
}