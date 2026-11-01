<?php

namespace App\Controllers;

use App\Libraries\Rbac;
use App\Models\Rbac_model;

/**
 * Module Settings: manage rows of rbac_modules and their permissions (rbac_permissions).
 *
 * RBAC (module slug "module_setting"):
 *   read           list + open a module
 *   create         Add Module
 *   update         edit details, add permissions
 *   delete         remove a permission from a module
 *   toggle_status  enable / disable a module for ALL users
 *   module_admin   (scoped) open Module Settings ONLY for modules where the user is assigned
 *                  in rbac_module_admins; can manage that module's approval settings
 */
class ModuleSettings extends BaseController
{
    // ------------------------------------------------------------------ pages

    public function index()
    {
        if ($deny = $this->guardEntry()) {
            return $deny;
        }

        $db = \Config\Database::connect();

        $builder = $db->table('rbac_modules m')
            ->select('m.*, pm.name AS parent_name')
            ->join('rbac_modules pm', 'pm.id = m.parent_id', 'left');

        // Module administrators (no full "read") only see the modules they administer
        if (! Rbac::can('module_setting', 'read')) {
            $mine = $this->myModuleIds($db);
            $mine ? $builder->whereIn('m.id', $mine) : $builder->where('m.id', 0);
        }

        $modules = $builder->orderBy('m.short_order', 'ASC')->orderBy('m.id', 'ASC')
            ->get()->getResultArray();

        $byModule = [];
        $rows = $db->table('rbac_permissions p')
            ->select('p.module_id, a.name AS action_name, a.action_key')
            ->join('rbac_actions a', 'a.id = p.action_id', 'inner')
            ->orderBy('a.id', 'ASC')
            ->get()->getResultArray();
        foreach ($rows as $r) {
            $byModule[$r['module_id']][] = $r;
        }
        foreach ($modules as &$mod) {
            $mod['actions'] = $byModule[$mod['id']] ?? [];
        }
        unset($mod);

        return view('module_settings/index', $this->baseData('Module Settings', 'Module Settings', [
            ['label' => 'Home', 'url' => 'dashboard'],
            ['label' => 'Module Settings'],
        ]) + [
            'modules'          => $modules,
            'parentModules'    => $this->categoryOptions($db),
            // "module_admin" belongs only to the module_setting module, so hide it from Add Module
            'availableActions' => array_values(array_filter(
                $db->table('rbac_actions')->orderBy('id', 'ASC')->get()->getResultArray(),
                static fn ($a) => $a['action_key'] !== 'module_admin'
            )),
        ]);
    }

