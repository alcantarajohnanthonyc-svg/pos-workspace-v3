<?php

namespace App\Models;

use App\Libraries\Rbac;
use CodeIgniter\Model;

class Rbac_model extends Model
{
    protected $table      = 'rbac_modules';
    protected $primaryKey = 'id';

    /**
     * Sidebar modules for a user.
     *  - disabled modules (status = 'disabled') are hidden for everybody
     *  - Super Admin sees every enabled module
     *  - everybody else sees category containers + modules where the READ permission is granted
     *    (same rule as Rbac::can, so the sidebar and the page checks never disagree)
     */
    public function get_user_modules($user_id, $role_id)
    {
        $cols = 'm.id, m.name, m.slug, m.icon, m.badge, m.url, m.parent_id, m.is_category, m.short_order';

        if ((int) $role_id === Rbac::SUPER_ADMIN_ROLE) {
            $modules = $this->db->table('rbac_modules m')
                ->select($cols)
                ->where('m.status', 'enabled')
                ->orderBy('m.short_order', 'ASC')->orderBy('m.id', 'ASC')
                ->get()->getResultArray();
        } else {
            $ids = Rbac::permissionIds($user_id, (int) $role_id);

            $builder = $this->db->table('rbac_modules m')
                ->select($cols)->distinct()
                ->join('rbac_permissions p', 'p.module_id = m.id', 'left')
                ->join('rbac_actions a', "a.id = p.action_id AND a.action_key = 'read'", 'left')
                ->where('m.status', 'enabled')
                ->groupStart()
                    ->where('m.is_category', 1);
            if ($ids) {
                $builder->orGroupStart()->whereIn('p.id', $ids)->where('a.id IS NOT NULL', null, false)->groupEnd();
            }
            $builder->groupEnd()
                ->orderBy('m.short_order', 'ASC')->orderBy('m.id', 'ASC');

            $modules = $builder->get()->getResultArray();
        }

        return $this->buildModuleTree($modules);
    }

    private function buildModuleTree(array $modules)
    {
        $map = [];
        foreach ($modules as $m) {
            $m['children'] = [];
            $map[$m['id']] = $m;
        }

        $tree = [];
        foreach ($map as $id => $m) {
            if (! empty($m['parent_id']) && isset($map[$m['parent_id']])) {
                $map[$m['parent_id']]['children'][] = &$map[$id];
            } else {
                $tree[$id] = &$map[$id];
            }
        }

        // Drop category containers that ended up empty
        $filtered = [];
        foreach ($tree as $node) {
            if (! empty($node['is_category']) && (int) $node['is_category'] === 1) {
                if (! empty($node['children'])) {
                    $filtered[] = $node;
                }
            } else {
                $filtered[] = $node;
            }
        }

        return $filtered;
    }
}