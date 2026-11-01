<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DomainException;

final class OutletAccessService
{
    public function __construct(private readonly BaseConnection $db) {}

    public function activeUser(int $userId): ?array
    {
        return $this->db->table('usr_accounts a')->select('a.user_id,a.role_id,a.account_status,r.role_code,r.status AS role_status')
            ->join('usr_roles r','r.role_id=a.role_id')->where('a.user_id',$userId)->get()->getRowArray() ?: null;
    }

    public function isSuperadmin(int $userId): bool
    {
        $u=$this->activeUser($userId);
        return $u !== null && $u['account_status']==='ACTIVE' && $u['role_status']==='ACTIVE' && $u['role_code']==='SUPERADMIN';
    }

    public function allowed(int $userId,string $moduleCode,string $action): bool
    {
        $u=$this->activeUser($userId);
        if ($u===null || $u['account_status']!=='ACTIVE' || $u['role_status']!=='ACTIVE') return false;
        if ($this->isSuperadmin($userId)) return true;
        if (($u['role_code'] ?? '')==='VIEWER' && !in_array($action,['VIEW'],true)) return false;
        return $this->db->table('mod_modules m')->join('mod_module_actions a','a.module_id=m.module_id')
            ->join('rbac_role_module_actions rr','rr.module_action_id=a.module_action_id')
            ->join('rbac_user_module_actions ur','ur.module_action_id=a.module_action_id')
            ->where('m.module_code',$moduleCode)->where('m.status','ACTIVE')->where('a.action_code',$action)
            ->where('a.is_enabled',1)->where('rr.role_id',$u['role_id'])->where('ur.user_id',$userId)->countAllResults()>0;
    }

    public function moduleId(string $code): int
    {
        $row=$this->db->table('mod_modules')->select('module_id')->where('module_code',$code)->get()->getRowArray();
        if (!$row) throw new DomainException('The requested module is unavailable.');
        return (int)$row['module_id'];
    }

    public function assertAllowed(int $userId,string $module,string $action): void
    {
        if (!$this->allowed($userId,$module,$action)) {
            if($this->db->tableExists('log_audit_logs')) {
                try { $moduleRow=$this->db->table('mod_modules')->select('module_id')->where('module_code',$module)->get()->getRowArray(); $this->audit($userId,$moduleRow?(int)$moduleRow['module_id']:null,$module.'_'.$action.'_DENIED',null,['action'=>$action],'DENIED'); }
                catch(\Throwable $e) { log_message('error','Denied-access audit insert failed.'); }
            }
            throw new DomainException('You are not authorized to perform this action.');
        }
    }

    public function inScope(int $userId,int $moduleId,int $companyId,int $branchId): bool
    {
        if ($this->isSuperadmin($userId)) return true;
        $moduleCompany=$this->db->table('mod_module_companies')->where(['module_id'=>$moduleId,'company_id'=>$companyId])->countAllResults()>0;
        $userCompany=$this->db->table('rbac_user_company_scopes')->where(['user_id'=>$userId,'module_id'=>$moduleId,'company_id'=>$companyId])->countAllResults()>0;
        $moduleBranch=$this->db->table('mod_module_branches')->where(['module_id'=>$moduleId,'company_id'=>$companyId,'branch_id'=>$branchId])->countAllResults()>0;
        $userBranch=$this->db->table('rbac_user_branch_scopes')->where(['user_id'=>$userId,'module_id'=>$moduleId,'company_id'=>$companyId,'branch_id'=>$branchId])->countAllResults()>0;
        $active=$this->db->table('org_branches b')->join('org_companies c','c.company_id=b.company_id')->where(['b.branch_id'=>$branchId,'b.company_id'=>$companyId,'b.status'=>'ACTIVE','c.status'=>'ACTIVE'])->countAllResults()>0;
        return $moduleCompany && $userCompany && $moduleBranch && $userBranch && $active;
    }

    public function scopedOutlets(int $userId,int $moduleId,bool $includeArchived=false): array
    {
        $q=$this->db->table('out_outlets o')->select('o.outlet_id,o.outlet_code,o.outlet_name,o.company_id,o.branch_id,o.status,o.archived_at,c.company_name,b.branch_name')
            ->join('org_companies c','c.company_id=o.company_id')->join('org_branches b','b.branch_id=o.branch_id')
            ;
        if (!$includeArchived) $q->where('o.archived_at',null); else $q->where('o.archived_at IS NOT NULL',null,false);
        if (!$this->isSuperadmin($userId)) {
            $companies=$this->db->table('mod_module_companies mc')->select('mc.company_id')->join('rbac_user_company_scopes uc','uc.company_id=mc.company_id AND uc.module_id=mc.module_id')->where('mc.module_id',$moduleId)->where('uc.user_id',$userId)->get()->getResultArray();
            $branches=$this->db->table('mod_module_branches mb')->select('mb.branch_id')->join('rbac_user_branch_scopes ub','ub.module_id=mb.module_id AND ub.company_id=mb.company_id AND ub.branch_id=mb.branch_id')->where('mb.module_id',$moduleId)->where('ub.user_id',$userId)->get()->getResultArray();
            $q->whereIn('o.company_id',array_column($companies,'company_id') ?: [-1])->whereIn('o.branch_id',array_column($branches,'branch_id') ?: [-1]);
            $q->where('c.status','ACTIVE')->where('b.status','ACTIVE');
        }
        return $q->orderBy('c.company_name')->orderBy('b.branch_name')->orderBy('o.outlet_name')->get()->getResultArray();
    }