    public function configure($id = null)
    {
        if ($deny = $this->guardEntry()) {
            return $deny;
        }

        $db     = \Config\Database::connect();
        $module = $db->table('rbac_modules m')
            ->select('m.*, pm.name AS parent_name')
            ->join('rbac_modules pm', 'pm.id = m.parent_id', 'left')
            ->where('m.id', (int) $id)->get()->getRowArray();
        if (! $module) {
            return redirect()->to(base_url('modules'))->with('error', 'Module not found.');
        }

        // Full access (read) can open any module; a module administrator only the ones assigned to them
        $isMine = $this->isModuleAdmin($db, (int) $module['id']);
        if (! Rbac::can('module_setting', 'read') && ! $isMine) {
            return redirect()->to(base_url('modules'))->with('error', 'You are not an administrator of this module.');
        }

        // Permissions of this module + how many roles / accounts hold each one
        $permissions = $db->table('rbac_permissions p')
            ->select('p.id AS pid, p.slug AS permission_slug, p.requires_approval, a.id AS action_id, a.name AS action_name, a.action_key, a.description')
            ->join('rbac_actions a', 'a.id = p.action_id', 'inner')
            ->where('p.module_id', $module['id'])
            ->orderBy('a.id', 'ASC')
            ->get()->getResultArray();

        $pids = array_map('intval', array_column($permissions, 'pid'));
        $roleCounts = $userCounts = [];
        if ($pids) {
            foreach ($db->table('rbac_role_permissions')->select('permission_id, COUNT(*) AS c')->whereIn('permission_id', $pids)->groupBy('permission_id')->get()->getResultArray() as $r) {
                $roleCounts[$r['permission_id']] = (int) $r['c'];
            }
            foreach ($db->table('rbac_user_permissions')->select('permission_id, COUNT(*) AS c')->whereIn('permission_id', $pids)->groupBy('permission_id')->get()->getResultArray() as $r) {
                $userCounts[$r['permission_id']] = (int) $r['c'];
            }
        }
        foreach ($permissions as &$p) {
            $p['role_count'] = $roleCounts[$p['pid']] ?? 0;
            $p['user_count'] = $userCounts[$p['pid']] ?? 0;
        }
        unset($p);

        $assigned = array_map('intval', array_column($permissions, 'action_id'));
        $availableActions = $db->table('rbac_actions')->orderBy('id', 'ASC')->get()->getResultArray();
        $availableActions = array_values(array_filter($availableActions, static fn ($a) => ! in_array((int) $a['id'], $assigned, true)
            && ($a['action_key'] !== 'module_admin' || $module['slug'] === 'module_setting')));

        $children = $db->table('rbac_modules')->select('id, name')->where('parent_id', $module['id'])->orderBy('short_order')->orderBy('id')->get()->getResultArray();

        // Assigned administrators + ALL accounts for the "Add administrators" popup
        $assignedAdminIds = array_map('intval', array_column(
            $db->table('rbac_module_admins')->select('account_id')->where('module_id', $module['id'])->get()->getResultArray(),
            'account_id'
        ));

        $data = $this->baseData('Configure Module - ' . $module['name'], 'Module Settings / Configure', [
            ['label' => 'Home', 'url' => 'dashboard'],
            ['label' => 'Module Settings', 'url' => 'modules'],
            ['label' => $module['name']],
        ]);
        // manage_admins : who may assign administrators (full "update")
        // manage_approval: full "update", OR a module administrator of THIS module with the module_admin action
        $data['can']['manage_admins']   = ! empty($data['can']['update']);
        $data['can']['manage_approval'] = $data['can']['manage_admins'] || ($isMine && ! empty($data['can']['module_admin']));

        return view('module_settings/configure', $data + [
            'module'           => $module,
            'permissions'      => $permissions,
            'availableActions' => $availableActions,
            'children'         => $children,
            'parentModules'    => $this->categoryOptions($db, (int) $module['id']),
            'accounts'         => $this->accountOptions($db),
            'assignedAdminIds' => $assignedAdminIds,
        ]);
    }

    // ---------------------------------------------------------------- actions

