<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DomainException;

final class UserManagementService
{
    private const PROFILE_FIELDS = ['first_name', 'last_name', 'birthday'];

    public function __construct(
        private readonly BaseConnection $db,
        private readonly OutletAccessService $access,
        private readonly AuditLogService $audit,
    ) {
    }

    /** @return array{items: list<array<string,mixed>>,total:int,page:int,perPage:int} */
    public function listAccounts(int $actorId, array $filters = [], int $page = 1): array
    {
        $this->assertAccountListAccess($actorId);
        $query = $this->db->table('usr_accounts a')
            ->select('a.user_id,a.username,a.employee_code,a.first_name,a.last_name,a.email,a.role_id,a.account_status,a.position_id,a.created_at,r.role_code,r.role_name')
            ->join('usr_roles r', 'r.role_id=a.role_id', 'left');
        if ($this->db->fieldExists('department_id', 'usr_accounts')) {
            $query->select('a.department_id');
        }

        if (!$this->access->isSuperadmin($actorId)) {
            $ids = $this->visibleDirectReportIds($actorId);
            $query->whereIn('a.user_id', $ids ?: [-1]);
        }
        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $query->groupStart()
                ->like('a.first_name', $term)->orLike('a.last_name', $term)
                ->orLike('a.email', $term)->orLike('a.employee_code', $term)
                ->orLike('a.username', $term)->groupEnd();
        }
        $status = strtoupper(trim((string) ($filters['status'] ?? '')));
        if (in_array($status, ['PENDING', 'ACTIVE', 'SUSPENDED', 'INACTIVE'], true)) {
            $query->where('a.account_status', $status);
        }

        $count = clone $query;
        $total = $count->countAllResults();
        $perPage = 20;
        $page = max(1, min($page, max(1, (int) ceil($total / $perPage))));
        $items = $query->orderBy('a.last_name')->orderBy('a.first_name')->orderBy('a.user_id')
            ->get($perPage, ($page - 1) * $perPage)->getResultArray();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    /** @return array<string,mixed> */
    public function account(int $actorId, int $targetId): array
    {
        $this->assertCanView($actorId, $targetId);
        $query = $this->db->table('usr_accounts a')->select([
            'a.user_id', 'a.username', 'a.employee_code', 'a.first_name', 'a.last_name', 'a.avatar_url',
            'a.birthday', 'a.email', 'a.role_id', 'a.position_id', 'a.default_module_id', 'a.account_status',
            'a.created_at', 'a.updated_at', 'r.role_code', 'r.role_name', 'r.status AS role_status',
        ])->join('usr_roles r', 'r.role_id=a.role_id', 'left');

        if ($this->db->fieldExists('department_id', 'usr_accounts')) {
            $query->select('a.department_id');
        }
        if ($this->db->tableExists('org_positions')) {
            $query->select('p.position_name')->join('org_positions p', 'p.position_id=a.position_id', 'left');
        }
        if ($this->db->tableExists('org_departments') && $this->db->fieldExists('department_id', 'usr_accounts')) {
            $query->select('d.department_name')->join('org_departments d', 'd.department_id=a.department_id', 'left');
        }
        $account = $query->where('a.user_id', $targetId)->get()->getRowArray();
        if ($account === null) {
            throw new DomainException('The account is unavailable.');
        }
        $account['reports_to_user_id'] = (int) ($this->db->table('org_reporting_lines')->select('reports_to_user_id')->where('employee_user_id',$targetId)->get()->getRowArray()['reports_to_user_id'] ?? 0) ?: null;
        return $account;
    }

