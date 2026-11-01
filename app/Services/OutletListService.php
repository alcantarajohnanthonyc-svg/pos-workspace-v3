<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DomainException;

final class OutletListService
{
    public function __construct(private readonly BaseConnection $db, private readonly OutletAccessService $access) {}

    public function index(int $userId, array $filters = []): array
    {
        $moduleId=$this->moduleId();
        $this->access->assertAllowed($userId,'OUTLET_LIST','VIEW');
        $rows=$this->access->scopedOutlets($userId,$moduleId,(bool)($filters['archived']??false));
        $term=mb_strtolower(trim((string)($filters['q']??'')),'UTF-8');
        $status=strtoupper(trim((string)($filters['status']??'')));
        $rows=array_values(array_filter($rows,static function(array $row) use($term,$status): bool {
            $matchesTerm=$term==='' || str_contains(mb_strtolower((string)$row['outlet_code'].' '.(string)$row['outlet_name'].' '.(string)$row['company_name'].' '.(string)$row['branch_name'],'UTF-8'),$term);
            return $matchesTerm && (!in_array($status,['ACTIVE','INACTIVE'],true) || $row['status']===$status);
        }));
        return ['items'=>$rows,'canCreate'=>$this->access->allowed($userId,'OUTLET_LIST','CREATE'),'canUpdate'=>$this->access->allowed($userId,'OUTLET_LIST','UPDATE'),'canDelete'=>$this->access->allowed($userId,'OUTLET_LIST','DELETE')];
    }

    public function formOptions(int $userId): array
    {
        $moduleId=$this->moduleId();
        $this->access->assertAllowed($userId,'OUTLET_LIST','VIEW');
        $companies=$this->db->table('mod_module_companies mc')->select('c.company_id,c.company_name')->join('org_companies c','c.company_id=mc.company_id')
            ->where(['mc.module_id'=>$moduleId,'c.status'=>'ACTIVE'])->orderBy('c.company_name')->get()->getResultArray();
        if(!$this->access->isSuperadmin($userId)) $companies=array_values(array_filter($companies,fn(array $row): bool=>$this->db->table('rbac_user_company_scopes')->where(['user_id'=>$userId,'module_id'=>$moduleId,'company_id'=>$row['company_id']])->countAllResults()>0));
        $branches=$this->db->table('mod_module_branches mb')->select('b.branch_id,b.company_id,b.branch_name')->join('org_branches b','b.company_id=mb.company_id AND b.branch_id=mb.branch_id')
            ->join('org_companies c','c.company_id=b.company_id')->where(['mb.module_id'=>$moduleId,'b.status'=>'ACTIVE','c.status'=>'ACTIVE'])->orderBy('b.branch_name')->get()->getResultArray();
        if(!$this->access->isSuperadmin($userId)) $branches=array_values(array_filter($branches,fn(array $row): bool=>$this->access->inScope($userId,$moduleId,(int)$row['company_id'],(int)$row['branch_id'])));
        return ['companies'=>$companies,'branches'=>$branches];
    }

    public function find(int $userId,int $outletId): array
    {
        $this->access->assertAllowed($userId,'OUTLET_LIST','VIEW');
        foreach(array_merge($this->access->scopedOutlets($userId,$this->moduleId()),$this->access->scopedOutlets($userId,$this->moduleId(),true)) as $row) if((int)$row['outlet_id']===$outletId) return $row;
        throw new DomainException('The outlet is unavailable.');
    }

    public function save(int $userId,?int $outletId,array $input): ?int
    {
        $actionCode=$outletId===null?'CREATE':'UPDATE';
        $this->access->assertAllowed($userId,'OUTLET_LIST',$actionCode);
        $moduleId=$this->moduleId();
        $companyId=filter_var($input['company_id']??null,FILTER_VALIDATE_INT);
        $branchId=filter_var($input['branch_id']??null,FILTER_VALIDATE_INT);
        $code=strtoupper(trim((string)($input['outlet_code']??'')));
        $name=trim((string)($input['outlet_name']??''));
        $status=strtoupper(trim((string)($input['status']??'ACTIVE')));
        if(!$companyId || !$branchId || !preg_match('/^[A-Z0-9][A-Z0-9._-]{0,49}$/',$code) || $name==='' || mb_strlen($name,'UTF-8')>150 || !in_array($status,['ACTIVE','INACTIVE'],true)) throw new DomainException('Enter a valid outlet code, name, company, branch, and status.');
        if(!$this->access->inScope($userId,$moduleId,$companyId,$branchId)) throw new DomainException('You cannot use a company or branch outside your authorized scope.');
        $branch=$this->db->table('mod_module_branches mb')->join('org_branches b','b.company_id=mb.company_id AND b.branch_id=mb.branch_id')
            ->where(['mb.module_id'=>$moduleId,'mb.company_id'=>$companyId,'mb.branch_id'=>$branchId,'b.status'=>'ACTIVE'])->countAllResults();
        if(!$branch) throw new DomainException('Choose an active branch configured for Outlet List.');
        if($outletId!==null) {
            $existing=$this->find($userId,$outletId);
            if(!empty($existing['archived_at'])) throw new DomainException('Archived outlets cannot be edited.');
        }
        $payload=['_operation'=>$outletId===null?'CREATE':'UPDATE','company_id'=>$companyId,'branch_id'=>$branchId,'outlet_code'=>$code,'outlet_name'=>$name,'status'=>$status];
        $action=$this->access->action($moduleId,$actionCode);
        if(!$action) throw new DomainException('This Outlet List action is not configured.');
        return $this->access->submit($userId,$moduleId,$action,$payload,$outletId,$companyId,$branchId);
    }

    public function setArchived(int $userId,int $outletId,bool $archive): ?int
    {
        $this->access->assertAllowed($userId,'OUTLET_LIST','DELETE');
        $row=$this->find($userId,$outletId);
        $currentlyArchived=!empty($row['archived_at']);
        if($archive===$currentlyArchived) throw new DomainException('The outlet already has the requested archive status.');
        $action=$this->access->action($this->moduleId(),'DELETE');
        if(!$action) throw new DomainException('This Outlet List action is not configured.');
        return $this->access->submit($userId,$this->moduleId(),$action,['company_id'=>(int)$row['company_id'],'branch_id'=>(int)$row['branch_id'],'_operation'=>$archive?'DELETE':'RESTORE'], $outletId,(int)$row['company_id'],(int)$row['branch_id']);
    }

    private function moduleId(): int
    {
        $row=$this->db->table('mod_modules')->select('module_id')->where(['module_code'=>'OUTLET_LIST','status'=>'ACTIVE'])->get()->getRowArray();
        if(!$row) throw new DomainException('Outlet List is not currently available.');
        return (int)$row['module_id'];
    }
}