    /** POST modules/create */
    public function create()
    {
        if ($deny = $this->guardPost('create')) {
            return $deny;
        }

        $db = \Config\Database::connect();
        [$data, $err] = $this->collectFields($db, null);
        if ($err) {
            return redirect()->back()->with('error', $err);
        }

        $data['status'] = 'enabled';
        $actionIds = $this->selectedActionIds();
        if ($actionIds === null) {
            $this->deleteLocalIcon($data['icon'] ?? null);

            return redirect()->back()->with('error', 'Invalid action selection.');
        }
        if ((int) $data['is_category'] === 1 && $actionIds !== []) {
            $this->deleteLocalIcon($data['icon'] ?? null);

            return redirect()->back()->with('error', 'Category containers cannot have actions.');
        }

        $actions = [];
        if ($actionIds !== []) {
            $actions = $db->table('rbac_actions')->whereIn('id', $actionIds)->get()->getResultArray();
            if (count($actions) !== count($actionIds)) {
                $this->deleteLocalIcon($data['icon'] ?? null);

                return redirect()->back()->with('error', 'One or more selected actions are unavailable. Please try again.');
            }
        }

        try {
            $db->transBegin();

            if (! $db->table('rbac_modules')->insert($data)) {
                $error = $db->error();
                throw new \RuntimeException((string) ($error['message'] ?? 'Module insert failed.'));
            }

            $moduleId = (int) $db->insertID();
            if ($moduleId < 1) {
                throw new \RuntimeException('The new module ID could not be read.');
            }

            foreach ($actions as $action) {
                if (! $db->table('rbac_permissions')->insert([
                    'module_id' => $moduleId,
                    'action_id' => (int) $action['id'],
                    'slug'      => $data['slug'] . '_' . $action['action_key'],
                ])) {
                    $error = $db->error();
                    throw new \RuntimeException((string) ($error['message'] ?? 'Permission insert failed.'));
                }
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('The module transaction failed.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            $this->deleteLocalIcon($data['icon'] ?? null);
            log_message('error', 'Module creation failed: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Could not create the module and its selected actions: ' . mb_substr($e->getMessage(), 0, 200));
        }

        return redirect()->to(base_url('modules/configure/' . $moduleId))
            ->with('success', 'Module created with ' . count($actions) . ' selected action(s).');
    }

    /** POST modules/(:num)/details */
    public function updateDetails($id = null)
    {
        if ($deny = $this->guardPost('update')) {
            return $deny;
        }

        $db     = \Config\Database::connect();
        $module = $db->table('rbac_modules')->where('id', (int) $id)->get()->getRowArray();
        if (! $module) {
            return redirect()->to(base_url('modules'))->with('error', 'Module not found.');
        }

        [$data, $err] = $this->collectFields($db, $module);
        if ($err) {
            return redirect()->back()->with('error', $err)->with('tab', 'details');
        }

        if (! $db->table('rbac_modules')->where('id', $module['id'])->update($data)) {
            $e = $db->error();
            if (! empty($data['icon']) && $data['icon'] !== $module['icon']) {
                $this->deleteLocalIcon($data['icon']);
            }

            return redirect()->back()->with('error', 'Could not save: ' . mb_substr((string) ($e['message'] ?? 'unknown error'), 0, 200))->with('tab', 'details');
        }

        if (isset($data['icon']) && $data['icon'] !== $module['icon']) {
            $this->deleteLocalIcon($module['icon']);   // old uploaded image is no longer used
        }

        return redirect()->back()->with('success', 'Module details updated.')->with('tab', 'details');
    }

   /** POST modules/(:num)/scope */
    public function saveScope($id = null)
    {
        if ($deny = $this->guardPost('update')) {
            return $deny;
        }

        $db     = \Config\Database::connect();
        $module = $db->table('rbac_modules')->where('id', (int) $id)->get()->getRowArray();
        if (! $module) {
            return redirect()->to(base_url('modules'))->with('error', 'Module not found.');
        }

        $data = [
            'has_company_filter' => $this->request->getPost('has_company_filter') ? 1 : 0,
            'has_branch_filter'  => $this->request->getPost('has_branch_filter') ? 1 : 0,
        ];

        if (! $db->table('rbac_modules')->where('id', (int) $module['id'])->update($data)) {
            return redirect()->back()->with('error', 'Could not save scope settings.')->with('tab', 'scope');
        }

        return redirect()->back()->with('success', 'Company/Branch scope settings updated successfully.')->with('tab', 'scope');
    }
    /** POST modules/(:num)/status   (Super Admin: turns a module off for all users) */
    public function setStatus($id = null)
    {
        if ($deny = $this->guardPost('toggle_status')) {
            return $deny;
        }

        $status = (string) $this->request->getPost('status');
        if (! in_array($status, ['enabled', 'disabled'], true)) {
            return redirect()->back()->with('error', 'Invalid status.');
        }

        $db     = \Config\Database::connect();
        $module = $db->table('rbac_modules')->where('id', (int) $id)->get()->getRowArray();
        if (! $module) {
            return redirect()->to(base_url('modules'))->with('error', 'Module not found.');
        }
        if ($module['slug'] === 'module_setting' && $status === 'disabled') {
            return redirect()->back()->with('error', 'Module Setting cannot be disabled: it is needed to turn modules back on.');
        }

        $db->table('rbac_modules')->where('id', $module['id'])->update(['status' => $status]);

        return redirect()->back()->with('success', $module['name'] . ' is now ' . $status . ($status === 'disabled' ? ' for all users.' : '.'));
    }

    /** POST modules/(:num)/permissions */
    public function addPermissions($id = null)
    {
        if ($deny = $this->guardPost('update')) {
            return $deny;
        }

        $db     = \Config\Database::connect();
        $module = $db->table('rbac_modules')->where('id', (int) $id)->get()->getRowArray();
        if (! $module) {
            return redirect()->to(base_url('modules'))->with('error', 'Module not found.');
        }
        if ((int) $module['is_category'] === 1) {
            return redirect()->back()->with('error', 'Category containers do not have permissions.')->with('tab', 'access');
        }

        $actionIds = array_values(array_unique(array_map('intval', (array) $this->request->getPost('action_ids'))));
        if (! $actionIds) {
            return redirect()->back()->with('error', 'Select at least one action to add.')->with('tab', 'access');
        }

        $actions = $db->table('rbac_actions')->whereIn('id', $actionIds)->get()->getResultArray();
        $added   = 0;

        try {
            $db->transBegin();
            foreach ($actions as $a) {
                $exists = $db->table('rbac_permissions')->where('module_id', $module['id'])->where('action_id', $a['id'])->countAllResults();
                if ($exists) {
                    continue;
                }
                if (! $db->table('rbac_permissions')->insert([
                    'module_id' => $module['id'],
                    'action_id' => $a['id'],
                    'slug'      => $module['slug'] . '_' . $a['action_key'],
                ])) {
                    $e = $db->error();
                    throw new \RuntimeException((string) ($e['message'] ?? 'insert failed'));
                }
                $added++;
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'addPermissions failed: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Could not add permissions: ' . mb_substr($e->getMessage(), 0, 200))->with('tab', 'access');
        }

        return redirect()->back()->with('success', $added . ' permission(s) added.')->with('tab', 'access');
    }

    /** POST modules/(:num)/permissions/(:num)/remove */
    public function removePermission($id = null, $pid = null)
    {
        if ($deny = $this->guardPost('delete')) {
            return $deny;
        }

        $db   = \Config\Database::connect();
        $perm = $db->table('rbac_permissions')->where('id', (int) $pid)->where('module_id', (int) $id)->get()->getRowArray();
        if (! $perm) {
            return redirect()->back()->with('error', 'Permission not found.')->with('tab', 'access');
        }

        // Foreign keys (ON DELETE CASCADE) also remove it from every role and account
        $db->table('rbac_permissions')->where('id', $perm['id'])->delete();

        return redirect()->back()->with('success', 'Permission removed from this module.')->with('tab', 'access');
    }

    /**
     * POST modules/(:num)/admins
     * Saves (1) the module administrators and (2) which actions require approval.
     *   - full "update"                       -> can change both
     *   - module administrator (module_admin) -> can change only the approval flags of their own module
     */
    public function saveAdmins($id = null)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        $db     = \Config\Database::connect();
        $module = $db->table('rbac_modules')->where('id', (int) $id)->get()->getRowArray();
        if (! $module) {
            return redirect()->to(base_url('modules'))->with('error', 'Module not found.');
        }
        if ((int) $module['is_category'] === 1) {
            return redirect()->back()->with('error', 'Category containers do not have administrators.')->with('tab', 'admins');
        }

        $canAdmins   = Rbac::can('module_setting', 'update');
        $canApproval = $canAdmins || ($this->isModuleAdmin($db, (int) $module['id']) && Rbac::can('module_setting', 'module_admin'));
        if (! $canApproval) {
            return redirect()->back()->with('error', 'You do not have permission to change this module\'s administrators.')->with('tab', 'admins');
        }

        // ---- validate input
        $newAdmins = null;   // null = leave the administrators untouched
        if ($canAdmins) {
            $newAdmins = $this->postedIds('admin_ids');
            if ($newAdmins === null) {
                return redirect()->back()->with('error', 'Invalid administrator selection.')->with('tab', 'admins');
            }
            if ($newAdmins) {
                $found = array_filter($this->accountOptions($db), static fn ($a) => in_array((int) $a['id'], $newAdmins, true));
                if (count($found) !== count($newAdmins)) {
                    return redirect()->back()->with('error', 'One or more selected users no longer exist.')->with('tab', 'admins');
                }
            }
        }

        $approvalPids = $this->postedIds('requires_approval');
        if ($approvalPids === null) {
            return redirect()->back()->with('error', 'Invalid approval selection.')->with('tab', 'admins');
        }
        $modulePids   = array_map('intval', array_column(
            $db->table('rbac_permissions')->select('id')->where('module_id', $module['id'])->get()->getResultArray(),
            'id'
        ));
        $approvalPids = array_values(array_intersect($approvalPids, $modulePids));   // only this module's permissions

        // ---- save
        try {
            $db->transBegin();

            if ($newAdmins !== null) {
                $current = array_map('intval', array_column(
                    $db->table('rbac_module_admins')->select('account_id')->where('module_id', $module['id'])->get()->getResultArray(),
                    'account_id'
                ));
                $remove = array_values(array_diff($current, $newAdmins));
                $add    = array_values(array_diff($newAdmins, $current));

                if ($remove) {
                    $db->table('rbac_module_admins')->where('module_id', $module['id'])->whereIn('account_id', $remove)->delete();
                }
                foreach ($add as $accountId) {
                    $db->table('rbac_module_admins')->insert(['module_id' => (int) $module['id'], 'account_id' => $accountId]);
                }
            }

            $db->table('rbac_permissions')->where('module_id', $module['id'])->update(['requires_approval' => 0]);
            if ($approvalPids) {
                $db->table('rbac_permissions')->where('module_id', $module['id'])->whereIn('id', $approvalPids)->update(['requires_approval' => 1]);
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('The transaction failed.');
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'saveAdmins failed: ' . $e->getMessage());

            return redirect()->back()->with('error', 'Could not save: ' . mb_substr($e->getMessage(), 0, 200))->with('tab', 'admins');
        }

        return redirect()->back()->with('success', 'Administrators and approval settings saved.')->with('tab', 'admins');
    }

    // ---------------------------------------------------------------- helpers

    /** Shared view data (sidebar, RBAC flags, breadcrumbs). */
    private function baseData(string $title, string $pageTitle, array $breadcrumbs): array
    {
        $userId = session()->get('account_id') ?? session()->get('id');
        $roleId = session()->get('user_role_id');

        return [
            'title'           => $title,
            'pageTitle'       => $pageTitle,
            'filteredModules' => (new Rbac_model())->get_user_modules($userId, $roleId),
            'can'             => Rbac::map('module_setting', ['create', 'read', 'update', 'delete', 'toggle_status', 'module_admin']),
            'breadcrumbs'     => $breadcrumbs,
        ];
    }

    private function currentAccountId(): int
    {
        return (int) (session()->get('account_id') ?? session()->get('id'));
    }

    /** Is the logged-in user an assigned administrator of this module? */
    private function isModuleAdmin($db, int $moduleId): bool
    {
        return $db->table('rbac_module_admins')
            ->where('module_id', $moduleId)->where('account_id', $this->currentAccountId())
            ->countAllResults() > 0;
    }

    /** @return list<int> module ids the logged-in user administers */
    private function myModuleIds($db): array
    {
        $rows = $db->table('rbac_module_admins')->select('module_id')->where('account_id', $this->currentAccountId())->get()->getResultArray();

        return array_map('intval', array_column($rows, 'module_id'));
    }

    /**
     * ALL accounts (for the "Add administrators" popup), with their role name.
     * Table/column names are detected, so it still works if your accounts table differs a little.
     * If yours is not called "accounts" / "rbac_roles", change the two names below.
     */
    private function accountOptions($db): array
    {
        // usr_account (login) + usr_account_info (names, avatar) + role name
        $b = $db->table('usr_account a')
            ->select("a.id, a.username, a.email_workspace AS email, a.account_status, i.first_name, i.middle_name, i.last_name, i.avatar", false)
            ->join('usr_account_info i', 'i.account_id = a.id', 'left');

        // Role name: first existing roles table wins (adjust here if yours is named differently)
        foreach (['rbac_roles', 'usr_role', 'usr_roles', 'roles'] as $rt) {
            if ($db->tableExists($rt)) {
                $nameCol = in_array('name', $db->getFieldNames($rt), true) ? 'name' : (in_array('role_name', $db->getFieldNames($rt), true) ? 'role_name' : null);
                if ($nameCol) {
                    $b->select('r.' . $nameCol . ' AS role_name')->join($rt . ' r', 'r.id = a.user_role_id', 'left');
                    break;
                }
            }
        }

        $rows = $b->orderBy('i.first_name', 'ASC')->orderBy('a.id', 'ASC')->get()->getResultArray();

        // Display name: first + last, else username, else email
        foreach ($rows as &$r) {
            $full = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
            $r['full_name'] = $full !== '' ? $full : ($r['username'] ?: ($r['email'] ?: 'User #' . $r['id']));
        }
        unset($r);

        return $rows;
    }

    /**
     * Posted array of positive ints (e.g. admin_ids[]). Missing key = []. Malformed = null.
     *
     * @return list<int>|null
     */
    private function postedIds(string $key): ?array
    {
        $posted = $this->request->getPost($key);
        if ($posted === null) {
            return [];
        }
        if (! is_array($posted)) {
            return null;
        }

        $ids = [];
        foreach ($posted as $v) {
            $v = is_scalar($v) ? (string) $v : '';
            if ($v === '' || ! ctype_digit($v) || (int) $v < 1) {
                return null;
            }
            $ids[] = (int) $v;
        }

        return array_values(array_unique($ids));
    }

    /** Entry guard: full "read" OR the scoped "module_admin" action opens Module Settings. */
    private function guardEntry()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        if (! Rbac::can('module_setting', 'read') && ! Rbac::can('module_setting', 'module_admin')) {
            return redirect()->to(base_url('dashboard'))->with('error', 'You do not have permission to open Module Settings.');
        }

        return null;
    }

