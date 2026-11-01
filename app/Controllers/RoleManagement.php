<?php

namespace App\Controllers;

use App\Libraries\Rbac;
use App\Models\Rbac_model;

class RoleManagement extends BaseController
{
    public function index()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        if ($deny = $this->denyUnless('read', true)) {
            return $deny;
        }

        $db = \Config\Database::connect();
        $roles = $db->table('rbac_roles')->orderBy('id', 'ASC')->get()->getResultArray();

        foreach ($roles as &$role) {
            $role['user_count'] = $db->table('usr_account')->where('user_role_id', $role['id'])->countAllResults();
        }
        unset($role);

        $rbacModel = new Rbac_model();
        $userId    = session()->get('account_id') ?? session()->get('id');
        $roleId    = session()->get('user_role_id');

        $data = [
            'title'           => 'Role Management',
            'pageTitle'       => 'Role Management',
            'filteredModules' => $rbacModel->get_user_modules($userId, $roleId),
            'roles'           => $roles,
            'can'             => $this->abilities(),
            'breadcrumbs'     => [
                ['label' => 'Home', 'url' => 'dashboard'],
                ['label' => 'Role Management'],
            ],
        ];

        return view('role_management/index', $data);
    }

    public function configure($id = null)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        $actionCheck = $id ? 'update' : 'create';
        if ($deny = $this->denyUnless($actionCheck, true)) {
            return $deny;
        }

        $db = \Config\Database::connect();
        $role = null;
        $assignedPermissionIds = [];

        if ($id) {
            $role = $db->table('rbac_roles')->where('id', (int) $id)->get()->getRowArray();
            if (! $role) {
                return redirect()->to(base_url('roles'))->with('error', 'Role not found.');
            }

            $assigned = $db->table('rbac_role_permissions')->where('role_id', $id)->get()->getResultArray();
            $assignedPermissionIds = array_column($assigned, 'permission_id');
        }

        $modules = $db->table('rbac_modules')->select('id, name, icon')->where('is_category', 0)->orderBy('id', 'ASC')->get()->getResultArray();
        
        foreach ($modules as &$mod) {
            $mod['permissions'] = $db->table('rbac_permissions')
                ->select('rbac_permissions.id as pid, rbac_actions.name as action_name, rbac_actions.action_key')
                ->join('rbac_actions', 'rbac_actions.id = rbac_permissions.action_id', 'inner')
                ->where('rbac_permissions.module_id', $mod['id'])
                ->get()
                ->getResultArray();
        }
        unset($mod);

        $rbacModel = new Rbac_model();
        $userId    = session()->get('account_id') ?? session()->get('id');
        $roleId    = session()->get('user_role_id');

        $data = [
            'title'                 => $id ? 'Configure Role - ' . $role['name'] : 'Add New Role',
            'pageTitle'             => 'Role Management / Configure',
            'filteredModules'       => $rbacModel->get_user_modules($userId, $roleId),
            'role'                  => $role,
            'modules'               => $modules,
            'assignedPermissionIds' => $assignedPermissionIds,
            'can'                   => $this->abilities(),
            'breadcrumbs'           => [
                ['label' => 'Home', 'url' => 'dashboard'],
                ['label' => 'Role Management', 'url' => 'roles'],
                ['label' => 'Configure Role'],
            ],
        ];

        return view('role_management/configure', $data);
    }

    public function save($id = null)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        $actionCheck = $id ? 'update' : 'create';
        if ($deny = $this->denyUnless($actionCheck)) {
            return $deny;
        }

        $db = \Config\Database::connect();
        $name = trim((string) $this->request->getPost('name'));
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name));
        $description = trim((string) $this->request->getPost('description'));

        $roleData = [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $description,
        ];

        if ($id) {
            $db->table('rbac_roles')->where('id', (int) $id)->update($roleData);
            $roleId = (int) $id;
            $msg = 'Role updated successfully.';
        } else {
            $db->table('rbac_roles')->insert($roleData);
            $roleId = $db->insertID();
            $msg = 'Role added successfully.';
        }

        $permissions = $this->request->getPost('permissions') ?? [];
        
        $db->table('rbac_role_permissions')->where('role_id', $roleId)->delete();

        if (!empty($permissions)) {
            $batchData = [];
            foreach ($permissions as $permId) {
                $batchData[] = [
                    'role_id'       => $roleId,
                    'permission_id' => (int) $permId,
                ];
            }
            $db->table('rbac_role_permissions')->insertBatch($batchData);
        }

        return redirect()->to(base_url('roles'))->with('success', $msg);
    }

    private function denyUnless(string $action, bool $isPage = false)
    {
        if (Rbac::can('role_management', $action)) {
            return null;
        }
        $msg = 'You do not have permission to do this (Role Management: ' . $action . ').';

        return $isPage
            ? redirect()->to(base_url('dashboard'))->with('error', $msg)
            : redirect()->back()->with('error', $msg);
    }

    private function abilities(): array
    {
        return Rbac::map('role_management', ['create', 'read', 'update', 'delete']);
    }
}