    /** Users may change only their own non-privileged profile values. */
    public function updateOwnProfile(int $actorId, int $targetId, array $input): void
    {
        if ($actorId < 1 || $actorId !== $targetId) {
            throw new DomainException('You may update only your own profile.');
        }
        $this->assertCanView($actorId, $targetId);
        $data = [];
        foreach (self::PROFILE_FIELDS as $field) {
            if (array_key_exists($field, $input)) {
                $value = trim((string) $input[$field]);
                if ($field === 'birthday' && $value !== '') {
                    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                    if (!$date || $date->format('Y-m-d') !== $value || $date > new \DateTimeImmutable('today')) {
                        throw new DomainException('Enter a valid birthday that is not in the future.');
                    }
                }
                if ($field !== 'birthday' && mb_strlen($value, 'UTF-8') > 100) {
                    throw new DomainException('Profile names must be 100 characters or fewer.');
                }
                $data[$field] = $value === '' ? null : $value;
            }
        }
        if ($data === []) {
            throw new DomainException('Update at least one profile field.');
        }

        $before = $this->db->table('usr_accounts')->select(self::PROFILE_FIELDS)->where('user_id', $targetId)->get()->getRowArray();
        if ($before === null) {
            throw new DomainException('The account is unavailable.');
        }
        $this->db->transBegin();
        try {
            $this->db->table('usr_accounts')->where('user_id', $targetId)->update($data + ['updated_at' => date('Y-m-d H:i:s')]);
            $after = array_replace($before, $data);
            $this->audit->recordChange($actorId, 'ACCOUNT_PROFILE_UPDATED', 'SUCCESS', $before, $after, $targetId);
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new DomainException('The profile could not be saved.');
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            if ($exception instanceof DomainException) {
                throw $exception;
            }
            throw new DomainException('The profile could not be saved. Please try again.');
        }
    }