    /** Category containers that can be chosen as a parent. */
    private function categoryOptions($db, int $exceptId = 0): array
    {
        $b = $db->table('rbac_modules')->select('id, name')->where('is_category', 1);
        if ($exceptId) {
            $b->where('id !=', $exceptId);
        }

        return $b->orderBy('name', 'ASC')->get()->getResultArray();
    }

    /**
     * Normalize selected action IDs without silently accepting malformed input.
     *
     * @return list<int>|null
     */
    private function selectedActionIds(): ?array
    {
        $posted = $this->request->getPost('action_ids');
        if ($posted === null) {
            return [];
        }
        if (! is_array($posted)) {
            return null;
        }

        $ids = [];
        foreach ($posted as $value) {
            if (! is_string($value) && ! is_int($value)) {
                return null;
            }

            $value = (string) $value;
            if ($value === '' || ! ctype_digit($value) || (int) $value < 1) {
                return null;
            }
            $ids[] = (int) $value;
        }

        return array_values(array_unique($ids));
    }

    private function guardPage(string $action)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        if (! Rbac::can('module_setting', $action)) {
            return redirect()->to(base_url('dashboard'))->with('error', 'You do not have permission to open Module Settings.');
        }

        return null;
    }

    private function guardPost(string $action)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        if (! Rbac::can('module_setting', $action)) {
            return redirect()->back()->with('error', 'You do not have permission to do this (Module Settings: ' . $action . ').');
        }

        return null;
    }

    /**
     * Validate the module form (create or edit) and return [data, error].
     * Column sizes follow rbac_modules: name/slug 100, url 255, icon/badge 50.
     */
    private function collectFields($db, ?array $existing): array
    {
        $rules = [
            'name'        => 'required|max_length[100]',
            'url'         => 'permit_empty|max_length[255]|regex_match[/^[a-zA-Z0-9_\-\/]*$/]',
            'icon'        => 'permit_empty|max_length[50]',
            'badge'       => 'permit_empty|max_length[50]',
            'parent_id'   => 'permit_empty|is_natural_no_zero',
            'is_category' => 'required|in_list[0,1]',
            'short_order' => 'permit_empty|is_natural|less_than[100000]',
        ];
        if (! $this->validate($rules)) {
            return [null, implode(' ', $this->validator->getErrors())];
        }

        $name       = trim((string) $this->request->getPost('name'));
        $isCategory = (int) $this->request->getPost('is_category');

        // slug: fixed once created (permission slugs and RBAC checks use it)
        if ($existing) {
            $slug = $existing['slug'];
        } else {
            $slug = strtolower(trim((string) $this->request->getPost('slug')));
            if ($slug === '') {
                $slug = trim(strtolower(preg_replace('/[^a-z0-9]+/i', '_', $name)), '_');
            }
            if ($slug === '' || strlen($slug) > 100 || ! preg_match('/^[a-z0-9_]+$/', $slug)) {
                return [null, 'Slug may only contain lowercase letters, numbers and underscores.'];
            }
            if ($db->table('rbac_modules')->where('slug', $slug)->countAllResults()) {
                return [null, 'The slug "' . $slug . '" is already used by another module.'];
            }
        }

        // parent: categories are top-level; a module may sit under a category
        $parentId = (int) $this->request->getPost('parent_id') ?: null;
        if ($isCategory === 1) {
            $parentId = null;
        }
        if ($parentId !== null) {
            $parent = $db->table('rbac_modules')->where('id', $parentId)->where('is_category', 1)->get()->getRowArray();
            if (! $parent || ($existing && (int) $existing['id'] === $parentId)) {
                return [null, 'Choose a valid parent category.'];
            }
        }

        if ($existing) {
            if ($isCategory === 1 && (int) $existing['is_category'] === 0 && $existing['slug'] === 'module_setting') {
                return [null, 'Module Setting must stay a functional module.'];
            }
            if ($isCategory === 0 && (int) $existing['is_category'] === 1
                && $db->table('rbac_modules')->where('parent_id', $existing['id'])->countAllResults()) {
                return [null, 'Move or remove the child modules before changing this category to a functional module.'];
            }
            if ($isCategory === 1 && (int) $existing['is_category'] === 0
                && $db->table('rbac_permissions')->where('module_id', $existing['id'])->countAllResults()) {
                return [null, 'Remove this module\'s permissions before turning it into a category container.'];
            }
        }

        $url = trim((string) $this->request->getPost('url'), " \t\n\r\0\x0B/");
        if ($isCategory === 1) {
            $url = '';
        } elseif ($url === '') {
            return [null, 'Page URL is required for a functional module.'];
        }

        $data = [
            'name'        => $name,
            'slug'        => $slug,
            'url'         => $url !== '' ? $url : null,
            'badge'       => trim((string) $this->request->getPost('badge')) ?: null,
            'parent_id'   => $parentId,
            'is_category' => $isCategory,
            'short_order' => (int) $this->request->getPost('short_order'),
        ];

        // Icon: uploaded image > typed/picked class > keep current
        [$iconPath, $iconErr] = $this->storeIcon();
        if ($iconErr) {
            return [null, $iconErr];
        }
        $iconText = trim((string) preg_replace('/[^a-z0-9\- ]/i', '', (string) $this->request->getPost('icon')));
        if ($iconPath) {
            $data['icon'] = $iconPath;
        } elseif ($iconText !== '') {
            $data['icon'] = $iconText;
        } elseif (! $existing) {
            $data['icon'] = 'fa-cube';
        }

        return [$data, null];
    }

    /** Optional icon image (JPG/PNG/WEBP, max 2 MB) saved in public/uploads/modules. [relativePath|null, error|null] */
    private function storeIcon(): array
    {
        $file = $this->request->getFile('icon_file');
        if (! $file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }
        if (! $file->isValid()) {
            return [null, 'Icon upload failed: ' . $file->getErrorString()];
        }
        if ($file->getSize() > 2 * 1024 * 1024) {
            return [null, 'Icon image is too large (max 2 MB).'];
        }

        $extByMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime      = $file->getMimeType();
        if (! isset($extByMime[$mime]) || @getimagesize($file->getTempName()) === false) {
            return [null, 'Icon must be a JPG, PNG or WEBP image.'];
        }

        $name = bin2hex(random_bytes(8)) . '.' . $extByMime[$mime];   // keeps the path under the 50-char icon column
        $dir  = FCPATH . 'uploads/modules';
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return [null, 'Could not create the upload folder.'];
        }
        $file->move($dir, $name);

        return ['uploads/modules/' . $name, null];
    }

    private function deleteLocalIcon(?string $icon): void
    {
        if ($icon && str_starts_with($icon, 'uploads/modules/') && ! str_contains($icon, '..')) {
            @unlink(FCPATH . $icon);
        }
    }
}