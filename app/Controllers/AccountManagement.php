<?php

namespace App\Controllers;

use App\Libraries\Rbac;
use App\Models\Rbac_model;
use App\Models\UserModel;

class AccountManagement extends BaseController
{
    private const STATUSES = ['active', 'inactive', 'suspended', 'pending'];

    // 1. ACCOUNT LIST PAGE WITH MODAL DATA
    public function index()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        if ($deny = $this->denyUnless('read', true)) {
            return $deny;
        }

        $userId = session()->get('account_id') ?? session()->get('id');
        $roleId = session()->get('user_role_id');

        $rbacModel = new Rbac_model();
        $userModel = new UserModel();
        $db        = \Config\Database::connect();

        $users = $userModel->select('usr_account.*, rbac_roles.name as role_name, usr_account_info.first_name, usr_account_info.middle_name, usr_account_info.last_name, usr_account_info.avatar')
            ->join('rbac_roles', 'rbac_roles.id = usr_account.user_role_id', 'left')
            ->join('usr_account_info', 'usr_account_info.account_id = usr_account.id', 'left')
            ->orderBy('usr_account.id', 'DESC')
            ->findAll();

        // Kumuha ng Roles at Modules para sa Add Account Modal
        $roles = $db->table('rbac_roles')->select('id, name')->orderBy('id')->get()->getResultArray();
        $modules = $rbacModel->findAll();

        foreach ($modules as &$mod) {
            $mod['actions'] = [];
            if ($db->tableExists('rbac_permissions') && $db->tableExists('rbac_actions')) {
                $mod['actions'] = $db->table('rbac_permissions')
                    ->select('rbac_permissions.id as pid, rbac_actions.name as action_name, rbac_actions.action_key')
                    ->join('rbac_actions', 'rbac_actions.id = rbac_permissions.action_id', 'inner')
                    ->where('rbac_permissions.module_id', $mod['id'])
                    ->get()
                    ->getResultArray();
            }
        }
        unset($mod);

        $rolePermissions = [];
        if ($db->tableExists('rbac_role_permissions')) {
            $rpRows = $db->table('rbac_role_permissions')->get()->getResultArray();
            foreach ($rpRows as $rp) {
                $rolePermissions[$rp['role_id']][] = (int) $rp['permission_id'];
            }
        }

        $data = [
            'title'           => 'User Management',
            'pageTitle'       => 'User Management',
            'filteredModules' => $rbacModel->get_user_modules($userId, $roleId),
            'users'           => $users,
            'roles'           => $roles,
            'modules'         => $modules,
            'rolePermissions' => $rolePermissions,
            'can'             => $this->abilities(),
            'breadcrumbs'     => [
                ['label' => 'Home', 'url' => 'dashboard'],
                ['label' => 'User Management'],
            ],
        ];