    /** Immediate Superadmin lifecycle action; deactivation revokes the registered session. */
    public function setAccountStatus(int $actorId, int $targetId, string $status): void
    {
        if (!$this->access->isSuperadmin($actorId)) throw new DomainException('Only a Superadmin may change account status.');
        $status = strtoupper(trim($status));
        if (!in_array($status, ['ACTIVE', 'SUSPENDED', 'INACTIVE'], true)) throw new DomainException('Choose a valid account status.');
        if ($actorId === $targetId && $status !== 'ACTIVE') throw new DomainException('You cannot deactivate your own account.');
        $this->db->transBegin();
        try {
            $before = $this->db->query('SELECT a.user_id,a.account_status,a.role_id,r.role_code,r.status AS role_status FROM usr_accounts a LEFT JOIN usr_roles r ON r.role_id=a.role_id WHERE a.user_id=?' . ($this->db->DBDriver === 'MySQLi' ? ' FOR UPDATE' : ''), [$targetId])->getRowArray();
            if (!$before) throw new DomainException('The account is unavailable.');
            if ($status === 'ACTIVE' && $before['role_status'] !== 'ACTIVE') throw new DomainException('Assign an active role before activating this account.');
            if ($status === 'ACTIVE' && !(new \App\Models\UserAccountModel($this->db))->hasApplicationAccess($before)) throw new DomainException('Assign at least one active module action before activating this account.');
            if ($before['role_code'] === 'SUPERADMIN' && $before['account_status'] === 'ACTIVE' && $status !== 'ACTIVE') {
                $activeAdmins = $this->db->table('usr_accounts a')->join('usr_roles r', 'r.role_id=a.role_id')
                    ->where(['a.account_status' => 'ACTIVE', 'r.role_code' => 'SUPERADMIN', 'r.status' => 'ACTIVE'])->countAllResults();
                if ($activeAdmins <= 1) throw new DomainException('The last active Superadmin cannot be deactivated.');
            }
            $this->db->table('usr_accounts')->where('user_id', $targetId)->update(['account_status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
            if ($status !== 'ACTIVE') $this->db->table('sec_active_sessions')->where('user_id', $targetId)->delete();
            $this->audit->recordChange($actorId, 'ACCOUNT_STATUS_CHANGED', 'SUCCESS', ['account_status' => $before['account_status']], ['account_status' => $status], $targetId);
            if (!$this->db->transStatus() || !$this->db->transCommit()) throw new DomainException('The account status could not be saved.');
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            if ($exception instanceof DomainException) throw $exception;
            throw new DomainException('The account status could not be saved. Please try again.');
        }
    }

    /** @return array{positions:list<array<string,mixed>>,departments:list<array<string,mixed>>}|null */
    public function organizationOptions(int $actorId, int $targetId): ?array
    {
        if (!$this->access->isSuperadmin($actorId)) throw new DomainException('Only a Superadmin may assign positions and departments.');
        if (!$this->db->tableExists('org_positions') || !$this->db->tableExists('org_departments') || !$this->db->fieldExists('department_id','usr_accounts')) return null;
        $account=$this->db->table('usr_accounts')->select('position_id,department_id')->where('user_id',$targetId)->get()->getRowArray();
        if(!$account) throw new DomainException('The account is unavailable.');
        $positions=$this->db->table('org_positions')->groupStart()->where('status','ACTIVE')->orWhere('position_id',$account['position_id'])->groupEnd()->orderBy('position_name')->get()->getResultArray();
        $departments=$this->db->table('org_departments')->groupStart()->where('status','ACTIVE')->orWhere('department_id',$account['department_id'])->groupEnd()->orderBy('department_name')->get()->getResultArray();
        return ['positions'=>$positions,'departments'=>$departments];
    }

    public function updateOrganizationAssignment(int $actorId, int $targetId, ?int $positionId, ?int $departmentId): void
    {
        if (!$this->access->isSuperadmin($actorId)) throw new DomainException('Only a Superadmin may assign positions and departments.');
        if (!$this->db->tableExists('org_positions') || !$this->db->tableExists('org_departments') || !$this->db->fieldExists('department_id','usr_accounts')) {
            throw new DomainException('Position and department assignments will be available after the organization lookup database update is deployed.');
        }
        foreach ([['org_positions','position_id',$positionId],['org_departments','department_id',$departmentId]] as [$table,$key,$id]) {
            if ($id !== null && $id > 0 && $this->db->table($table)->where([$key=>$id,'status'=>'ACTIVE'])->countAllResults() !== 1) {
                throw new DomainException('Choose an active position and department.');
            }
        }
        $before=$this->db->table('usr_accounts')->select('position_id,department_id')->where('user_id',$targetId)->get()->getRowArray();
        if (!$before) throw new DomainException('The account is unavailable.');
        $after=['position_id'=>$positionId ?: null,'department_id'=>$departmentId ?: null];
        $this->db->transBegin();
        try {
            $this->db->table('usr_accounts')->where('user_id',$targetId)->update($after+['updated_at'=>date('Y-m-d H:i:s')]);
            $this->audit->recordChange($actorId,'ACCOUNT_ORGANIZATION_ASSIGNMENT_CHANGED','SUCCESS',$before,$after,$targetId);
            if (!$this->db->transStatus() || !$this->db->transCommit()) throw new DomainException('The account organization assignment could not be saved.');
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            if ($exception instanceof DomainException) throw $exception;
            throw new DomainException('The account organization assignment could not be saved. Please try again.');
        }
    }

    /** Submit privileged changes through the configured Employee Management approval action. */
    public function submitSensitiveChange(int $actorId, int $targetId, string $changeType, array $input): ?int
    {
        $this->assertCanView($actorId, $targetId);
        if ($actorId === $targetId) throw new DomainException('You cannot submit privileged changes for your own account.');
        if (!$this->access->isSuperadmin($actorId)
            && (!$this->access->allowed($actorId, 'EMPLOYEE_MANAGEMENT', 'MANAGE_ACCESS') || !$this->isDirectReport($actorId, $targetId))) {
            throw new DomainException('You may manage only direct reports within your authorized Employee Management access.');
        }
        $module = $this->db->table('mod_modules')->where('module_code', 'EMPLOYEE_MANAGEMENT')->get()->getRowArray();
        if (!$module || $module['status'] !== 'ACTIVE') throw new DomainException('Employee Management is not currently available.');
        $action = $this->db->table('mod_module_actions')->where(['module_id' => $module['module_id'], 'action_code' => 'MANAGE_ACCESS'])->get()->getRowArray();
        if (!$action || !(bool) $action['is_enabled']) throw new DomainException('Account access changes are not currently enabled.');
        $changeType = strtoupper(trim($changeType));
        if ($changeType !== 'MODULE_ACCESS') $input['target_module_id'] = (int) $module['module_id'];
        elseif ((int) ($input['target_module_id'] ?? 0) !== (int) $module['module_id'] && array_key_exists('employee_actions', $input)) $input['employee_module_id'] = (int) $module['module_id'];
        $input['change_type'] = $changeType;
        return $this->access->submit($actorId, (int) $module['module_id'], $action, $input, $targetId, null, null);
    }

    public function canManageSensitive(int $actorId, int $targetId): bool
    {
        if ($actorId < 1 || $targetId < 1 || $actorId === $targetId) return false;
        $module = $this->db->table('mod_modules')->where(['module_code'=>'EMPLOYEE_MANAGEMENT','status'=>'ACTIVE'])->get()->getRowArray();
        if (!$module) return false;
        $action = $this->db->table('mod_module_actions')->where(['module_id'=>$module['module_id'],'action_code'=>'MANAGE_ACCESS','is_enabled'=>1])->get()->getRowArray();
        if (!$action) return false;
        if ($this->access->isSuperadmin($actorId)) return true;
        return $this->isDirectReport($actorId,$targetId)
            && $this->sharesActiveScope($actorId,$targetId)
            && $this->access->allowed($actorId,'EMPLOYEE_MANAGEMENT','MANAGE_ACCESS');
    }

    /** @return array{roles:list<array<string,mixed>>,managers:list<array<string,mixed>>,modules:list<array<string,mixed>>} */
    public function sensitiveOptions(int $actorId, int $targetId): array
    {
        if (!$this->canManageSensitive($actorId,$targetId)) throw new DomainException('You are not authorized to manage this account.');
        $superadmin=$this->access->isSuperadmin($actorId);
        $roleQuery=$this->db->table('usr_roles')->where('status','ACTIVE')->orderBy('role_name');
        if (!$superadmin) $roleQuery->where('role_code !=','SUPERADMIN');
        $roles=$roleQuery->get()->getResultArray();
        if(!$superadmin) {
            $roles=array_values(array_filter($roles,function(array $role) use($actorId): bool {
                $grants=$this->db->table('rbac_role_module_actions rr')->select('m.module_code,a.action_code')
                    ->join('mod_module_actions a','a.module_action_id=rr.module_action_id')->join('mod_modules m','m.module_id=a.module_id')
                    ->where('rr.role_id',$role['role_id'])->get()->getResultArray();
                foreach($grants as $grant) if(!$this->access->allowed($actorId,(string)$grant['module_code'],(string)$grant['action_code'])) return false;
                return true;
            }));
        }
        $managerQuery=$this->db->table('usr_accounts a')->select('a.user_id,a.first_name,a.last_name,a.email')
            ->join('usr_roles r','r.role_id=a.role_id')->where(['a.account_status'=>'ACTIVE','r.status'=>'ACTIVE'])->orderBy('a.last_name')->orderBy('a.first_name');
        if(!$superadmin) {
            $visibleManagers=array_merge([$actorId],array_map('intval',array_column($this->db->table('org_reporting_lines')->select('employee_user_id')->where('reports_to_user_id',$actorId)->get()->getResultArray(),'employee_user_id')));
            $visibleManagers=array_values(array_filter($visibleManagers,fn(int $id): bool=>$id===$actorId || $this->sharesActiveScope($actorId,$id)));
            $managerQuery->whereIn('a.user_id',$visibleManagers);
        }
        $managers=array_values(array_filter($managerQuery->get()->getResultArray(),static fn(array $row): bool => (int)$row['user_id']!==$targetId));

        $modules=[];
        foreach($this->db->table('mod_modules')->where('status','ACTIVE')->orderBy('sort_order')->get()->getResultArray() as $module) {
            $actions=$this->db->table('mod_module_actions')->where(['module_id'=>$module['module_id'],'is_enabled'=>1])->orderBy('action_code')->get()->getResultArray();
            if(!$superadmin) $actions=array_values(array_filter($actions,fn(array $action): bool=>$this->access->allowed($actorId,(string)$module['module_code'],(string)$action['action_code'])));
            if($actions===[]) continue;
            $moduleId=(int)$module['module_id'];
            $companies=$this->db->table('mod_module_companies mc')->select('c.company_id,c.company_name')
                ->join('org_companies c','c.company_id=mc.company_id')->where(['mc.module_id'=>$moduleId,'c.status'=>'ACTIVE'])->orderBy('c.company_name')->get()->getResultArray();
            if(!$superadmin) {
                $allowedCompanies=array_map('intval',array_column($this->db->table('rbac_user_company_scopes')->select('company_id')->where(['user_id'=>$actorId,'module_id'=>$moduleId])->get()->getResultArray(),'company_id'));
                $companies=array_values(array_filter($companies,static fn(array $company): bool=>in_array((int)$company['company_id'],$allowedCompanies,true)));
            }
            $branches=$this->db->table('mod_module_branches mb')->select('b.branch_id,b.company_id,b.branch_name')
                ->join('org_branches b','b.company_id=mb.company_id AND b.branch_id=mb.branch_id')->join('org_companies c','c.company_id=b.company_id')
                ->where(['mb.module_id'=>$moduleId,'b.status'=>'ACTIVE','c.status'=>'ACTIVE'])->orderBy('b.branch_name')->get()->getResultArray();
            if(!$superadmin) $branches=array_values(array_filter($branches,fn(array $branch): bool=>$this->access->inScope($actorId,$moduleId,(int)$branch['company_id'],(int)$branch['branch_id'])));
            $actionIds=array_column($this->db->table('mod_module_actions')->select('module_action_id')->where('module_id',$moduleId)->get()->getResultArray(),'module_action_id');
            $selectedActions=$actionIds?array_map('strval',array_column($this->db->table('rbac_user_module_actions')->select('a.action_code')->join('mod_module_actions a','a.module_action_id=rbac_user_module_actions.module_action_id')->where('rbac_user_module_actions.user_id',$targetId)->whereIn('rbac_user_module_actions.module_action_id',$actionIds)->get()->getResultArray(),'action_code')):[];
            $selectedCompanies=array_map('intval',array_column($this->db->table('rbac_user_company_scopes')->select('company_id')->where(['user_id'=>$targetId,'module_id'=>$moduleId])->get()->getResultArray(),'company_id'));
            $selectedBranches=array_map('intval',array_column($this->db->table('rbac_user_branch_scopes')->select('branch_id')->where(['user_id'=>$targetId,'module_id'=>$moduleId])->get()->getResultArray(),'branch_id'));
            $modules[]=['module_id'=>$moduleId,'module_code'=>$module['module_code'],'module_name'=>$module['module_name'],'actions'=>$actions,'companies'=>$companies,'branches'=>$branches,'selected_actions'=>$selectedActions,'selected_companies'=>$selectedCompanies,'selected_branches'=>$selectedBranches];
        }
        return ['roles'=>$roles,'managers'=>$managers,'modules'=>$modules];
    }

    /** @return list<array<string,mixed>> */
    public function effectiveAccessPreview(int $actorId, int $targetId): array
    {
        $account = $this->account($actorId, $targetId);
        $rows = $this->db->table('mod_modules m')->select([
            'm.module_id', 'm.module_code', 'm.module_name', 'm.status AS module_status',
            'a.module_action_id', 'a.action_code', 'a.is_enabled', 'a.requires_company_scope', 'a.requires_branch_scope',
        ])->join('mod_module_actions a', 'a.module_id=m.module_id')->orderBy('m.sort_order')->orderBy('a.action_code')
            ->get()->getResultArray();

        $preview = [];
        foreach ($rows as $row) {
            if ($actorId !== $targetId && !$this->access->isSuperadmin($actorId)
                && !$this->sharesActiveModuleScope($actorId, $targetId, (int) $row['module_id'])) {
                continue;
            }
            $roleGrant = $this->hasGrant('rbac_role_module_actions', 'role_id', (int) $account['role_id'], (int) $row['module_action_id']);
            $userGrant = $this->hasGrant('rbac_user_module_actions', 'user_id', $targetId, (int) $row['module_action_id']);
            if (!$this->access->isSuperadmin($targetId) && !$roleGrant && !$userGrant) {
                continue;
            }
            $reasons = [];
            if ($account['account_status'] !== 'ACTIVE') $reasons[] = 'ACCOUNT_INACTIVE';
            if (($account['role_status'] ?? '') !== 'ACTIVE') $reasons[] = 'ROLE_INACTIVE';
            if ($row['module_status'] !== 'ACTIVE') $reasons[] = 'MODULE_INACTIVE';
            if (!(bool) $row['is_enabled']) $reasons[] = 'ACTION_DISABLED';
            if (($account['role_code'] ?? '') === 'VIEWER' && $row['action_code'] !== 'VIEW') $reasons[] = 'VIEWER_READ_ONLY';
            if (!$roleGrant) $reasons[] = 'ROLE_GRANT_MISSING';
            if (!$userGrant) $reasons[] = 'USER_GRANT_MISSING';
            $scopes = $this->effectiveScopes($targetId, (int) $row['module_id']);
            if ((bool) $row['requires_company_scope'] && $scopes['companies'] === []) $reasons[] = 'COMPANY_SCOPE_MISSING';
            if ((bool) $row['requires_branch_scope'] && $scopes['branches'] === []) $reasons[] = 'BRANCH_SCOPE_MISSING';
            $allowed = $reasons === [] || $this->access->isSuperadmin($targetId);
            if ($this->access->isSuperadmin($targetId)) $reasons = [];
            $preview[] = [
                'module_code' => $row['module_code'], 'module_name' => $row['module_name'],
                'action_code' => $row['action_code'], 'allowed' => $allowed,
                'denied_reasons' => $reasons, 'company_ids' => $scopes['companies'], 'branch_ids' => $scopes['branches'],
            ];
        }
        return $preview;
    }

    /** @return list<array<string,mixed>> */
    public function accountHistory(int $actorId, int $targetId, int $limit = 50): array
    {
        $this->assertCanView($actorId, $targetId);
        if (!$this->db->fieldExists('target_user_id', 'log_audit_logs')) {
            return [];
        }
        $rows = $this->db->table('log_audit_logs')->select('audit_log_id,actor_user_id,event_code,result,before_data,after_data,created_at')
            ->where('target_user_id', $targetId)->orderBy('created_at', 'DESC')->orderBy('audit_log_id', 'DESC')
            ->get(max(1, min($limit, 100)))->getResultArray();
        foreach ($rows as &$row) {
            $row['before_data'] = $this->safeAuditData($row['before_data']);
            $row['after_data'] = $this->safeAuditData($row['after_data']);
        }
        unset($row);
        return $rows;
    }

    private function assertAccountListAccess(int $actorId): void
    {
        if ($this->access->isSuperadmin($actorId)) return;
        if (!$this->access->allowed($actorId, 'EMPLOYEE_MANAGEMENT', 'VIEW')) {
            throw new DomainException('You are not authorized to view account management.');
        }
    }

    private function assertCanView(int $actorId, int $targetId): void
    {
        if ($actorId < 1 || $targetId < 1) throw new DomainException('The account is unavailable.');
        if ($actorId === $targetId || $this->access->isSuperadmin($actorId)) return;
        if (!$this->access->allowed($actorId, 'EMPLOYEE_MANAGEMENT', 'VIEW')
            || !$this->isDirectReport($actorId, $targetId) || !$this->sharesActiveScope($actorId, $targetId)) {
            throw new DomainException('The account is unavailable.');
        }
    }

    /** @return list<int> */
    private function visibleDirectReportIds(int $actorId): array
    {
        $ids = array_map('intval', array_column($this->db->table('org_reporting_lines')->select('employee_user_id')
            ->where('reports_to_user_id', $actorId)->get()->getResultArray(), 'employee_user_id'));
        return array_values(array_filter($ids, fn (int $id): bool => $this->sharesActiveScope($actorId, $id)));
    }

    private function isDirectReport(int $actorId, int $targetId): bool
    {
        return $this->db->table('org_reporting_lines')->where(['employee_user_id' => $targetId, 'reports_to_user_id' => $actorId])->countAllResults() === 1;
    }

    private function sharesActiveScope(int $actorId, int $targetId): bool
    {
        $module=$this->db->table('mod_modules')->select('module_id')->where(['module_code'=>'EMPLOYEE_MANAGEMENT','status'=>'ACTIVE'])->get()->getRowArray();
        return $module !== null && $this->sharesActiveModuleScope($actorId,$targetId,(int)$module['module_id']);
    }

    private function sharesActiveModuleScope(int $actorId, int $targetId, int $moduleId): bool
    {
        $sql = 'SELECT 1 FROM mod_modules m '
            . 'JOIN mod_module_companies mc ON mc.module_id=m.module_id '
            . 'JOIN mod_module_branches mb ON mb.module_id=m.module_id AND mb.company_id=mc.company_id '
            . 'JOIN org_companies c ON c.company_id=mc.company_id AND c.status=? '
            . 'JOIN org_branches b ON b.company_id=mb.company_id AND b.branch_id=mb.branch_id AND b.status=? '
            . 'JOIN rbac_user_company_scopes ac ON ac.user_id=? AND ac.module_id=m.module_id AND ac.company_id=mc.company_id '
            . 'JOIN rbac_user_company_scopes tc ON tc.user_id=? AND tc.module_id=m.module_id AND tc.company_id=mc.company_id '
            . 'JOIN rbac_user_branch_scopes ab ON ab.user_id=? AND ab.module_id=m.module_id AND ab.company_id=mc.company_id AND ab.branch_id=mb.branch_id '
            . 'JOIN rbac_user_branch_scopes tb ON tb.user_id=? AND tb.module_id=m.module_id AND tb.company_id=mc.company_id AND tb.branch_id=mb.branch_id '
            . 'WHERE m.module_id=? AND m.status=? LIMIT 1';
        return $this->db->query($sql, ['ACTIVE', 'ACTIVE', $actorId, $targetId, $actorId, $targetId, $moduleId, 'ACTIVE'])->getRowArray() !== null;
    }

    private function hasGrant(string $table, string $userColumn, int $userId, int $actionId): bool
    {
        return $this->db->table($table)->where([$userColumn => $userId, 'module_action_id' => $actionId])->countAllResults() > 0;
    }

    /** @return array{companies:list<int>,branches:list<int>} */
    private function effectiveScopes(int $userId, int $moduleId): array
    {
        $companies = $this->db->table('rbac_user_company_scopes u')->select('u.company_id')
            ->join('mod_module_companies m', 'm.module_id=u.module_id AND m.company_id=u.company_id')
            ->join('org_companies c', 'c.company_id=u.company_id')->where(['u.user_id' => $userId, 'u.module_id' => $moduleId, 'c.status' => 'ACTIVE'])
            ->get()->getResultArray();
        $branches = $this->db->table('rbac_user_branch_scopes u')->select('u.branch_id')
            ->join('mod_module_branches m', 'm.module_id=u.module_id AND m.company_id=u.company_id AND m.branch_id=u.branch_id')
            ->join('org_branches b', 'b.company_id=u.company_id AND b.branch_id=u.branch_id')
            ->join('org_companies c', 'c.company_id=u.company_id')
            ->where(['u.user_id' => $userId, 'u.module_id' => $moduleId, 'b.status' => 'ACTIVE', 'c.status' => 'ACTIVE'])
            ->get()->getResultArray();
        return ['companies' => array_map('intval', array_column($companies, 'company_id')), 'branches' => array_map('intval', array_column($branches, 'branch_id'))];
    }

    private function safeAuditData(?string $json): ?array
    {
        if ($json === null) return null;
        $data = json_decode($json, true);
        if (!is_array($data)) return null;
        $safe = [];
        foreach ($data as $key => $value) {
            if (preg_match('/password|secret|token|session|credential|authorization|cookie|hash/i', (string) $key)) continue;
            if (is_scalar($value) || $value === null) $safe[$key] = is_string($value) ? mb_substr($value, 0, 200, 'UTF-8') : $value;
            elseif (is_array($value)) $safe[$key] = $this->safeAuditData(json_encode($value));
        }
        return $safe;
    }
}