    public function audit(int $actor,?int $moduleId,string $event,?array $before,?array $after,string $result='SUCCESS',?int $targetUserId=null): void
    {
        $row=['actor_user_id'=>$actor,'module_id'=>$moduleId,'event_code'=>$event,'result'=>$result,
            'before_data'=>$before===null?null:json_encode($before,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'after_data'=>$after===null?null:json_encode($after,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)];
        if($targetUserId!==null && $this->db->fieldExists('target_user_id','log_audit_logs')) $row['target_user_id']=$targetUserId;
        $this->db->table('log_audit_logs')->insert($row);
        if (!$this->db->transStatus()) throw new DomainException('The change could not be audited and was not saved.');
    }

    public function action(int $moduleId,string $code): array
    {
        return $this->db->table('mod_module_actions')->where(['module_id'=>$moduleId,'action_code'=>$code])->get()->getRowArray() ?: [];
    }

    /** Creates ordered approvals or applies the change in this transaction. */
    public function submit(int $userId,int $moduleId,array $action,array $payload,?int $targetId,?int $companyId,?int $branchId): ?int
    {
        $module=$this->db->table('mod_modules')->select('module_code')->where('module_id',$moduleId)->get()->getRowArray();
        $moduleCode=(string)($module['module_code']??'');
        if ($moduleCode === 'EMPLOYEE_MANAGEMENT') {
            $payload=$this->sanitizeEmployeePayload($payload);
            $this->validateEmployeeChange($userId, (int) ($targetId ?? 0), $payload, $moduleId);
        } elseif ($moduleCode === 'OUTLET_LIST') {
            $payload=$this->sanitizeOutletPayload($payload);
            if($payload['_operation']==='') $payload['_operation']=strtoupper((string)($action['action_code']??''));
            $this->validateOutletChange($userId,$moduleId,(string)($action['action_code']??''),$payload,$targetId);
        }
        $globalRequired=$this->db->table('sys_mandatory_approval_policies')->where(['action_code'=>$action['action_code']??'','is_mandatory_global'=>1])->countAllResults()>0;
        $required=(bool)($action['requires_approval'] ?? false) || $globalRequired;
        if($moduleCode==='EMPLOYEE_MANAGEMENT' && !$this->isSuperadmin($userId) && !$required) {
            throw new DomainException('Sensitive account changes require a configured approval workflow. Please contact your administrator.');
        }
        if (!$required || ($this->isSuperadmin($userId) && !$globalRequired)) {
            $this->db->transBegin();
            try {
                if ($moduleCode==='EMPLOYEE_MANAGEMENT') $this->applyUserAccess($userId,(int)$targetId,(int)$payload['target_module_id'],$payload);
                else $this->apply($userId,$moduleId,$action['action_code'],$payload,$targetId);
                if (!$this->db->transCommit()) throw new DomainException('The change could not be saved.');
            }
            catch(\Throwable $e) { $this->db->transRollback(); throw $e; }
            return null;
        }
        $approvers=[];
        if (($action['approval_mode']??'NONE')==='ADMIN') {
            $approvers=$this->db->table('apr_module_approvers')->select('approver_user_id')->where('module_action_id',$action['module_action_id'])->orderBy('stage_order')->get()->getResultArray();
            $approvers=array_column($approvers,'approver_user_id');
        } elseif (($action['approval_mode']??'NONE')==='HIERARCHY') {
            $mgr=$this->db->table('org_reporting_lines')->select('reports_to_user_id')->where('employee_user_id',$userId)->get()->getRowArray();
            if ($mgr) $approvers[]=(int)$mgr['reports_to_user_id'];
            $admins=$this->db->table('apr_module_approvers')->select('approver_user_id')->where('module_action_id',$action['module_action_id'])->orderBy('stage_order')->get()->getResultArray();
            foreach (array_column($admins,'approver_user_id') as $id) if (!in_array((int)$id,$approvers,true)) $approvers[]=(int)$id;
        } elseif (($action['approval_mode']??'NONE')==='COMBINED') {
            // Preserve the existing direct-manager hierarchy boundary. COMBINED
            // then adds configured module administrators as the second sequence.
            $mgr=$this->db->table('org_reporting_lines')->select('reports_to_user_id')->where('employee_user_id',$userId)->get()->getRowArray();
            if ($mgr) $approvers[]=(int)$mgr['reports_to_user_id'];
            $admins=$this->db->table('mod_module_administrators')->select('user_id')->where('module_id',$moduleId)->orderBy('user_id')->get()->getResultArray();
            foreach (array_column($admins,'user_id') as $id) if (!in_array((int)$id,$approvers,true)) $approvers[]=(int)$id;
        }
        $eligible=[];
        foreach ($approvers as $id) {
            $id=(int)$id;
            if ($id===$userId || !$this->allowed($id,$moduleCode,'APPROVE') || ($companyId && !$this->isSuperadmin($id) && !$this->inScope($id,$moduleId,$companyId,(int)$branchId))) continue;
            if ($moduleCode==='EMPLOYEE_MANAGEMENT' && !$this->canApproveAccessScope($id,$payload,(int)($targetId??0))) continue;
            $eligible[]=$id;
        }
        if (!$eligible) throw new DomainException('This action cannot proceed because no approver is assigned. Please contact your administrator.');
        $this->db->transBegin();
        try {
            if($moduleCode==='EMPLOYEE_MANAGEMENT' && $targetId!==null) {
                $targetSql='SELECT user_id FROM usr_accounts WHERE user_id=?'.($this->db->DBDriver==='MySQLi'?' FOR UPDATE':'');
                if(!$this->db->query($targetSql,[$targetId])->getRowArray()) throw new DomainException('The target account is unavailable.');
                $pending=$this->db->table('apr_requests')->where(['target_type'=>'USER_ACCESS','target_record_id'=>$targetId,'request_status'=>'PENDING'])->countAllResults();
                if($pending>0) throw new DomainException('This account already has a pending access change. Wait until it is decided before submitting another.');
            }
            $code=bin2hex(random_bytes(18));
            $this->db->table('apr_requests')->insert(['request_code'=>$code,'module_action_id'=>$action['module_action_id'],'target_type'=>$moduleCode==='OUTLET_LIST'?'OUTLET':'USER_ACCESS','target_record_id'=>$targetId,'requester_user_id'=>$userId,'company_id'=>$companyId,'branch_id'=>$branchId,'request_status'=>'PENDING','requested_data'=>json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
            $requestId=(int)$this->db->insertID();
            foreach ($eligible as $i=>$id) $this->db->table('apr_request_stages')->insert(['approval_request_id'=>$requestId,'stage_order'=>$i+1,'approver_user_id'=>$id,'stage_status'=>$i===0?'PENDING':'WAITING']);
            $this->audit($userId,$moduleId,$moduleCode==='EMPLOYEE_MANAGEMENT'?'USER_ACCESS_CHANGE_SUBMITTED':'OUTLET_CHANGE_SUBMITTED',null,['approval_request_id'=>$requestId,'action'=>$action['action_code']],'SUCCESS',$moduleCode==='EMPLOYEE_MANAGEMENT'?$targetId:null);
            if (!$this->db->transCommit()) throw new DomainException('The approval request could not be saved.');
            return $requestId;
        } catch (\Throwable $e) { $this->db->transRollback(); throw $e; }
    }

    public function apply(int $userId,int $moduleId,string $action,array $data,?int $targetId): ?int
    {
        $now=date('Y-m-d H:i:s');
        $operation=(string)($data['_operation']??$action);
        unset($data['_operation']);
        $before=$targetId===null?null:$this->db->table('out_outlets')->where('outlet_id',$targetId)->get()->getRowArray();
        if ($operation==='CREATE') {
            $this->db->table('out_outlets')->insert($data+['created_by'=>$userId,'created_at'=>$now]);
            $targetId=(int)$this->db->insertID();
        } elseif ($operation==='UPDATE') {
            if (!$targetId || !$before || $before['archived_at']!==null) throw new DomainException('The outlet was not found.');
            $this->db->table('out_outlets')->where(['outlet_id'=>$targetId,'archived_at'=>null])->update($data+['updated_by'=>$userId,'updated_at'=>$now]);
        } elseif ($operation==='DELETE') {
            if (!$targetId || !$before || $before['archived_at']!==null) throw new DomainException('The outlet was not found.');
            $this->db->table('out_outlets')->where(['outlet_id'=>$targetId,'archived_at'=>null])->update(['archived_at'=>$now,'archived_by'=>$userId]);
        } elseif ($operation==='RESTORE') {
            if (!$targetId || !$before || $before['archived_at']===null) throw new DomainException('The outlet was not found.');
            $this->db->table('out_outlets')->where(['outlet_id'=>$targetId])->update(['archived_at'=>null,'archived_by'=>null,'updated_by'=>$userId,'updated_at'=>$now]);
        } else throw new DomainException('Unsupported outlet action.');
        if (!$this->db->transStatus()) throw new DomainException('The outlet could not be saved.');
        $after=$operation==='DELETE'?['outlet_id'=>$targetId,'archived'=>true]:($operation==='RESTORE'?['outlet_id'=>$targetId,'archived'=>false]:($data+['outlet_id'=>$targetId]));
        $this->audit($userId,$moduleId,'OUTLET_'.$operation,$before,$after);
        return $targetId;
    }

    public function decide(int $userId,int $requestId,string $decision,string $reason): void
    {
        $this->db->transBegin();
        try {
            $lockClause=$this->db->DBDriver==='MySQLi'?' FOR UPDATE':'';
            $req=$this->db->query('SELECT * FROM `apr_requests` WHERE `approval_request_id` = ? AND `request_status` = ?'.$lockClause,[$requestId,'PENDING'])->getRowArray();
            $stage=$this->db->query('SELECT * FROM `apr_request_stages` WHERE `approval_request_id` = ? AND `approver_user_id` = ? AND `stage_status` = ?'.$lockClause,[$requestId,$userId,'PENDING'])->getRowArray();
            if (!$stage || !$req) throw new DomainException('You are not authorized to decide this request.');
            $moduleCode=$this->db->table('mod_modules m')->select('m.module_code')->join('mod_module_actions a','a.module_id=m.module_id')->where('a.module_action_id',$req['module_action_id'])->get()->getRowArray()['module_code']??'';
            if ($req['requester_user_id']==$userId || !$this->allowed($userId,$moduleCode,'APPROVE')) throw new DomainException('You are not authorized to decide this request.');
            $decision=strtoupper($decision);
            if (!in_array($decision,['APPROVED','REJECTED'],true) || ($decision==='REJECTED' && trim($reason)==='')) throw new DomainException('Select a valid decision and provide a reason when rejecting.');
            $payload=json_decode($req['requested_data'],true,512,JSON_THROW_ON_ERROR);
            if ($decision === 'APPROVED') {
                if($req['target_type']==='USER_ACCESS' && $moduleCode==='EMPLOYEE_MANAGEMENT') {
                    $actionRow=$this->db->table('mod_module_actions')->where('module_action_id',$req['module_action_id'])->get()->getRowArray();
                    $this->validateEmployeeChange((int)$req['requester_user_id'],(int)$req['target_record_id'],$payload,(int)$actionRow['module_id']);
                }
                if ($req['company_id'] !== null && !$this->isSuperadmin($userId) && !$this->inScope($userId,$this->moduleId($moduleCode),(int)$req['company_id'],(int)$req['branch_id'])) throw new DomainException('You are not authorized to decide requests outside your data scope.');
                if ($req['target_type']==='USER_ACCESS' && !$this->canApproveAccessScope($userId,$payload,(int)$req['target_record_id'])) throw new DomainException('You are not authorized to decide requests outside your data scope.');
                if ($req['target_type']==='OUTLET') {
                    $actionRow=$this->db->table('mod_module_actions')->where('module_action_id',$req['module_action_id'])->get()->getRowArray();
                    $this->validateOutletChange((int)$req['requester_user_id'],$this->moduleId($moduleCode),(string)$actionRow['action_code'],$payload,(int)($req['target_record_id']??0) ?: null);
                }
            }
            $this->db->table('apr_request_stages')->where('approval_stage_id',$stage['approval_stage_id'])->update(['stage_status'=>$decision,'decision_reason'=>$reason,'decided_at'=>date('Y-m-d H:i:s')]);
            if ($decision==='REJECTED') $this->db->table('apr_requests')->where('approval_request_id',$requestId)->update(['request_status'=>'REJECTED']);
            else {
                $next=$this->db->table('apr_request_stages')->where('approval_request_id',$requestId)->where('stage_order >',(int)$stage['stage_order'])->orderBy('stage_order')->get()->getRowArray();
                if ($next) $this->db->table('apr_request_stages')->where('approval_stage_id',$next['approval_stage_id'])->update(['stage_status'=>'PENDING']);
                else {
                    $action=$this->db->table('mod_module_actions')->where('module_action_id',$req['module_action_id'])->get()->getRowArray();
                    $appliedId=null;
                    if ($req['target_type']==='USER_ACCESS') {
                        $this->applyUserAccess($userId,(int)$req['target_record_id'],(int)$payload['target_module_id'],$payload,(int)$req['requester_user_id']);
                    }
                    else $appliedId=$this->apply((int)$req['requester_user_id'],$this->moduleId($moduleCode),$action['action_code'],$payload,(int)($req['target_record_id']??0) ?: null);
                    $this->db->table('apr_requests')->where('approval_request_id',$requestId)->update(['request_status'=>'APPLIED','target_record_id'=>$appliedId??$req['target_record_id'],'applied_at'=>date('Y-m-d H:i:s')]);
                }
            }
            $this->audit($userId,$this->moduleId($moduleCode),'APPROVAL_'.$decision,null,['request_id'=>$requestId,'reason'=>$reason],'SUCCESS',$req['target_type']==='USER_ACCESS'?(int)$req['target_record_id']:null);
            if (!$this->db->transCommit()) throw new DomainException('The decision could not be saved.');
        } catch (\Throwable $e) { $this->db->transRollback(); throw $e; }
    }

    /** @return list<array<string,mixed>> Requests assigned to this eligible approver only. */
    public function pendingFor(int $approverId): array
    {
        $rows=$this->db->table('apr_requests r')->select('r.approval_request_id,r.target_type,r.target_record_id,r.requester_user_id,r.company_id,r.branch_id,r.requested_data,r.created_at,a.module_action_id,a.action_code,m.module_id,m.module_code,m.module_name,requester.first_name AS requester_first_name,requester.last_name AS requester_last_name,target.first_name AS target_first_name,target.last_name AS target_last_name')
            ->join('apr_request_stages s','s.approval_request_id=r.approval_request_id')
            ->join('mod_module_actions a','a.module_action_id=r.module_action_id')
            ->join('mod_modules m','m.module_id=a.module_id')
            ->join('usr_accounts requester','requester.user_id=r.requester_user_id')
            ->join("usr_accounts target", "target.user_id=r.target_record_id AND r.target_type='USER_ACCESS'", 'left')
            ->where(['r.request_status'=>'PENDING','s.approver_user_id'=>$approverId,'s.stage_status'=>'PENDING'])
            ->orderBy('r.created_at')->orderBy('r.approval_request_id')->get()->getResultArray();
        $result=[];
        foreach($rows as $row) {
            if((int)$row['requester_user_id']===$approverId || !$this->allowed($approverId,(string)$row['module_code'],'APPROVE')) continue;
            $payload=json_decode((string)$row['requested_data'],true);
            if(!is_array($payload)) continue;
            $canApprove=true;
            $validationError=null;
            if($row['company_id']!==null && !$this->isSuperadmin($approverId)
                && !$this->inScope($approverId,(int)$row['module_id'],(int)$row['company_id'],(int)$row['branch_id'])) {
                $canApprove=false;
                $validationError='Your current scope no longer permits approval. You may reject this request.';
            }
            if($row['target_type']==='USER_ACCESS') {
                if(!$this->canApproveAccessScope($approverId,$payload,(int)$row['target_record_id'])) { $canApprove=false; $validationError='The requested data scope is no longer eligible. You may reject this request.'; }
                try { $this->validateEmployeeChange((int)$row['requester_user_id'],(int)$row['target_record_id'],$payload,(int)$row['module_id']); }
                catch(DomainException) { $canApprove=false; $validationError='The requested change is no longer valid. You may reject this request.'; }
                $summary=$this->safeEmployeeSummary($payload);
            } else {
                $summary=[];
                foreach(['_operation','outlet_code','outlet_name','status'] as $field) if(isset($payload[$field]) && is_scalar($payload[$field])) $summary[$field]=mb_substr((string)$payload[$field],0,120,'UTF-8');
            }
            $result[]=[
                'request_id'=>(int)$row['approval_request_id'],'target_type'=>$row['target_type'],
                'target_id'=>(int)($row['target_record_id']??0),'requester'=>trim((string)$row['requester_first_name'].' '.(string)$row['requester_last_name']),
                'target'=>trim((string)$row['target_first_name'].' '.(string)$row['target_last_name']),
                'module_code'=>$row['module_code'],'module_name'=>$row['module_name'],'action_code'=>$row['action_code'],
                'created_at'=>$row['created_at'],'summary'=>$summary,'can_approve'=>$canApprove,'validation_error'=>$validationError,
            ];
        }
        return $result;
    }

    private function safeEmployeeSummary(array $payload): array
    {
        $type=(string)($payload['change_type']??'MODULE_ACCESS');
        $summary=['change_type'=>$type];
        if($type==='ROLE_ASSIGNMENT') $summary['role_id']=(int)($payload['role_id']??0);
        elseif($type==='REPORTING_LINE') $summary['reports_to_user_id']=(int)($payload['reports_to_user_id']??0)?:null;
        else {
            $summary['target_module_id']=(int)($payload['target_module_id']??0);
            $summary['actions']=array_values(array_map('strval',(array)($payload['actions']??[])));
            $summary['companies']=array_values(array_map('intval',(array)($payload['companies']??[])));
            $summary['branches']=array_values(array_map('intval',(array)($payload['branches']??[])));
            if(array_key_exists('employee_actions',$payload)) $summary['employee_management_actions']=array_values(array_map('strval',(array)$payload['employee_actions']));
        }
        return $summary;
    }

    public function applyUserAccess(int $actor,int $target,int $moduleId,array $payload,?int $authorityActor=null): void
    {
        $authorityActor ??= $actor;
        $changeType=strtoupper((string)($payload['change_type']??'MODULE_ACCESS'));
        if($changeType==='ROLE_ASSIGNMENT') { $this->applyRoleAssignment($actor,$target,$payload); return; }
        if($changeType==='REPORTING_LINE') { $this->applyReportingLine($actor,$target,$payload); return; }
        if($changeType!=='MODULE_ACCESS') throw new DomainException('Unsupported account change type.');
        $manageableActionIds=$this->manageableActionIds($authorityActor,$moduleId);
        $beforeActions=$manageableActionIds?array_column($this->db->table('rbac_user_module_actions')->select('module_action_id')->where('user_id',$target)->whereIn('module_action_id',$manageableActionIds)->get()->getResultArray(),'module_action_id'):[];
        $manageableCompanyIds=$this->manageableCompanyIds($authorityActor,$moduleId);
        $manageableBranchIds=$this->manageableBranchIds($authorityActor,$moduleId);
        $beforeCompanies=$manageableCompanyIds?array_column($this->db->table('rbac_user_company_scopes')->select('company_id')->where(['user_id'=>$target,'module_id'=>$moduleId])->whereIn('company_id',$manageableCompanyIds)->get()->getResultArray(),'company_id'):[];
        $beforeBranches=$manageableBranchIds?array_column($this->db->table('rbac_user_branch_scopes')->select('branch_id')->where(['user_id'=>$target,'module_id'=>$moduleId])->whereIn('branch_id',$manageableBranchIds)->get()->getResultArray(),'branch_id'):[];
        $requestedCodes=(array)($payload['actions']??[]);
        $requestedIds=$requestedCodes?array_column($this->db->table('mod_module_actions')->select('module_action_id')->where('module_id',$moduleId)->whereIn('action_code',$requestedCodes)->get()->getResultArray(),'module_action_id'):[];
        $employeeModuleId=(int)($payload['employee_module_id']??0);
        if($employeeModuleId===$moduleId || !array_key_exists('employee_actions',$payload)) $employeeModuleId=0;
        $employeeIds=$employeeModuleId>0?$this->manageableActionIds($authorityActor,$employeeModuleId):[];
        $beforeEmployeeActions=$employeeIds?array_column($this->db->table('rbac_user_module_actions')->select('module_action_id')->where('user_id',$target)->whereIn('module_action_id',$employeeIds)->get()->getResultArray(),'module_action_id'):[];
        $requestedEmployeeCodes=(array)($payload['employee_actions']??[]);
        $requestedEmployeeIds=$requestedEmployeeCodes?array_column($this->db->table('mod_module_actions')->select('module_action_id')->where('module_id',$employeeModuleId)->whereIn('action_code',$requestedEmployeeCodes)->get()->getResultArray(),'module_action_id'):[];
        $requestedCompanies=array_map('intval',(array)($payload['companies']??[]));
        $requestedBranches=array_map('intval',(array)($payload['branches']??[]));
        $accessReduced=(bool)array_diff(array_map('intval',$beforeActions),array_map('intval',$requestedIds))
            || (bool)array_diff(array_map('intval',$beforeEmployeeActions),array_map('intval',$requestedEmployeeIds))
            || (bool)array_diff(array_map('intval',$beforeCompanies),$requestedCompanies)
            || (bool)array_diff(array_map('intval',$beforeBranches),$requestedBranches);
        if($manageableActionIds) $this->db->table('rbac_user_module_actions')->where('user_id',$target)->whereIn('module_action_id',$manageableActionIds)->delete();
        foreach((array)($payload['actions']??[]) as $code){$a=$this->db->table('mod_module_actions')->where(['module_id'=>$moduleId,'action_code'=>$code])->get()->getRowArray();if($a)$this->db->table('rbac_user_module_actions')->insert(['user_id'=>$target,'module_action_id'=>$a['module_action_id'],'created_by'=>$actor]);}
        if($employeeModuleId>0){
            if($employeeIds) $this->db->table('rbac_user_module_actions')->where('user_id',$target)->whereIn('module_action_id',$employeeIds)->delete();
            foreach($requestedEmployeeCodes as $code){$a=$this->db->table('mod_module_actions')->where(['module_id'=>$employeeModuleId,'action_code'=>$code])->get()->getRowArray();if($a)$this->db->table('rbac_user_module_actions')->insert(['user_id'=>$target,'module_action_id'=>$a['module_action_id'],'created_by'=>$actor]);}
        }
        if($manageableCompanyIds) $this->db->table('rbac_user_company_scopes')->where(['user_id'=>$target,'module_id'=>$moduleId])->whereIn('company_id',$manageableCompanyIds)->delete();
        foreach((array)($payload['companies']??[]) as $companyId) $this->db->table('rbac_user_company_scopes')->insert(['user_id'=>$target,'module_id'=>$moduleId,'company_id'=>(int)$companyId,'created_by'=>$actor]);
        if($manageableBranchIds) $this->db->table('rbac_user_branch_scopes')->where(['user_id'=>$target,'module_id'=>$moduleId])->whereIn('branch_id',$manageableBranchIds)->delete();
        foreach((array)($payload['branches']??[]) as $branchId){$branch=$this->db->table('org_branches')->where('branch_id',(int)$branchId)->get()->getRowArray();if($branch)$this->db->table('rbac_user_branch_scopes')->insert(['user_id'=>$target,'module_id'=>$moduleId,'company_id'=>$branch['company_id'],'branch_id'=>$branchId,'created_by'=>$actor]);}
        if($accessReduced) $this->db->table('sec_active_sessions')->where('user_id',$target)->delete();
        $this->audit($actor,$moduleId,$accessReduced?'EMPLOYEE_ACCESS_REVOKED':'EMPLOYEE_MODULE_ACCESS_APPLIED',['action_ids'=>$beforeActions,'companies'=>$beforeCompanies,'branches'=>$beforeBranches],['actions'=>$payload['actions']??[],'employee_actions'=>$payload['employee_actions']??[],'companies'=>$payload['companies']??[],'branches'=>$payload['branches']??[]],'SUCCESS',$target);
    }

    private function manageableActionIds(int $actor,int $moduleId): array
    {
        $rows=$this->db->table('mod_module_actions a')->select('a.module_action_id,a.action_code')->join('mod_modules m','m.module_id=a.module_id')->where(['a.module_id'=>$moduleId,'a.is_enabled'=>1,'m.status'=>'ACTIVE'])->get()->getResultArray();
        if($this->isSuperadmin($actor)) return array_map('intval',array_column($rows,'module_action_id'));
        $module=$this->db->table('mod_modules')->select('module_code')->where('module_id',$moduleId)->get()->getRowArray();
        if(!$module) return [];
        $ids=[]; foreach($rows as $row) if($this->allowed($actor,(string)$module['module_code'],(string)$row['action_code'])) $ids[]=(int)$row['module_action_id'];
        return $ids;
    }

    private function manageableCompanyIds(int $actor,int $moduleId): array
    {
        $query=$this->db->table('mod_module_companies mc')->select('mc.company_id')->join('org_companies c','c.company_id=mc.company_id')->where(['mc.module_id'=>$moduleId,'c.status'=>'ACTIVE']);
        if(!$this->isSuperadmin($actor)) $query->join('rbac_user_company_scopes s','s.module_id=mc.module_id AND s.company_id=mc.company_id')->where('s.user_id',$actor);
        return array_map('intval',array_column($query->get()->getResultArray(),'company_id'));
    }

    private function manageableBranchIds(int $actor,int $moduleId): array
    {
        $rows=$this->db->table('mod_module_branches mb')->select('mb.company_id,mb.branch_id')->join('org_branches b','b.company_id=mb.company_id AND b.branch_id=mb.branch_id')->join('org_companies c','c.company_id=mb.company_id')->where(['mb.module_id'=>$moduleId,'b.status'=>'ACTIVE','c.status'=>'ACTIVE'])->get()->getResultArray();
        $ids=[]; foreach($rows as $row) if($this->isSuperadmin($actor) || $this->inScope($actor,$moduleId,(int)$row['company_id'],(int)$row['branch_id'])) $ids[]=(int)$row['branch_id'];
        return array_values(array_unique($ids));
    }

    private function sanitizeOutletPayload(array $payload): array
    {
        $clean=['_operation'=>strtoupper((string)($payload['_operation']??'')),'company_id'=>(int)($payload['company_id']??0),'branch_id'=>(int)($payload['branch_id']??0)];
        foreach(['outlet_code','outlet_name','status'] as $field) if(array_key_exists($field,$payload)) $clean[$field]=is_scalar($payload[$field])?trim((string)$payload[$field]):'';
        return $clean;
    }

    private function validateOutletChange(int $requester,int $moduleId,string $actionCode,array $payload,?int $targetId): void
    {
        $operation=strtoupper(trim((string)($payload['_operation']??'')));
        if($operation==='') $operation=strtoupper($actionCode);
        $validOperations=['CREATE'=>['CREATE'],'UPDATE'=>['UPDATE'],'DELETE'=>['DELETE','RESTORE']];
        if(!in_array($operation,$validOperations[strtoupper($actionCode)]??[],true)) throw new DomainException('The outlet request does not match its approved action.');
        $companyId=(int)($payload['company_id']??0); $branchId=(int)($payload['branch_id']??0);
        if($companyId<1 || $branchId<1 || !$this->inScope($requester,$moduleId,$companyId,$branchId)) throw new DomainException('The requested outlet is outside the authorized requester scope.');
        $activeBranch=$this->db->table('mod_module_branches mb')->join('org_branches b','b.company_id=mb.company_id AND b.branch_id=mb.branch_id')
            ->join('org_companies c','c.company_id=b.company_id')->where(['mb.module_id'=>$moduleId,'mb.company_id'=>$companyId,'mb.branch_id'=>$branchId,'b.status'=>'ACTIVE','c.status'=>'ACTIVE'])->countAllResults();
        if(!$activeBranch) throw new DomainException('The selected company and branch are no longer active for Outlet List.');
        if(in_array($operation,['CREATE','UPDATE'],true)) {
            $code=strtoupper(trim((string)($payload['outlet_code']??''))); $name=trim((string)($payload['outlet_name']??'')); $status=strtoupper(trim((string)($payload['status']??'')));
            if(!preg_match('/^[A-Z0-9][A-Z0-9._-]{0,49}$/',$code) || $name==='' || mb_strlen($name,'UTF-8')>150 || !in_array($status,['ACTIVE','INACTIVE'],true)) throw new DomainException('The requested outlet details are invalid.');
        }
        if($operation==='CREATE') { if($targetId!==null) throw new DomainException('A new outlet request cannot target an existing record.'); return; }
        if($targetId===null) throw new DomainException('The target outlet is unavailable.');
        $target=$this->db->table('out_outlets')->where(['outlet_id'=>$targetId,'company_id'=>$companyId,'branch_id'=>$branchId])->get()->getRowArray();
        if(!$target || (($operation==='RESTORE')!==($target['archived_at']!==null))) throw new DomainException('The target outlet changed while this request was pending.');
    }

    private function canApproveAccessScope(int $approver,array $payload,?int $targetId=null): bool
    {
        if($this->isSuperadmin($approver)) return true;
        $type=strtoupper((string)($payload['change_type']??'MODULE_ACCESS'));
        if($type!=='MODULE_ACCESS') return $targetId!==null && $this->sharesManagementScope($approver,$targetId);
        $moduleId=(int)($payload['target_module_id']??0);
        if($moduleId<1) return false;
        foreach((array)($payload['companies']??[]) as $companyId) {
            $companyId=(int)$companyId;
            if($this->db->table('mod_module_companies')->where(['module_id'=>$moduleId,'company_id'=>$companyId])->countAllResults()===0 || $this->db->table('rbac_user_company_scopes')->where(['user_id'=>$approver,'module_id'=>$moduleId,'company_id'=>$companyId])->countAllResults()===0) return false;
        }
        foreach((array)($payload['branches']??[]) as $branchId) {
            $branch=$this->db->table('org_branches')->where('branch_id',(int)$branchId)->get()->getRowArray();
            if(!$branch || !$this->inScope($approver,$moduleId,(int)$branch['company_id'],(int)$branchId)) return false;
        }
        if((array)($payload['employee_actions']??[])!==[]) {
            $employeeModuleId=(int)($payload['employee_module_id']??0);
            if($employeeModuleId<1) return false;
            foreach((array)($payload['companies']??[]) as $companyId) {
                if($this->db->table('mod_module_companies')->where(['module_id'=>$employeeModuleId,'company_id'=>(int)$companyId])->countAllResults()===0
                    || $this->db->table('rbac_user_company_scopes')->where(['user_id'=>$approver,'module_id'=>$employeeModuleId,'company_id'=>(int)$companyId])->countAllResults()===0) return false;
            }
            foreach((array)($payload['branches']??[]) as $branchId) {
                $branch=$this->db->table('org_branches')->where('branch_id',(int)$branchId)->get()->getRowArray();
                if(!$branch || !$this->inScope($approver,$employeeModuleId,(int)$branch['company_id'],(int)$branchId)) return false;
            }
        }
        return true;
    }

    private function validateEmployeeChange(int $actor,int $target,array $payload,int $employeeModuleId): void
    {
        if($target<1 || $target===$actor || $this->db->table('usr_accounts')->where('user_id',$target)->countAllResults()!==1) {
            throw new DomainException('Choose a valid account other than your own.');
        }
        $type=strtoupper((string)($payload['change_type']??'MODULE_ACCESS'));
        if(!in_array($type,['MODULE_ACCESS','ROLE_ASSIGNMENT','REPORTING_LINE'],true)) throw new DomainException('Unsupported account change type.');
        if($type!=='MODULE_ACCESS' && (int)($payload['target_module_id']??0)!==$employeeModuleId) {
            throw new DomainException('The account change request targets an invalid management module.');
        }
        if(!$this->isSuperadmin($actor)) {
            if(!$this->allowed($actor,'EMPLOYEE_MANAGEMENT','MANAGE_ACCESS')
                || $this->db->table('org_reporting_lines')->where(['employee_user_id'=>$target,'reports_to_user_id'=>$actor])->countAllResults()!==1) {
                throw new DomainException('You may manage only direct reports within your authorized Employee Management access.');
            }
        }
        if($type==='MODULE_ACCESS') {
            $moduleId=(int)($payload['target_module_id']??0);
            $module=$this->db->table('mod_modules')->where(['module_id'=>$moduleId,'status'=>'ACTIVE'])->get()->getRowArray();
            if(!$module || $moduleId<1) throw new DomainException('Choose an active module.');
            $actions=array_values(array_unique(array_map('strval',(array)($payload['actions']??[]))));
            $this->validateDelegatedActions($actor,$moduleId,$actions);
            $employeeModuleId=(int)($payload['employee_module_id']??0);
            $employeeActions=array_values(array_unique(array_map('strval',(array)($payload['employee_actions']??[]))));
            if(array_key_exists('employee_actions',$payload) && $employeeActions!==[]) {
                $this->validateDelegatedActions($actor,$employeeModuleId,$employeeActions);
            }
            $companies=array_values(array_unique(array_map('intval',(array)($payload['companies']??[]))));
            $branches=array_values(array_unique(array_map('intval',(array)($payload['branches']??[]))));
            foreach($companies as $companyId) {
                $valid=$this->db->table('mod_module_companies mc')->join('org_companies c','c.company_id=mc.company_id')
                    ->where(['mc.module_id'=>$moduleId,'mc.company_id'=>$companyId,'c.status'=>'ACTIVE'])->countAllResults()>0;
                if(!$valid || (!$this->isSuperadmin($actor) && $this->db->table('rbac_user_company_scopes')->where(['user_id'=>$actor,'module_id'=>$moduleId,'company_id'=>$companyId])->countAllResults()===0)) {
                    throw new DomainException('You cannot delegate a company outside your authorized scope.');
                }
            }
            foreach($branches as $branchId) {
                $branch=$this->db->table('org_branches b')->select('b.company_id')->join('mod_module_branches mb','mb.company_id=b.company_id AND mb.branch_id=b.branch_id')
                    ->where(['mb.module_id'=>$moduleId,'b.branch_id'=>$branchId,'b.status'=>'ACTIVE'])->get()->getRowArray();
                if(!$branch || !in_array((int)$branch['company_id'],$companies,true)
                    || (!$this->isSuperadmin($actor) && !$this->inScope($actor,$moduleId,(int)$branch['company_id'],$branchId))) {
                    throw new DomainException('You cannot delegate a branch outside your authorized scope.');
                }
            }
        } elseif($type==='ROLE_ASSIGNMENT') {
            $roleId=(int)($payload['role_id']??0);
            $role=$this->db->table('usr_roles')->where(['role_id'=>$roleId,'status'=>'ACTIVE'])->get()->getRowArray();
            if(!$role || $roleId<1) throw new DomainException('Choose an active role.');
            if(!$this->isSuperadmin($actor)) {
                if($role['role_code']==='SUPERADMIN') throw new DomainException('Only a Superadmin may assign the Superadmin role.');
                $actions=$this->db->table('rbac_role_module_actions rr')->select('m.module_code,a.action_code')
                    ->join('mod_module_actions a','a.module_action_id=rr.module_action_id')->join('mod_modules m','m.module_id=a.module_id')
                    ->where('rr.role_id',$roleId)->get()->getResultArray();
                foreach($actions as $action) if(!$this->allowed($actor,(string)$action['module_code'],(string)$action['action_code'])) {
                    throw new DomainException('You cannot assign a role with permissions beyond your own access.');
                }
                $targetScopes=$this->db->table('rbac_user_branch_scopes')->where('user_id',$target)->get()->getResultArray();
                foreach($actions as $action) {
                    $module=(int)$this->db->table('mod_modules')->select('module_id')->where('module_code',$action['module_code'])->get()->getRowArray()['module_id'];
                    $targetCompanyScopes=$this->db->table('rbac_user_company_scopes')->where(['user_id'=>$target,'module_id'=>$module])->get()->getResultArray();
                    foreach($targetCompanyScopes as $scope) {
                        $authorizedCompany=$this->db->table('mod_module_companies mc')
                            ->join('org_companies c','c.company_id=mc.company_id')
                            ->join('rbac_user_company_scopes ac','ac.module_id=mc.module_id AND ac.company_id=mc.company_id')
                            ->where(['mc.module_id'=>$module,'mc.company_id'=>(int)$scope['company_id'],'c.status'=>'ACTIVE','ac.user_id'=>$actor])
                            ->countAllResults()>0;
                        if(!$authorizedCompany) throw new DomainException('You cannot assign a role outside your company or branch scope.');
                    }
                    foreach($targetScopes as $scope) if((int)$scope['module_id']===$module
                        && !$this->inScope($actor,$module,(int)$scope['company_id'],(int)$scope['branch_id'])) {
                        throw new DomainException('You cannot assign a role outside your company or branch scope.');
                    }
                }
            }
        } elseif($type==='REPORTING_LINE') {
            $managerId=(int)($payload['reports_to_user_id']??0);
            if($managerId>0 && ($managerId===$target || $this->db->table('usr_accounts a')->join('usr_roles r','r.role_id=a.role_id')
                ->where(['a.user_id'=>$managerId,'a.account_status'=>'ACTIVE','r.status'=>'ACTIVE'])->countAllResults()!==1)) {
                throw new DomainException('Choose an active manager other than the account being updated.');
            }
            if(!$this->isSuperadmin($actor) && $managerId>0 && $managerId!==$actor
                && ($this->db->table('org_reporting_lines')->where(['employee_user_id'=>$managerId,'reports_to_user_id'=>$actor])->countAllResults()!==1
                    || !$this->sharesManagementScope($actor,$managerId))) {
                throw new DomainException('You may assign a direct report only to yourself or another direct report.');
            }
            $this->assertNoReportingCycle($target,$managerId);
        }
    }

    private function validateDelegatedActions(int $actor,int $moduleId,array $actions): void
    {
        if($moduleId<1) throw new DomainException('A valid module is required for action assignments.');
        foreach($actions as $code) {
            $action=$this->db->table('mod_module_actions a')->join('mod_modules m','m.module_id=a.module_id')
                ->where(['a.module_id'=>$moduleId,'a.action_code'=>$code,'a.is_enabled'=>1,'m.status'=>'ACTIVE'])->get()->getRowArray();
            if(!$action || (!$this->isSuperadmin($actor) && !$this->allowed($actor,(string)$action['module_code'],(string)$code))) {
                throw new DomainException('You cannot assign an unavailable action or an action outside your own access.');
            }
        }
    }

    private function applyRoleAssignment(int $actor,int $target,array $payload): void
    {
        $before=$this->db->table('usr_accounts')->select('role_id')->where('user_id',$target)->get()->getRowArray();
        $roleId=(int)($payload['role_id']??0);
        if(!$before || $roleId<1) throw new DomainException('The account or requested role is unavailable.');
        $this->db->table('usr_accounts')->where('user_id',$target)->update(['role_id'=>$roleId,'updated_at'=>date('Y-m-d H:i:s')]);
        $this->db->table('sec_active_sessions')->where('user_id',$target)->delete();
        $this->audit($actor,(int)($payload['target_module_id']??0),'ACCOUNT_ROLE_ASSIGNED',$before,['role_id'=>$roleId],'SUCCESS',$target);
    }

    private function applyReportingLine(int $actor,int $target,array $payload): void
    {
        $before=$this->db->table('org_reporting_lines')->where('employee_user_id',$target)->get()->getRowArray();
        $managerId=(int)($payload['reports_to_user_id']??0);
        if($managerId<1) {
            $this->db->table('org_reporting_lines')->where('employee_user_id',$target)->delete();
        } elseif($before) {
            $this->db->table('org_reporting_lines')->where('employee_user_id',$target)->update(['reports_to_user_id'=>$managerId,'updated_at'=>date('Y-m-d H:i:s')]);
        } else {
            $this->db->table('org_reporting_lines')->insert(['employee_user_id'=>$target,'reports_to_user_id'=>$managerId,'created_by'=>$actor,'created_at'=>date('Y-m-d H:i:s')]);
        }
        $this->audit($actor,(int)($payload['target_module_id']??0),'ACCOUNT_REPORTING_LINE_CHANGED',$before,['reports_to_user_id'=>$managerId?:null],'SUCCESS',$target);
    }

    private function assertNoReportingCycle(int $employeeId,int $managerId): void
    {
        $seen=[$employeeId=>true];
        $current=$managerId;
        while($current>0) {
            if(isset($seen[$current])) throw new DomainException('This reporting line would create a cycle.');
            $seen[$current]=true;
            $row=$this->db->table('org_reporting_lines')->select('reports_to_user_id')->where('employee_user_id',$current)->get()->getRowArray();
            $current=(int)($row['reports_to_user_id']??0);
        }
    }

    private function sharesManagementScope(int $actor,int $target): bool
    {
        if($this->isSuperadmin($actor)) return false;
        $sql='SELECT 1 FROM mod_modules m JOIN mod_module_companies mc ON mc.module_id=m.module_id '
            .'JOIN mod_module_branches mb ON mb.module_id=mc.module_id AND mb.company_id=mc.company_id '
            .'JOIN org_companies c ON c.company_id=mc.company_id AND c.status=? '
            .'JOIN org_branches b ON b.company_id=mb.company_id AND b.branch_id=mb.branch_id AND b.status=? '
            .'JOIN rbac_user_company_scopes ac ON ac.module_id=m.module_id AND ac.company_id=mc.company_id AND ac.user_id=? '
            .'JOIN rbac_user_company_scopes tc ON tc.module_id=m.module_id AND tc.company_id=mc.company_id AND tc.user_id=? '
            .'JOIN rbac_user_branch_scopes ab ON ab.module_id=m.module_id AND ab.company_id=mb.company_id AND ab.branch_id=mb.branch_id AND ab.user_id=? '
            .'JOIN rbac_user_branch_scopes tb ON tb.module_id=m.module_id AND tb.company_id=mb.company_id AND tb.branch_id=mb.branch_id AND tb.user_id=? '
            .'WHERE m.module_code=? AND m.status=? LIMIT 1';
        return $this->db->query($sql,['ACTIVE','ACTIVE',$actor,$target,$actor,$target,'EMPLOYEE_MANAGEMENT','ACTIVE'])->getRowArray()!==null;
    }

    private function sanitizeEmployeePayload(array $payload): array
    {
        $type=strtoupper((string)($payload['change_type']??'MODULE_ACCESS'));
        if($type==='ROLE_ASSIGNMENT') return ['change_type'=>$type,'target_module_id'=>(int)($payload['target_module_id']??0),'role_id'=>(int)($payload['role_id']??0)];
        if($type==='REPORTING_LINE') return ['change_type'=>$type,'target_module_id'=>(int)($payload['target_module_id']??0),'reports_to_user_id'=>(int)($payload['reports_to_user_id']??0)];
        if($type!=='MODULE_ACCESS') return ['change_type'=>$type];
        $clean = [
            'change_type'=>$type,
            'target_module_id'=>(int)($payload['target_module_id']??0),
            'actions'=>array_values(array_unique(array_map('strval',(array)($payload['actions']??[])))),
            'companies'=>array_values(array_unique(array_map('intval',(array)($payload['companies']??[])))),
            'branches'=>array_values(array_unique(array_map('intval',(array)($payload['branches']??[])))),
        ];
        if(array_key_exists('employee_actions',$payload)) {
            $clean['employee_module_id']=(int)($payload['employee_module_id']??0);
            $clean['employee_actions']=array_values(array_unique(array_map('strval',(array)$payload['employee_actions'])));
        }
        return $clean;
    }
}