        return view('account_management/list', $data);
    }

    // CREATE NEW ACCOUNT  POST accounts/create  (Google Workspace or Username & Password)
    public function createAccount()
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        // RBAC: needs the Create permission on Account Management
        if ($deny = $this->denyUnless('create')) {
            return $deny;
        }
        $actorRole = (int) session()->get('user_role_id');

        $db            = \Config\Database::connect();
        $authType      = (string) $this->request->getPost('auth_type');
        $accountStatus = (string) ($this->request->getPost('account_status') ?: 'active');
        if (! in_array($accountStatus, self::STATUSES, true)) {
            $accountStatus = 'active';
        }
        $roleId = (int) $this->request->getPost('user_role_id');
        $now    = date('Y-m-d H:i:s');
        $avatarPath = null;

        // Role must exist; only a Super Admin may create a Super Admin
        if (! $db->table('rbac_roles')->where('id', $roleId)->countAllResults()) {
            return $this->fail('Please select a valid role.');
        }
        if ($roleId === 1 && $actorRole !== 1) {
            return $this->fail('Only a Super Admin can create a Super Admin account.');
        }

        if ($authType === 'google') {
            $rules = [
                'email_workspace' => 'required|valid_email|max_length[150]|is_unique[usr_account.email_workspace]',
                'user_role_id'    => 'required|is_natural_no_zero',
            ];
            if (! $this->validate($rules)) {
                return $this->fail(implode(' ', $this->validator->getErrors()));
            }

            $email = strtolower(trim((string) $this->request->getPost('email_workspace')));
            if (! str_ends_with($email, '@bounty.com.ph')) {
                return $this->fail('Email must belong to the @bounty.com.ph domain.');
            }
            $local = explode('@', $email)[0];

            // username must be unique too (it is derived from the email)
            if ($db->table('usr_account')->where('username', $local)->countAllResults()) {
                return $this->fail('Username "' . $local . '" is already taken.');
            }

            $accountData = [
                'username'        => $local,
                'email_workspace' => $email,
                'password'        => '',   // column is NOT NULL; Google accounts have no password
                'user_role_id'    => $roleId,
                'account_status'  => $accountStatus,
            ];
            $infoData = [
                'first_name' => mb_substr(ucfirst($local), 0, 50),
                'last_name'  => 'User',
                'email'      => $email,
            ];
        } elseif ($authType === 'credentials') {
            $rules = [
                'username'     => 'required|alpha_numeric_space|min_length[3]|max_length[50]|is_unique[usr_account.username]',
                'password'     => 'required|min_length[8]|max_length[72]',
                'first_name'   => 'required|max_length[50]',
                'last_name'    => 'required|max_length[50]',
                'user_role_id' => 'required|is_natural_no_zero',
                'email'        => 'permit_empty|valid_email|max_length[100]',
            ];
            if (! $this->validate($rules)) {
                return $this->fail(implode(' ', $this->validator->getErrors()));
            }

            // Optional profile photo (Username & Password accounts only; Google accounts use their Google picture)
            [$avatarPath, $avatarErr] = $this->storeAvatar('avatar');
            if ($avatarErr) {
                return $this->fail($avatarErr);
            }

            $accountData = [
                'username'        => trim((string) $this->request->getPost('username')),
                'email_workspace' => null,
                'password'        => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
                'user_role_id'    => $roleId,
                'account_status'  => $accountStatus,
            ];
            $infoData = [
                'first_name'   => trim((string) $this->request->getPost('first_name')),
                'middle_name'  => trim((string) $this->request->getPost('middle_name')),
                'last_name'    => trim((string) $this->request->getPost('last_name')),
                'email'        => trim((string) $this->request->getPost('email')) ?: null,
                'phone_number' => trim((string) $this->request->getPost('phone_number')),
                'sex'          => $this->request->getPost('sex') ?: null,
                'avatar'       => $avatarPath,
            ];
        } else {
            return $this->fail('Please choose an authentication type.');
        }

        // created_at only where the column exists
        if ($db->fieldExists('created_at', 'usr_account')) {
            $accountData['created_at'] = $now;
        }
        if ($db->fieldExists('created_at', 'usr_account_info')) {
            $infoData['created_at'] = $now;
        }

        // Only real permission ids are accepted
        $posted        = array_values(array_unique(array_map('intval', (array) $this->request->getPost('permission_ids'))));
        $permissionIds = [];
        if ($posted) {
            $valid         = $db->table('rbac_permissions')->select('id, module_id, action_id')->whereIn('id', $posted)->get()->getResultArray();
            $permissionIds = array_map('intval', array_column($valid, 'id'));
            $permById      = array_column($valid, null, 'id');
        }

        $insert = fn ($db, string $table, array $row) => $this->insertOrFail($db, $table, $row);

        try {
            $db->transBegin();

            $insert($db, 'usr_account', $accountData);

            $accountId = $db->insertID();
            if (! $accountId) {
                // id is not AUTO_INCREMENT here: read it back through the unique username
                $found     = $db->table('usr_account')->select('id')->where('username', $accountData['username'])->get()->getRowArray();
                $accountId = $found['id'] ?? null;
            }
            if (! $accountId) {
                throw new \RuntimeException('usr_account row saved but its id could not be read (is id AUTO_INCREMENT?).');
            }

            $insert($db, 'usr_account_info', $infoData + ['account_id' => $accountId]);

            if ($permissionIds) {
                $schema = $this->userPermSchema($db);
                if ($schema === null) {
                    throw new \RuntimeException('rbac_user_permissions layout not supported.');
                }
                $fields = $db->getFieldNames('rbac_user_permissions');

                foreach ($permissionIds as $pid) {
                    $row = [$schema['userCol'] => $accountId];
                    if ($schema['mode'] === 'permission') {
                        $row['permission_id'] = $pid;
                    } else {
                        $row['module_id'] = (int) $permById[$pid]['module_id'];
                        $row['action_id'] = (int) $permById[$pid]['action_id'];
                    }
                    foreach (['created_at', 'updated_at'] as $ts) {
                        if (in_array($ts, $fields, true)) { $row[$ts] = $now; }
                    }
                    $insert($db, 'rbac_user_permissions', $row);
                }
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database reported a failed query.');
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            $this->deleteLocalAvatar($avatarPath);   // do not leave an orphan photo behind
            log_message('error', 'createAccount failed: ' . $e->getMessage());

            return $this->fail('Failed to create account: ' . mb_substr($e->getMessage(), 0, 300));
        }

        return redirect()->to(base_url('accounts'))->with('success', 'User account created successfully.');
    }

   public function configure($id = null)
    {
        if (! session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }
        if ($deny = $this->denyUnless('read', true)) {
            return $deny;
        }

        $currentUserId = session()->get('account_id') ?? session()->get('id');
        $roleId        = session()->get('user_role_id');

        $rbacModel = new Rbac_model();
        $userModel = new UserModel();
        $db        = \Config\Database::connect();

        $selectedUser = $userModel->select('usr_account.*, rbac_roles.name as role_name, usr_account_info.first_name, usr_account_info.middle_name, usr_account_info.last_name, usr_account_info.sex, usr_account_info.email as info_email, usr_account_info.phone_number, usr_account_info.avatar')
            ->join('rbac_roles', 'rbac_roles.id = usr_account.user_role_id', 'left')
            ->join('usr_account_info', 'usr_account_info.account_id = usr_account.id', 'left')
            ->where('usr_account.id', $id)
            ->first();

        if (! $selectedUser) {
            return redirect()->to(base_url('accounts'))->with('error', 'User not found');
        }

        $modules = $rbacModel->findAll();
        $granted = $this->grantedPermissionIds($db, $selectedUser);

        // Kunin ang lahat ng roles para sa dropdown sa Module Access tab
        $roles = $db->table('rbac_roles')->select('id, name')->orderBy('id')->get()->getResultArray();

        // Kunin ang companies at branches para sa Company / Branch Scope tab
        $companies = $db->table('com_company')->where('status', 'active')->orderBy('name', 'ASC')->get()->getResultArray();
        $branches  = $db->table('com_branch b')
            ->select('b.id, b.branch_name, b.company_id, c.name as company_name, c.short_name as company_short_name')
            ->join('com_company c', 'c.id = b.company_id', 'inner')
            ->where('b.status', 'active')
            ->orderBy('b.branch_name', 'ASC')
            ->get()->getResultArray();

        // Kunin ang kasalukuyang nakatalagang branches ng user na ito
        $assignedBranchRows = $db->table('usr_account_branches')->select('branch_id')->where('account_id', $selectedUser['id'])->get()->getResultArray();
        $assignedBranchIds = array_map('intval', array_column($assignedBranchRows, 'branch_id'));

        // Kunin ang role permissions mapping para sa JS role switching
        $rolePermissions = [];
        if ($db->tableExists('rbac_role_permissions')) {
            $rpRows = $db->table('rbac_role_permissions')->get()->getResultArray();
            foreach ($rpRows as $rp) {
                $rolePermissions[$rp['role_id']][] = (int) $rp['permission_id'];
            }
        }

        foreach ($modules as &$mod) {
            $mod['actions'] = [];
            try {
                if ($db->tableExists('rbac_permissions') && $db->tableExists('rbac_actions')) {
                    $mod['actions'] = $db->table('rbac_permissions')
                        ->select('rbac_permissions.*, rbac_actions.name as action_name, rbac_actions.action_key, rbac_actions.description as action_desc')
                        ->join('rbac_actions', 'rbac_actions.id = rbac_permissions.action_id', 'inner')
                        ->where('rbac_permissions.module_id', $mod['id'])
                        ->get()
                        ->getResultArray();
                }
            } catch (\Exception $e) {
                $mod['actions'] = [];
            }

            foreach ($mod['actions'] as &$act) {
                $act['granted'] = $granted === null ? true : in_array((int) $act['id'], $granted, true);
            }
            unset($act);
        }
        unset($mod);

        $data = [
            'title'             => 'Configure User',
            'pageTitle'         => 'User Management / User Profile',
            'filteredModules'   => $rbacModel->get_user_modules($currentUserId, $roleId),
            'selectedUser'      => $selectedUser,
            'modules'           => $modules,
            'roles'             => $roles,
            'companies'         => $companies,
            'branches'          => $branches,
            'assignedBranchIds' => $assignedBranchIds,
            'rolePermissions'   => $rolePermissions,
            'can'               => $this->abilities(),
            'breadcrumbs'       => [
                ['label' => 'Home', 'url' => 'dashboard'],
                ['label' => 'User Management', 'url' => 'accounts'],
                ['label' => 'User Profile'],
            ],
        ];

        return view('account_management/configure', $data);
    }

    // SAVE BRANCH SCOPE  POST accounts/(:num)/scope
    public function saveBranchScope($id = null)
    {
        [$target, $deny] = $this->authorizeTarget($id);
        if ($deny) {
            return $deny;
        }

        $branchIds = array_values(array_unique(array_map('intval', (array) $this->request->getPost('branch_ids'))));
        $db = \Config\Database::connect();

        try {
            $db->transBegin();

            // Tanggalin ang mga lumang assignments
            $db->table('usr_account_branches')->where('account_id', $target['id'])->delete();

            // I-insert ang mga bagong piniling branches
            if (!empty($branchIds)) {
                $now = date('Y-m-d H:i:s');
                foreach ($branchIds as $bid) {
                    $this->insertOrFail($db, 'usr_account_branches', [
                        'account_id' => $target['id'],
                        'branch_id'  => $bid,
                        'created_at' => $now,
                    ]);
                }
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database reported a failed query.');
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'saveBranchScope failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Could not save branch scope: ' . mb_substr($e->getMessage(), 0, 300))->with('tab', 'scope');
        }

        return redirect()->back()->with('success', 'Company/Branch scope updated successfully.')->with('tab', 'scope');
    }
    // 3. SAVE PROFILE (modal)  POST accounts/(:num)/profile
    public function updateProfile($id = null)
    {
        [$target, $deny] = $this->authorizeTarget($id);
        if ($deny) {
            return $deny;
        }

        $rules = [
            'first_name'   => 'required|max_length[50]',
            'middle_name'  => 'permit_empty|max_length[50]',
            'last_name'    => 'required|max_length[50]',
            'phone_number' => 'permit_empty|max_length[20]|regex_match[/^[0-9+\-\s()]*$/]',
            'sex'          => 'permit_empty|in_list[Male,Female,Other]',
            'email'        => 'permit_empty|valid_email|max_length[100]',
        ];
        if (! $this->validate($rules)) {
            return $this->fail(implode(' ', $this->validator->getErrors()));
        }

        $db       = \Config\Database::connect();
        $isGoogle = trim((string) ($target['email_workspace'] ?? '')) !== '';

        $info = [
            'first_name'   => trim((string) $this->request->getPost('first_name')),
            'middle_name'  => trim((string) $this->request->getPost('middle_name')),
            'last_name'    => trim((string) $this->request->getPost('last_name')),
            'phone_number' => trim((string) $this->request->getPost('phone_number')),
            'sex'          => $this->request->getPost('sex') ?: null,
        ];

        // Username & Password accounts: email and photo are editable.
        // Google accounts keep their workspace email and Google picture.
        $avatarPath = null;
        $oldAvatar  = null;
        if (! $isGoogle) {
            $info['email'] = trim((string) $this->request->getPost('email')) ?: null;

            [$avatarPath, $avatarErr] = $this->storeAvatar('avatar');
            if ($avatarErr) {
                return $this->fail($avatarErr);
            }
            if ($avatarPath) {
                $oldRow    = $db->table('usr_account_info')->select('avatar')->where('account_id', $target['id'])->get()->getRowArray();
                $oldAvatar = $oldRow['avatar'] ?? null;
                $info['avatar'] = $avatarPath;
            }
        }

        try {
            $db->transBegin();

            $exists = $db->table('usr_account_info')->where('account_id', $target['id'])->countAllResults() > 0;
            if ($exists) {
                $ok = $db->table('usr_account_info')->where('account_id', $target['id'])
                    ->update($info + $this->touch($db, 'usr_account_info'));
                if (! $ok) {
                    $err = $db->error();
                    throw new \RuntimeException('usr_account_info update failed: ' . ($err['message'] ?? 'unknown error'));
                }
            } else {
                $this->insertOrFail($db, 'usr_account_info', $info + ['account_id' => $target['id']]);
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database reported a failed query.');
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            $this->deleteLocalAvatar($avatarPath);
            log_message('error', 'updateProfile failed: ' . $e->getMessage());

            return $this->fail('Could not save the profile: ' . mb_substr($e->getMessage(), 0, 300));
        }

        if ($avatarPath) {
            $this->deleteLocalAvatar($oldAvatar);               // remove the replaced photo
            if ($this->isSelf($target)) {
                session()->set('avatar', $avatarPath);          // header avatar updates immediately
            }
        }

        return redirect()->back()->with('success', 'Profile updated successfully.')->with('tab', 'profile');
    }

    // 4. CHANGE PASSWORD
    public function changePassword($id = null)
    {
        [$target, $deny] = $this->authorizeTarget($id);
        if ($deny) {
            return $deny;
        }
        if (trim((string) ($target['email_workspace'] ?? '')) !== '') {
            return $this->fail('This account signs in with Google Workspace. Its password is managed by Google.');
        }

        $rules = [
            'new_password'     => 'required|min_length[8]|max_length[72]',
            'confirm_password' => 'required|matches[new_password]',
        ];
        if (! $this->validate($rules)) {
            return $this->fail(implode(' ', $this->validator->getErrors()));
        }

        $db = \Config\Database::connect();
        $db->table('usr_account')->where('id', $target['id'])->update([
            'password' => password_hash((string) $this->request->getPost('new_password'), PASSWORD_DEFAULT),
        ] + $this->touch($db, 'usr_account'));

        return redirect()->back()->with('success', 'Password updated successfully.')->with('tab', 'profile');
    }

    // 5. CHANGE ACCOUNT STATUS
    public function setStatus($id = null)
    {
        [$target, $deny] = $this->authorizeTarget($id, 'toggle_status');
        if ($deny) {
            return $deny;
        }

        $status = (string) $this->request->getPost('account_status');
        if (! in_array($status, self::STATUSES, true)) {
            return $this->fail('Invalid account status.');
        }
        if ($this->isSelf($target) && $status !== 'active') {
            return $this->fail('You cannot deactivate your own account.');
        }

        $db = \Config\Database::connect();
        $db->table('usr_account')->where('id', $target['id'])
            ->update(['account_status' => $status] + $this->touch($db, 'usr_account'));

        return redirect()->back()->with('success', 'Account status set to ' . ucfirst($status) . '.')->with('tab', 'profile');
    }

    // 6. SAVE MODULE ACCESS (KASAMA ANG ROLE ID AT PERMISSIONS)
    public function saveModuleAccess($id = null)
    {
        [$target, $deny] = $this->authorizeTarget($id);
        if ($deny) {
            return $deny;
        }

        $roleId    = (int) $this->request->getPost('user_role_id');
        $moduleIds = array_values(array_unique(array_map('intval', (array) $this->request->getPost('module_ids'))));
        $posted    = array_values(array_unique(array_map('intval', (array) $this->request->getPost('permission_ids'))));

        $db     = \Config\Database::connect();
        $schema = $this->userPermSchema($db);
        if ($schema === null) {
            return $this->fail('Cannot save: rbac_user_permissions layout not supported.')->with('tab', 'modules');
        }

        if ($roleId && ! $db->table('rbac_roles')->where('id', $roleId)->countAllResults()) {
            return $this->fail('Selected role does not exist.')->with('tab', 'modules');
        }
        if ($roleId && $roleId !== (int) $target['user_role_id']) {
            if ($this->isSelf($target)) {
                return $this->fail('You cannot change your own role.');
            }
            $actor = (int) session()->get('user_role_id');
            if ($roleId === 1 && $actor !== 1) {
                return $this->fail('Only a Super Admin can assign the Super Admin role.');
            }
        }

        $scopeRows = [];
        if (!empty($moduleIds)) {
            $scopeRows = $db->table('rbac_permissions')->select('id, module_id, action_id')
                ->whereIn('module_id', $moduleIds)->get()->getResultArray();
        }
        $scope   = array_map('intval', array_column($scopeRows, 'id'));
        $allowed = !empty($scope) ? array_values(array_intersect($posted, $scope)) : [];
        $byId    = array_column($scopeRows, null, 'id');
        $now     = date('Y-m-d H:i:s');
        $fields  = $db->getFieldNames('rbac_user_permissions');

        try {
            $db->transBegin();

            // 1. Update user role kung binago sa module access tab
            if ($roleId && $roleId !== (int) $target['user_role_id']) {
                $db->table('usr_account')->where('id', $target['id'])
                    ->update(['user_role_id' => $roleId] + $this->touch($db, 'usr_account'));
            }

            // 2. Save user permissions overrides
            $del = $db->table('rbac_user_permissions')->where($schema['userCol'], $target['id']);
            if ($schema['mode'] === 'permission') {
                if (!empty($scope)) {
                    $del->whereIn('permission_id', $scope)->delete();
                } else {
                    $del->delete();
                }
            } else {
                if (!empty($moduleIds)) {
                    $del->whereIn('module_id', $moduleIds)->delete();
                } else {
                    $del->delete();
                }
            }

            foreach ($allowed as $pid) {
                if (!isset($byId[$pid])) {
                    continue;
                }
                $row = [$schema['userCol'] => $target['id']];
                if ($schema['mode'] === 'permission') {
                    $row['permission_id'] = $pid;
                } else {
                    $row['module_id'] = (int) $byId[$pid]['module_id'];
                    $row['action_id'] = (int) $byId[$pid]['action_id'];
                }
                foreach (['created_at', 'updated_at'] as $ts) {
                    if (in_array($ts, $fields, true)) { $row[$ts] = $now; }
                }
                $this->insertOrFail($db, 'rbac_user_permissions', $row);
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Database reported a failed query.');
            }
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'saveModuleAccess failed: ' . $e->getMessage());
            return $this->fail('Could not save module access: ' . mb_substr($e->getMessage(), 0, 300))->with('tab', 'modules');
        }

        return redirect()->back()->with('success', 'Module access saved successfully.')->with('tab', 'modules');
    }

    // ---------------------------------------------------------------- helpers

    /** insert() returns false (no exception) when DBDebug is off: turn that into an exception with the real DB message. */
    private function insertOrFail($db, string $table, array $row): void
    {
        if (! $db->table($table)->insert($row)) {
            $err = $db->error();
            throw new \RuntimeException($table . ' insert failed: ' . ($err['message'] ?? 'unknown error'));
        }
    }

    /**
     * Validate + store an uploaded avatar (JPG/PNG/WEBP, max 2 MB) in public/uploads/avatars.
     * Returns [relativePath|null, errorMessage|null]. No file chosen => [null, null].
     */
    private function storeAvatar(string $field = 'avatar'): array
    {
        $file = $this->request->getFile($field);
        if (! $file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }
        if (! $file->isValid()) {
            return [null, 'Photo upload failed: ' . $file->getErrorString()];
        }
        if ($file->getSize() > 2 * 1024 * 1024) {
            return [null, 'Photo is too large (max 2 MB).'];
        }

        // Trust the server-detected type, not the file name
        $extByMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime      = $file->getMimeType();
        if (! isset($extByMime[$mime]) || @getimagesize($file->getTempName()) === false) {
            return [null, 'Photo must be a JPG, PNG or WEBP image.'];
        }

        $name = bin2hex(random_bytes(12)) . '.' . $extByMime[$mime];
        $dir  = FCPATH . 'uploads/avatars';
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return [null, 'Could not create the upload folder.'];
        }
        $file->move($dir, $name);

        return ['uploads/avatars/' . $name, null];
    }

    /** Delete a previously uploaded local avatar (never touches Google URLs). */
    private function deleteLocalAvatar(?string $path): void
    {
        if ($path && str_starts_with($path, 'uploads/avatars/') && ! str_contains($path, '..')) {
            @unlink(FCPATH . $path);
        }
    }

    private function userPermSchema($db): ?array
    {
        if (! $db->tableExists('rbac_user_permissions')) {
            return null;
        }
        $f = $db->getFieldNames('rbac_user_permissions');

        $userCol = null;
        foreach (['account_id', 'user_id'] as $col) {
            if (in_array($col, $f, true)) { $userCol = $col; break; }
        }
        if ($userCol === null) {
            return null;
        }
        if (in_array('permission_id', $f, true)) {
            return ['userCol' => $userCol, 'mode' => 'permission'];
        }
        if (in_array('module_id', $f, true) && in_array('action_id', $f, true)) {
            return ['userCol' => $userCol, 'mode' => 'module_action'];
        }

        return null;
    }

    private function grantedPermissionIds($db, array $user): ?array
    {
        $schema = $this->userPermSchema($db);
        if ($schema === null) {
            return null;
        }

        $rows = $db->table('rbac_user_permissions')->where($schema['userCol'], $user['id'])->get()->getResultArray();

        if ($rows) {
            if ($schema['mode'] === 'permission') {
                return array_map('intval', array_column($rows, 'permission_id'));
            }
            $ids = [];
            foreach ($rows as $r) {
                $p = $db->table('rbac_permissions')->select('id')
                    ->where('module_id', $r['module_id'])->where('action_id', $r['action_id'])->get()->getRowArray();
                if ($p) { $ids[] = (int) $p['id']; }
            }
            return $ids;
        }

        if ($db->tableExists('rbac_role_permissions')) {
            $rf = $db->getFieldNames('rbac_role_permissions');
            if (in_array('role_id', $rf, true) && in_array('permission_id', $rf, true)) {
                $rr = $db->table('rbac_role_permissions')->select('permission_id')
                    ->where('role_id', $user['user_role_id'])->get()->getResultArray();
                return array_map('intval', array_column($rr, 'permission_id'));
            }
        }

        return [];
    }

    /** Logged in + has the RBAC permission + may touch this target. Returns [targetRow|null, redirect|null]. */
    private function authorizeTarget($id, string $action = 'update'): array
    {
        if (! session()->get('isLoggedIn')) {
            return [null, redirect()->to(base_url('auth/login'))];
        }
        if ($deny = $this->denyUnless($action)) {
            return [null, $deny];
        }

        $target = \Config\Database::connect()->table('usr_account')
            ->where('id', (int) $id)->get()->getRowArray();

        if (! $target) {
            return [null, redirect()->to(base_url('accounts'))->with('error', 'User not found')];
        }
        if (! $this->canManage($target)) {
            return [null, redirect()->back()->with('error', 'Only a Super Admin can change a Super Admin account.')];
        }

        return [$target, null];
    }

    /** Target protection only: a Super Admin account can be changed by a Super Admin. (Permissions are checked by RBAC.) */
    private function canManage(array $target): bool
    {
        $actor = (int) session()->get('user_role_id');

        return ! ((int) $target['user_role_id'] === Rbac::SUPER_ADMIN_ROLE && $actor !== Rbac::SUPER_ADMIN_ROLE);
    }

    /** Redirect with an error unless the current user has this Account Management permission. */
    private function denyUnless(string $action, bool $isPage = false)
    {
        if (Rbac::can('account_management', $action)) {
            return null;
        }
        $msg = 'You do not have permission to do this (Account Management: ' . $action . ').';

        return $isPage
            ? redirect()->to(base_url('dashboard'))->with('error', $msg)
            : redirect()->back()->with('error', $msg);
    }

    /** Permission flags handed to the views so they can hide buttons (the server still enforces them). */
    private function abilities(): array
    {
        return Rbac::map('account_management', ['create', 'read', 'update', 'delete', 'toggle_status']);
    }

    private function isSelf(array $target): bool
    {
        return (string) $target['id'] === (string) (session()->get('account_id') ?? session()->get('id'));
    }

    private function fail(string $message)
    {
       return redirect()->back()->with('error', $message);
    }

    private function touch($db, string $table): array
    {
        return $db->fieldExists('updated_at', $table) ? ['updated_at' => date('Y-m-d H:i:s')] : [];
    }
}