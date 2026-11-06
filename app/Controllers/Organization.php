<?php

namespace App\Controllers;

use App\Libraries\ActivityLog;
use App\Libraries\Rbac;
use App\Models\Rbac_model;

/**
 * Organization (slug organization, URL organization): global Position and Department masterlists.
 *
 * RBAC actions of this module:  read (open the page)  create  update  toggle_status (activate / deactivate)
 * Positions and departments are never deleted, only deactivated (history stays valid).
 * A position / department that an ACTIVE assignment still uses cannot be deactivated, and a position in use
 * cannot change its level (it would silently change who may handle whom).
 * Super Admin (role_id 1) passes every check.
 */
class Organization extends BaseController
{
    private const SLUG    = 'organization';
    private const ACTIONS = ['read', 'create', 'update', 'toggle_status'];

    // ------------------------------------------------------------------ page

    public function index()
    {
        if ($deny = $this->orgDeny('read', true)) {
            return $deny;
        }
        $db = \Config\Database::connect();

        $posUse = $this->usage($db, 'position_id');
        $depUse = $this->usage($db, 'department_id');

        $positions = $db->table('org_positions')->orderBy('level', 'ASC')->orderBy('name', 'ASC')->get()->getResultArray();
        foreach ($positions as &$p) {
            $p['in_use'] = $posUse[(int) $p['id']] ?? 0;
        }
        unset($p);

        $departments = $db->table('org_departments')->orderBy('name', 'ASC')->get()->getResultArray();
        foreach ($departments as &$d) {
            $d['in_use'] = $depUse[(int) $d['id']] ?? 0;
        }
        unset($d);

        return view('organization/index', $this->baseData() + [
            'positions'   => $positions,
            'departments' => $departments,
        ]);
    }

    // ------------------------------------------------------------------ positions

