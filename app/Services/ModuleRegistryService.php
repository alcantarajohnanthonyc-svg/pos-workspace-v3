<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DomainException;
use Throwable;

final class ModuleRegistryService
{
    private const TYPES = ['MASTERFILE', 'TRANSACTION', 'REPORT', 'ADMIN'];
    private const STATUSES = ['DRAFT', 'ACTIVE', 'INACTIVE'];
    private const STATIC_ROUTES = ['/superadmin/modules', '/user-management/users', '/outlets'];
    private const ACTION_HANDLERS = [
        'MODULE_REGISTRY' => ['MODULE_REGISTRY.VIEW' => 'MODULE_REGISTRY.INDEX'],
        'EMPLOYEE_MANAGEMENT' => [
            'VIEW' => 'USER_MANAGEMENT.INDEX',
            'MANAGE_ACCESS' => 'USER_MANAGEMENT.MANAGE_ACCESS',
            'APPROVE' => 'USER_ACCESS_APPROVAL.INDEX',
        ],
        'OUTLET_LIST' => [
            'VIEW' => 'OUTLET_LIST.INDEX', 'CREATE' => 'OUTLET_LIST.CREATE', 'UPDATE' => 'OUTLET_LIST.UPDATE',
            'DELETE' => 'OUTLET_LIST.ARCHIVE', 'APPROVE' => 'OUTLET_LIST.APPROVE',
        ],
    ];
    private const AUDIT_FIELDS = [
        'module_code', 'module_name', 'short_name', 'module_type', 'description',
        'route_path', 'icon', 'sort_order', 'status', 'parent_module_id',
    ];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /** @return array{items: list<array<string, mixed>>, total: int, page: int, perPage: int} */
    public function paginate(array $filters, int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $perPage = min(50, max(5, $perPage));
        $query = $this->moduleQuery($filters);
        $total = (clone $query)->countAllResults();
        $items = $query->select('m.*, parent.module_name AS parent_name')
            ->orderBy('m.sort_order', 'ASC')->orderBy('m.module_name', 'ASC')
            ->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    /** @return array<string, mixed>|null */
    public function find(int $moduleId): ?array
    {
        if ($moduleId < 1) {
            return null;
        }

        $row = $this->db->table('mod_modules m')
            ->select('m.*, parent.module_name AS parent_name')
            ->join('mod_modules parent', 'parent.module_id = m.parent_module_id', 'left')
            ->where('m.module_id', $moduleId)->get()->getRowArray();

        return $row ?: null;
    }

    /** @return array{audiences:list<string>,administrators:list<int>,actions:list<array<string,mixed>>,company_scopes:list<int>,branch_scopes:list<string>,companies:list<array<string,mixed>>,branches:list<array<string,mixed>>} */
    public function configuration(int $moduleId): array
    {
        $assignedCompanies = array_map('intval', array_column($this->db->table('mod_module_companies')->select('company_id')->where('module_id', $moduleId)->get()->getResultArray(), 'company_id'));
        $assignedBranches = $this->db->table('mod_module_branches')->select('company_id,branch_id')->where('module_id', $moduleId)->get()->getResultArray();
        $companyQuery = $this->db->table('org_companies')->select('company_id,company_code,company_name,status')->groupStart()->where('status', 'ACTIVE');
        if ($assignedCompanies !== []) $companyQuery->orWhereIn('company_id', $assignedCompanies);
        $companies = $companyQuery->groupEnd()->orderBy('company_name')->get()->getResultArray();
        $branchQuery = $this->db->table('org_branches b')->select('b.branch_id,b.company_id,b.branch_code,b.branch_name,b.status,c.company_code')->join('org_companies c', 'c.company_id=b.company_id')->groupStart()->where('b.status', 'ACTIVE');
        if ($assignedBranches !== []) $branchQuery->orGroupStart();
        if ($assignedBranches !== []) {
            foreach ($assignedBranches as $branch) {
                $branchQuery->orGroupStart()->where('b.company_id', (int) $branch['company_id'])->where('b.branch_id', (int) $branch['branch_id'])->groupEnd();
            }
        }
        if ($assignedBranches !== []) $branchQuery->groupEnd();
        $branches = $branchQuery->groupEnd()->orderBy('b.company_id')->orderBy('b.branch_name')->get()->getResultArray();
        return [
            'audiences' => array_column($this->db->table('mod_module_audiences')->select('audience_code')->where('module_id', $moduleId)->get()->getResultArray(), 'audience_code'),
            'administrators' => array_map('intval', array_column($this->db->table('mod_module_administrators')->select('user_id')->where('module_id', $moduleId)->get()->getResultArray(), 'user_id')),
            'actions' => $this->db->table('mod_module_actions')->where('module_id', $moduleId)->orderBy('action_code')->get()->getResultArray(),
            'company_scopes' => $assignedCompanies,
            'branch_scopes' => array_map(static fn (array $row): string => (int) $row['company_id'] . ':' . (int) $row['branch_id'], $assignedBranches),
            'companies' => $companies,
            'branches' => $branches,
        ];
    }

    /** Save configuration metadata without creating any permission grants. */
    public function saveConfiguration(int $moduleId, array $input, int $actorId): void
    {
        $module = $this->find($moduleId);
        if ($module === null) throw new DomainException('The requested module was not found.');
        $audiences = array_values(array_unique(array_filter((array) ($input['audiences'] ?? []), 'is_string')));
        sort($audiences);
        if ($audiences === [] || array_diff($audiences, ['SUPERADMIN', 'USER', 'VIEWER']) !== []) throw new DomainException('Select one or more valid module audiences.');
        $adminIds = array_values(array_unique(array_map('intval', array_filter((array) ($input['administrators'] ?? []), static fn ($v) => is_scalar($v) && ctype_digit((string) $v) && (int) $v > 0))));
        foreach ($adminIds as $adminId) {
            $account = $this->db->table('usr_accounts a')->select('a.user_id')->join('usr_roles r', 'r.role_id = a.role_id')
                ->where('a.user_id', $adminId)->where('a.account_status', 'ACTIVE')->where('r.status', 'ACTIVE')->get()->getRowArray();
            if ($account === null) throw new DomainException('Module administrators must be active accounts with active roles.');
        }
        $actions = (array) ($input['actions'] ?? []);
        $scopeConfiguration = array_key_exists('module_scope_configured', $input) || array_key_exists('company_scopes', $input) || array_key_exists('branch_scopes', $input);
        $companyScopes = $scopeConfiguration ? $this->normalizeIds((array) ($input['company_scopes'] ?? []), 'company') : [];
        $branchScopes = $scopeConfiguration ? $this->normalizeBranchScopes((array) ($input['branch_scopes'] ?? [])) : [];
        if ($scopeConfiguration) $this->validateModuleScopes($moduleId, $companyScopes, $branchScopes);
        $this->db->transBegin();
        try {
            $this->db->table('mod_module_audiences')->where('module_id', $moduleId)->delete();
            foreach ($audiences as $audience) $this->db->table('mod_module_audiences')->insert(['module_id' => $moduleId, 'audience_code' => $audience]);
            $this->db->table('mod_module_administrators')->where('module_id', $moduleId)->delete();
            foreach ($adminIds as $adminId) $this->db->table('mod_module_administrators')->insert(['module_id' => $moduleId, 'user_id' => $adminId, 'assigned_by' => $actorId]);
            if ($scopeConfiguration) {
                $this->db->table('mod_module_companies')->where('module_id', $moduleId)->delete();
                $this->db->table('mod_module_branches')->where('module_id', $moduleId)->delete();
                foreach ($companyScopes as $companyId) $this->db->table('mod_module_companies')->insert(['module_id' => $moduleId, 'company_id' => $companyId]);
                foreach ($branchScopes as [$companyId, $branchId]) $this->db->table('mod_module_branches')->insert(['module_id' => $moduleId, 'company_id' => $companyId, 'branch_id' => $branchId]);
            }
            foreach ($actions as $action) {
                if (!is_array($action)) continue;
                $code = strtoupper(trim($this->scalarString($action['action_code'] ?? '')));
                $name = trim($this->scalarString($action['action_name'] ?? ''));
                $description = trim($this->scalarString($action['action_description'] ?? ''));
                $handler = trim($this->scalarString($action['handler_key'] ?? ''));
                $approval = strtoupper(trim($this->scalarString($action['approval_mode'] ?? 'NONE')));
                if (!preg_match('/^[A-Z][A-Z0-9_.]{1,49}$/', $code) || $name === '' || mb_strlen($name) > 120 || mb_strlen($description) > 500) throw new DomainException('Each action requires a valid stable key and display name.');
                if (!in_array($approval, ['NONE', 'HIERARCHY', 'ADMIN', 'COMBINED'], true)) throw new DomainException('Select a valid approval mode.');
                if ((self::ACTION_HANDLERS[$module['module_code']][$code] ?? null) !== $handler) throw new DomainException('The selected action does not map to an explicitly approved server-side handler.');
                $existing = $this->db->table('mod_module_actions')->select('module_action_id')->where(['module_id' => $moduleId, 'action_code' => $code])->get()->getRowArray();
                $enabled = in_array($module['module_code'], ['EMPLOYEE_MANAGEMENT', 'OUTLET_LIST'], true)
                    && in_array((string) ($action['is_enabled'] ?? ''), ['1', 'on', 'true'], true);
                if ($module['module_code'] === 'EMPLOYEE_MANAGEMENT' && $code === 'MANAGE_ACCESS' && $enabled && $approval === 'NONE') {
                    throw new DomainException('Account access actions must use an approval workflow before they can be enabled.');
                }
                if ($module['module_code'] === 'EMPLOYEE_MANAGEMENT' && $code !== 'MANAGE_ACCESS' && $approval !== 'NONE') {
                    throw new DomainException('This action does not support an approval workflow.');
                }
                $data = ['action_name' => $name, 'action_description' => $description, 'handler_key' => $handler, 'approval_mode' => $approval, 'requires_approval' => $approval === 'NONE' ? 0 : 1, 'is_enabled' => $enabled ? 1 : 0];
                if ($existing) $this->db->table('mod_module_actions')->where('module_action_id', $existing['module_action_id'])->update($data);
                else $this->db->table('mod_module_actions')->insert($data + ['module_id' => $moduleId, 'action_code' => $code]);
            }
            if (!$this->db->transStatus()) throw new DomainException('Module configuration could not be saved.');
            $this->audit($actorId, $moduleId, 'MODULE_CONFIGURATION_UPDATED', null, json_encode(['audiences' => $audiences, 'administrator_count' => count($adminIds), 'action_count' => count($actions), 'company_scope_count' => count($companyScopes), 'branch_scope_count' => count($branchScopes)], JSON_THROW_ON_ERROR));
            $this->commit();
        } catch (Throwable $exception) { $this->db->transRollback(); throw $exception; }
    }

    /** @return list<int> */
    private function normalizeIds(array $values, string $label): array
    {
        $ids = [];
        foreach ($values as $value) {
            if (!is_scalar($value) || !ctype_digit((string) $value) || (int) $value < 1) throw new DomainException('Choose valid ' . $label . ' scope records.');
            $ids[] = (int) $value;
        }
        return array_values(array_unique($ids));
    }

    /** @return list<array{0:int,1:int}> */
    private function normalizeBranchScopes(array $values): array
    {
        $pairs = [];
        foreach ($values as $value) {
            if (!is_string($value) || !preg_match('/^([1-9][0-9]*):([1-9][0-9]*)$/', $value, $matches)) throw new DomainException('Choose valid branch scope records.');
            $pairs[] = [(int) $matches[1], (int) $matches[2]];
        }
        $unique = [];
        foreach ($pairs as [$companyId, $branchId]) $unique[$companyId . ':' . $branchId] = [$companyId, $branchId];
        return array_values($unique);
    }

    /** @param list<int> $companyIds @param list<array{0:int,1:int}> $branchScopes */
    private function validateModuleScopes(int $moduleId, array $companyIds, array $branchScopes): void
    {
        $oldCompanyIds = array_map('intval', array_column($this->db->table('mod_module_companies')->select('company_id')->where('module_id', $moduleId)->get()->getResultArray(), 'company_id'));
        $oldBranchRows = $this->db->table('mod_module_branches')->select('company_id,branch_id')->where('module_id', $moduleId)->get()->getResultArray();
        $oldBranches = [];
        foreach ($oldBranchRows as $row) $oldBranches[(int) $row['company_id'] . ':' . (int) $row['branch_id']] = true;
        foreach ($companyIds as $companyId) {
            $company = $this->db->table('org_companies')->select('status')->where('company_id', $companyId)->get()->getRowArray();
            if ($company === null || ($company['status'] !== 'ACTIVE' && !in_array($companyId, $oldCompanyIds, true))) throw new DomainException('Choose active companies for module scope.');
        }
        foreach ($branchScopes as [$companyId, $branchId]) {
            if (!in_array($companyId, $companyIds, true)) throw new DomainException('Each branch scope must belong to a selected company.');
            $branch = $this->db->table('org_branches')->select('status')->where(['company_id' => $companyId, 'branch_id' => $branchId])->get()->getRowArray();
            $key = $companyId . ':' . $branchId;
            if ($branch === null || ($branch['status'] !== 'ACTIVE' && !isset($oldBranches[$key]))) throw new DomainException('Choose active branches that belong to the selected companies.');
            if (!isset($oldBranches[$key]) && $this->db->table('org_companies')->where(['company_id' => $companyId, 'status' => 'ACTIVE'])->countAllResults() !== 1) throw new DomainException('New branch scopes require an active company.');
        }
    }

    /** @return list<array<string, mixed>> */
    public function parentOptions(?int $exceptModuleId = null): array
    {
        $builder = $this->db->table('mod_modules')->select('module_id, module_code, module_name')
            ->orderBy('module_name', 'ASC');
        if ($exceptModuleId !== null) {
            $builder->where('module_id !=', $exceptModuleId);
        }

        return $builder->get()->getResultArray();
    }

    /** @param array<string, mixed> $input */
    public function create(array $input, int $actorId): int
    {
        $data = $this->normalize($input, true);
        $this->db->transBegin();
        try {
            $tree = $this->lockHierarchy();
            $this->validate($data, $tree, null);
            $data['created_by'] = $actorId;
            $data['created_at'] = date('Y-m-d H:i:s');
            if (!$this->db->table('mod_modules')->insert($data)) {
                throw new DomainException('The module could not be saved. Please try again.');
            }

            $moduleId = (int) $this->db->insertID();
            $after = $this->auditSnapshot($data);
            $this->audit($actorId, $moduleId, 'MODULE_CREATED', null, $after);
            $this->commit();
            return $moduleId;
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    /** @param array<string, mixed> $input */
    public function update(int $moduleId, array $input, int $actorId): void
    {
        if ($moduleId < 1) {
            throw new DomainException('The requested module was not found.');
        }

        $data = $this->normalize($input, false);
        $this->db->transBegin();
        try {
            $tree = $this->lockHierarchy();
            $before = $this->find($moduleId);
            if ($before === null) {
                throw new DomainException('The requested module was not found.');
            }
            $this->validate($data, $tree, $moduleId);
            $this->db->table('mod_modules')->where('module_id', $moduleId)->update($data);
            if (!$this->db->transStatus()) {
                throw new DomainException('The module could not be saved. Please try again.');
            }

            $after = array_merge($before, $data);
            $this->audit($actorId, $moduleId, 'MODULE_UPDATED', $this->auditSnapshot($before), $this->auditSnapshot($after));
            $this->commit();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function changeStatus(int $moduleId, string $status, int $actorId): void
    {
        $status = strtoupper(trim($status));
        if ($moduleId < 1 || !in_array($status, self::STATUSES, true)) {
            throw new DomainException('Select a valid module status.');
        }

        $this->db->transBegin();
        try {
            $this->lockHierarchy();
            $module = $this->find($moduleId);
            if ($module === null) {
                throw new DomainException('The requested module was not found.');
            }
            if ($status === 'ACTIVE' && $module['module_code'] === 'MODULE_REGISTRY' && $module['route_path'] !== '/superadmin/modules') {
                throw new DomainException('This module cannot be activated until a developer-maintained route and handler are available.');
            }
            if ($status === 'ACTIVE' && $module['module_code'] === 'EMPLOYEE_MANAGEMENT'
                && ($module['route_path'] !== '/user-management/users' || !$this->employeeManagementHandlersReady($moduleId))) {
                throw new DomainException('Employee Management requires its approved route and enabled action handlers before activation.');
            }
            if ($status === 'ACTIVE' && $module['module_code'] === 'OUTLET_LIST'
                && ($module['route_path'] !== '/outlets' || !$this->outletListHandlersReady($moduleId))) {
                throw new DomainException('Outlet List requires its approved route and enabled action handlers before activation.');
            }
            if ($status === 'ACTIVE' && !in_array($module['module_code'], ['MODULE_REGISTRY', 'EMPLOYEE_MANAGEMENT', 'OUTLET_LIST'], true)) {
                throw new DomainException('This module cannot be activated until a developer-maintained route and handler are available.');
            }

            $this->db->table('mod_modules')->where('module_id', $moduleId)->update(['status' => $status]);
            if (!$this->db->transStatus()) {
                throw new DomainException('The module status could not be changed. Please try again.');
            }

            $after = $module;
            $after['status'] = $status;
            $this->audit($actorId, $moduleId, 'MODULE_STATUS_CHANGED', $this->auditSnapshot($module), $this->auditSnapshot($after));
            $this->commit();
        } catch (Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function recordRejectedAttempt(int $actorId, string $eventCode): void
    {
        if ($actorId < 1 || !in_array($eventCode, ['MODULE_CREATE_REJECTED', 'MODULE_UPDATE_REJECTED', 'MODULE_STATUS_REJECTED', 'MODULE_CONFIG_REJECTED'], true)) {
            return;
        }
        try {
            if ($this->db->tableExists('log_audit_logs')) {
                $this->db->table('log_audit_logs')->insert([
                    'actor_user_id' => $actorId,
                    'module_id' => null,
                    'event_code' => $eventCode,
                    'result' => 'FAILURE',
                    'before_data' => null,
                    'after_data' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        } catch (Throwable) {
            log_message('error', 'A rejected Module Registry attempt could not be audited.');
        }
    }

    /** @return array<string, mixed> */
    private function moduleQuery(array $filters): object
    {
        $query = $this->db->table('mod_modules m')
            ->join('mod_modules parent', 'parent.module_id = m.parent_module_id', 'left');
        $search = trim($this->scalarString($filters['q'] ?? ''));
        if ($search !== '') {
            $query->groupStart()->like('m.module_name', $search)->orLike('m.module_code', $search)->groupEnd();
        }
        $type = strtoupper(trim($this->scalarString($filters['type'] ?? '')));
        if (in_array($type, self::TYPES, true)) {
            $query->where('m.module_type', $type);
        }
        $status = strtoupper(trim($this->scalarString($filters['status'] ?? '')));
        if (in_array($status, self::STATUSES, true)) {
            $query->where('m.status', $status);
        }
        return $query;
    }

    /** @return array<string, mixed> */
    private function normalize(array $input, bool $creating): array
    {
        $code = strtoupper(trim($this->scalarString($input['module_code'] ?? '')));
        $name = trim($this->scalarString($input['module_name'] ?? ''));
        $shortName = trim($this->scalarString($input['short_name'] ?? ''));
        $type = strtoupper(trim($this->scalarString($input['module_type'] ?? '')));
        $description = trim($this->scalarString($input['description'] ?? ''));
        $routePath = trim($this->scalarString($input['route_path'] ?? ''));
        $icon = trim($this->scalarString($input['icon'] ?? ''));
        $parentRaw = trim($this->scalarString($input['parent_module_id'] ?? ''));
        $sortRaw = trim($this->scalarString($input['sort_order'] ?? '0'));

        if (!preg_match('/^[A-Z][A-Z0-9_]{1,49}$/', $code)) {
            throw new DomainException('Module Code must use 2 to 50 uppercase letters, numbers, or underscores, and start with a letter.');
        }
        if ($name === '' || mb_strlen($name) > 120) {
            throw new DomainException('Module Name is required and must not exceed 120 characters.');
        }
        if ($shortName !== '' && mb_strlen($shortName) > 50) {
            throw new DomainException('Short Name must not exceed 50 characters.');
        }
        if (!in_array($type, self::TYPES, true)) {
            throw new DomainException('Select a valid Module Type.');
        }
        if (mb_strlen($description) > 500 || mb_strlen($routePath) > 191 || mb_strlen($icon) > 50) {
            throw new DomainException('One or more module fields exceed the allowed length.');
        }
        if ($icon !== '' && !preg_match('/^[a-zA-Z0-9_-]+$/', $icon)) {
            throw new DomainException('Icon must be a simple icon key.');
        }
        if ($routePath !== '' && !in_array($routePath, self::STATIC_ROUTES, true)) {
            throw new DomainException('Select an approved route identifier. Arbitrary paths and URLs are not accepted.');
        }
        if ($sortRaw === '' || !ctype_digit($sortRaw) || (int) $sortRaw > 65535) {
            throw new DomainException('Display Order must be a whole number from 0 to 65535.');
        }
        $parentId = null;
        if ($parentRaw !== '') {
            if (!ctype_digit($parentRaw) || (int) $parentRaw < 1) {
                throw new DomainException('Select a valid parent module.');
            }
            $parentId = (int) $parentRaw;
        }

        return [
            'parent_module_id' => $parentId,
            'module_code' => $code,
            'module_name' => $name,
            'short_name' => $shortName === '' ? null : $shortName,
            'module_type' => $type,
            'description' => $description === '' ? null : $description,
            'route_path' => $routePath === '' ? null : $routePath,
            'icon' => $icon === '' ? null : $icon,
            'sort_order' => (int) $sortRaw,
            'status' => $creating ? 'DRAFT' : null,
        ];
    }

    private function employeeManagementHandlersReady(int $moduleId): bool
    {
        foreach (self::ACTION_HANDLERS['EMPLOYEE_MANAGEMENT'] as $code => $handler) {
            $action = $this->db->table('mod_module_actions')->where(['module_id' => $moduleId, 'action_code' => $code])->get()->getRowArray();
            if ($action === null || !(bool) $action['is_enabled'] || $action['handler_key'] !== $handler) return false;
            if ($code === 'MANAGE_ACCESS' && ($action['approval_mode'] ?? 'NONE') === 'NONE') return false;
            if ($code !== 'MANAGE_ACCESS' && ($action['approval_mode'] ?? 'NONE') !== 'NONE') return false;
        }
        return true;
    }

    private function outletListHandlersReady(int $moduleId): bool
    {
        foreach (self::ACTION_HANDLERS['OUTLET_LIST'] as $code => $handler) {
            $action=$this->db->table('mod_module_actions')->where(['module_id'=>$moduleId,'action_code'=>$code])->get()->getRowArray();
            if($action===null || !(bool)$action['is_enabled'] || $action['handler_key']!==$handler) return false;
            if(in_array($code,['CREATE','UPDATE','DELETE'],true) && ($action['approval_mode']??'NONE')==='NONE') return false;
            if(in_array($code,['VIEW','APPROVE'],true) && ($action['approval_mode']??'NONE')!=='NONE') return false;
        }
        return true;
    }

    /** @param list<array<string, mixed>> $tree */
    private function validate(array &$data, array $tree, ?int $moduleId): void
    {
        if ($data['status'] === null) {
            unset($data['status']);
        }
        $duplicate = $this->db->table('mod_modules')->where('module_code', $data['module_code']);
        if ($moduleId !== null) {
            $duplicate->where('module_id !=', $moduleId);
        }
        if ($duplicate->countAllResults() > 0) {
            throw new DomainException('Module Code is already in use.');
        }

        $parentId = $data['parent_module_id'];
        if ($parentId === null) {
            return;
        }
        if ($moduleId !== null && $parentId === $moduleId) {
            throw new DomainException('A module cannot be its own parent.');
        }
        $parents = [];
        foreach ($tree as $row) {
            $parents[(int) $row['module_id']] = $row['parent_module_id'] === null ? null : (int) $row['parent_module_id'];
        }
        if (!array_key_exists($parentId, $parents)) {
            throw new DomainException('Select an existing parent module.');
        }
        $seen = [];
        $cursor = $parentId;
        while ($cursor !== null) {
            if ($cursor === $moduleId || isset($seen[$cursor])) {
                throw new DomainException('This parent selection would create a circular hierarchy.');
            }
            $seen[$cursor] = true;
            $cursor = $parents[$cursor] ?? null;
        }
    }

    /** @return list<array<string, mixed>> */
    private function lockHierarchy(): array
    {
        $driver = strtolower((string) $this->db->DBDriver);
        $sql = 'SELECT module_id, parent_module_id FROM mod_modules';
        if (str_contains($driver, 'mysqli') || str_contains($driver, 'mysql')) {
            $sql .= ' FOR UPDATE';
        }
        return $this->db->query($sql)->getResultArray();
    }

    private function audit(int $actorId, int $moduleId, string $event, ?string $before, ?string $after): void
    {
        $this->db->table('log_audit_logs')->insert([
            'actor_user_id' => $actorId,
            'module_id' => $moduleId,
            'event_code' => $event,
            'result' => 'SUCCESS',
            'before_data' => $before,
            'after_data' => $after,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        if (!$this->db->transStatus()) {
            throw new DomainException('The change could not be audited and was not saved. Please try again.');
        }
    }

    /** @param array<string, mixed> $data */
    private function auditSnapshot(array $data): string
    {
        $safe = array_intersect_key($data, array_flip(self::AUDIT_FIELDS));
        return json_encode($safe, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function scalarString(mixed $value): string
    {
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : '';
    }

    private function commit(): void
    {
        if (!$this->db->transStatus() || !$this->db->transCommit()) {
            throw new DomainException('The change could not be completed. Please try again.');
        }
    }
}
