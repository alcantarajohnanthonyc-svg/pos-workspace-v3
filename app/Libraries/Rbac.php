<?php

namespace App\Libraries;

/**
 * Central permission check for every module.
 *
 *   Rbac::can('account_management', 'update')       // slug from rbac_modules, key from rbac_actions
 *   Rbac::can('module_setting', 'toggle_status')
 *
 * Effective permissions of an account (same rule the Module Access tab uses):
 *   1. the account's own rows in rbac_user_permissions, if it has any
 *   2. otherwise the rows of its role in rbac_role_permissions
 * A module whose rbac_modules.status = 'disabled' grants nothing to anybody.
 * Super Admin (role id 1) is always allowed, so nobody can lock the system out.
 *
 * The role is read from the database (not the session), so a role change applies at once.
 */
class Rbac
{
    public const SUPER_ADMIN_ROLE = 1;

    /** @var array<int|string, array<string, true>> */
    private static array $cache = [];

    public static function can(string $moduleSlug, string $actionKey, $accountId = null): bool
    {
        $accountId = $accountId ?? (session()->get('account_id') ?? session()->get('id'));
        if (! $accountId || ! session()->get('isLoggedIn')) {
            return false;
        }

        $db      = \Config\Database::connect();
        $account = $db->table('usr_account')->select('user_role_id, account_status')->where('id', $accountId)->get()->getRowArray();
        if (! $account || strtolower((string) $account['account_status']) !== 'active') {
            return false;
        }
        // A disabled module is off for EVERYBODY (even Super Admin). Module Setting can never be switched off.
        if ($moduleSlug !== 'module_setting') {
            $mod = $db->table('rbac_modules')->select('status')->where('slug', $moduleSlug)->get()->getRowArray();
            if ($mod && strtolower((string) $mod['status']) === 'disabled') {
                return false;
            }
        }

        if ((int) $account['user_role_id'] === self::SUPER_ADMIN_ROLE) {
            return true;
        }

        $set = self::permissionSet($db, $accountId, (int) $account['user_role_id']);

        return isset($set[self::norm($moduleSlug) . ':' . self::norm($actionKey)]);
    }

    /** Several checks at once: Rbac::map('account_management', ['create','update']) => ['create'=>bool, ...] */
    public static function map(string $moduleSlug, array $actionKeys): array
    {
        $out = [];
        foreach ($actionKeys as $k) {
            $out[$k] = self::can($moduleSlug, $k);
        }

        return $out;
    }

    /** rbac_permissions.id the account effectively has (own rows, else its role's rows). */
    public static function permissionIds($accountId, int $roleId): array
    {
        return self::grantedPermissionIds(\Config\Database::connect(), $accountId, $roleId);
    }

    private static function norm(string $v): string
    {
        return trim(strtolower(preg_replace('/[^a-z0-9]+/i', '_', $v)), '_');
    }

    /** @return array<string, true>  "slug:action_key" => true */
    private static function permissionSet($db, $accountId, int $roleId): array
    {
        if (isset(self::$cache[$accountId])) {
            return self::$cache[$accountId];
        }

        $ids = self::grantedPermissionIds($db, $accountId, $roleId);
        $set = [];

        if ($ids) {
            $builder = $db->table('rbac_permissions')
                ->select('rbac_modules.slug, rbac_actions.action_key')
                ->join('rbac_modules', 'rbac_modules.id = rbac_permissions.module_id', 'inner')
                ->join('rbac_actions', 'rbac_actions.id = rbac_permissions.action_id', 'inner')
                ->whereIn('rbac_permissions.id', $ids);

            if ($db->fieldExists('status', 'rbac_modules')) {
                $builder->where('rbac_modules.status !=', 'disabled');
            }

            foreach ($builder->get()->getResultArray() as $r) {
                $set[self::norm((string) $r['slug']) . ':' . self::norm((string) $r['action_key'])] = true;
            }
        }

        return self::$cache[$accountId] = $set;
    }

    /** @return int[] rbac_permissions.id the account effectively has */
    private static function grantedPermissionIds($db, $accountId, int $roleId): array
    {
        if ($db->tableExists('rbac_user_permissions')) {
            $f       = $db->getFieldNames('rbac_user_permissions');
            $userCol = in_array('account_id', $f, true) ? 'account_id' : (in_array('user_id', $f, true) ? 'user_id' : null);

            if ($userCol !== null) {
                if (in_array('permission_id', $f, true)) {
                    $rows = $db->table('rbac_user_permissions')->select('permission_id AS id')->where($userCol, $accountId)->get()->getResultArray();
                } elseif (in_array('module_id', $f, true) && in_array('action_id', $f, true)) {
                    $rows = $db->table('rbac_user_permissions up')->select('p.id')
                        ->join('rbac_permissions p', 'p.module_id = up.module_id AND p.action_id = up.action_id', 'inner')
                        ->where('up.' . $userCol, $accountId)->get()->getResultArray();
                } else {
                    $rows = [];
                }

                if ($rows) {
                    return array_map('intval', array_column($rows, 'id'));
                }
            }
        }

        if ($db->tableExists('rbac_role_permissions')) {
            $rows = $db->table('rbac_role_permissions')->select('permission_id')->where('role_id', $roleId)->get()->getResultArray();

            return array_map('intval', array_column($rows, 'permission_id'));
        }

        return [];
    }
}