    /** POST organization/positions/save  and  organization/positions/(:num)/save */
    public function savePosition($id = null)
    {
        $id = (int) $id;
        if ($deny = $this->orgDeny($id ? 'update' : 'create', false)) {
            return $deny;
        }
        $db   = \Config\Database::connect();
        $name = mb_substr(trim((string) $this->request->getPost('name')), 0, 100);
        $lvl  = trim((string) $this->request->getPost('level'));
        $desc = mb_substr(trim((string) $this->request->getPost('description')), 0, 255);

        if ($name === '') {
            return $this->back('error', 'Enter the position name.', 'positions');
        }
        if (! ctype_digit($lvl) || (int) $lvl < 1 || (int) $lvl > 99) {
            return $this->back('error', 'The level must be a whole number from 1 (highest) to 99.', 'positions');
        }
        $lvl = (int) $lvl;

        if ($db->table('org_positions')->where('name', $name)->where('id !=', $id)->countAllResults() > 0) {
            return $this->back('error', 'A position called "' . $name . '" already exists.', 'positions');
        }

        $now = date('Y-m-d H:i:s');
        if ($id) {
            $row = $db->table('org_positions')->where('id', $id)->get()->getRowArray();
            if (! $row) {
                return $this->back('error', 'Position not found.', 'positions');
            }
            if ((int) $row['level'] !== $lvl && ($this->usage($db, 'position_id')[$id] ?? 0) > 0) {
                return $this->back('error', 'The level of "' . $row['name'] . '" cannot change while active employees hold this position.', 'positions');
            }
            $db->table('org_positions')->where('id', $id)->update(['name' => $name, 'level' => $lvl, 'description' => $desc !== '' ? $desc : null, 'updated_at' => $now]);
            ActivityLog::record(self::SLUG, 'update', 'position', $id, 'Updated the position "' . $name . '"', ['changes' => ActivityLog::diff($row, ['name' => $name, 'level' => $lvl, 'description' => $desc])]);

            return $this->back('success', 'Position updated.', 'positions');
        }

        $db->table('org_positions')->insert(['name' => $name, 'level' => $lvl, 'description' => $desc !== '' ? $desc : null, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        ActivityLog::record(self::SLUG, 'create', 'position', $db->insertID(), 'Added the position "' . $name . '" (level ' . $lvl . ')', []);

        return $this->back('success', 'Position added.', 'positions');
    }

    /** POST organization/positions/(:num)/status */
    public function setPositionStatus($id = null)
    {
        return $this->changeStatus('org_positions', 'position_id', (int) $id, 'positions', 'position');
    }

    // ------------------------------------------------------------------ departments

    /** POST organization/departments/save  and  organization/departments/(:num)/save */
    public function saveDepartment($id = null)
    {
        $id = (int) $id;
        if ($deny = $this->orgDeny($id ? 'update' : 'create', false)) {
            return $deny;
        }
        $db   = \Config\Database::connect();
        $name = mb_substr(trim((string) $this->request->getPost('name')), 0, 100);
        $desc = mb_substr(trim((string) $this->request->getPost('description')), 0, 255);

        if ($name === '') {
            return $this->back('error', 'Enter the department name.', 'departments');
        }
        if ($db->table('org_departments')->where('name', $name)->where('id !=', $id)->countAllResults() > 0) {
            return $this->back('error', 'A department called "' . $name . '" already exists.', 'departments');
        }

        $now = date('Y-m-d H:i:s');
        if ($id) {
            $row = $db->table('org_departments')->where('id', $id)->get()->getRowArray();
            if (! $row) {
                return $this->back('error', 'Department not found.', 'departments');
            }
            $db->table('org_departments')->where('id', $id)->update(['name' => $name, 'description' => $desc !== '' ? $desc : null, 'updated_at' => $now]);
            ActivityLog::record(self::SLUG, 'update', 'department', $id, 'Updated the department "' . $name . '"', ['changes' => ActivityLog::diff($row, ['name' => $name, 'description' => $desc])]);

            return $this->back('success', 'Department updated.', 'departments');
        }

        $db->table('org_departments')->insert(['name' => $name, 'description' => $desc !== '' ? $desc : null, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        ActivityLog::record(self::SLUG, 'create', 'department', $db->insertID(), 'Added the department "' . $name . '"', []);

        return $this->back('success', 'Department added.', 'departments');
    }

    /** POST organization/departments/(:num)/status */
    public function setDepartmentStatus($id = null)
    {
        return $this->changeStatus('org_departments', 'department_id', (int) $id, 'departments', 'department');
    }

    // ------------------------------------------------------------------ helpers

    private function changeStatus(string $table, string $usageCol, int $id, string $tab, string $label)
    {
        if ($deny = $this->orgDeny('toggle_status', false)) {
            return $deny;
        }
        $status = (string) $this->request->getPost('status');
        if (! in_array($status, ['active', 'inactive'], true)) {
            return $this->back('error', 'Invalid status.', $tab);
        }
        $db  = \Config\Database::connect();
        $row = $db->table($table)->where('id', $id)->get()->getRowArray();
        if (! $row) {
            return $this->back('error', ucfirst($label) . ' not found.', $tab);
        }
        if ($status === 'inactive') {
            $n = $this->usage($db, $usageCol)[$id] ?? 0;
            if ($n > 0) {
                return $this->back('error', '"' . $row['name'] . '" is still used by ' . $n . ' active employee(s). Move them first, then deactivate.', $tab);
            }
        }
        $db->table($table)->where('id', $id)->update(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
        ActivityLog::record(self::SLUG, $status === 'active' ? 'activate' : 'deactivate', $label, $id, ucfirst($status === 'active' ? 'Activated' : 'Deactivated') . ' the ' . $label . ' "' . $row['name'] . '"', []);

        return $this->back('success', '"' . $row['name'] . '" is now ' . $status . '.', $tab);
    }

    /** @return array<int,int> id => number of ACTIVE assignments using it */
    private function usage($db, string $col): array
    {
        if (! $db->tableExists('org_user_assignments')) {
            return [];
        }
        $out = [];
        foreach ($db->table('org_user_assignments')->select($col . ' AS k, COUNT(*) AS n', false)->where('status', 'active')->groupBy($col)->get()->getResultArray() as $r) {
            $out[(int) $r['k']] = (int) $r['n'];
        }

        return $out;
    }

    private function back(string $kind, string $message, string $tab)
    {
        return redirect()->to(base_url('organization'))->with($kind, $message)->with('tab', $tab);
    }

    private function baseData(): array
    {
        $userId = session()->get('account_id') ?? session()->get('id') ?? session()->get('user_id');
        $roleId = session()->get('user_role_id') ?? session()->get('role_id');

        return [
            'title'           => 'Organization',
            'pageTitle'       => 'Organization',
            'filteredModules' => (new Rbac_model())->get_user_modules($userId, $roleId),
            'can'             => $this->abilities(),
            'breadcrumbs'     => [['label' => 'Home', 'url' => 'dashboard'], ['label' => 'Organization']],
        ];
    }

    private function allowed(string $action): bool
    {
        $role = (int) (session()->get('user_role_id') ?? session()->get('role_id'));

        return $role === Rbac::SUPER_ADMIN_ROLE || Rbac::can(self::SLUG, $action);
    }

    /** @return array<string,bool> */
    private function abilities(): array
    {
        $out = [];
        foreach (self::ACTIONS as $a) {
            $out[$a] = $this->allowed($a);
        }

        return $out;
    }

    private function orgDeny(string $action, bool $page)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        if (! $this->allowed($action)) {
            return $page
                ? redirect()->to(base_url('dashboard'))->with('error', 'You do not have permission to open Organization.')
                : redirect()->to(base_url('organization'))->with('error', 'You do not have permission to do this (Organization: ' . $action . ').');
        }

        return null;
    }
}
