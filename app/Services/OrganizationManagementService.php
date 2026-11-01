<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DomainException;

final class OrganizationManagementService
{
    public function __construct(private readonly BaseConnection $db, private readonly AuditLogService $audit)
    {
    }

    /** @return array{companies:list<array<string,mixed>>,branches:list<array<string,mixed>>} */
    public function all(): array
    {
        return [
            'companies' => $this->db->table('org_companies')->orderBy('status')->orderBy('company_name')->get()->getResultArray(),
            'branches' => $this->db->table('org_branches b')->select('b.branch_id,b.company_id,b.branch_code,b.branch_name,b.status,c.company_code,c.company_name,c.status AS company_status')
                ->join('org_companies c','c.company_id=b.company_id')->orderBy('b.status')->orderBy('c.company_name')->orderBy('b.branch_name')->get()->getResultArray(),
        ];
    }

    public function saveCompany(int $actorId, array $input, ?int $id = null): int
    {
        $code = $this->normalizeCode($input['code'] ?? null, 'Company');
        $name = $this->normalizeName($input['name'] ?? null, 'Company');
        $existing = $id === null ? null : $this->db->table('org_companies')->where('company_id',$id)->get()->getRowArray();
        if ($id !== null && $existing === null) throw new DomainException('The company is unavailable.');
        $duplicate = $this->db->table('org_companies')->where('company_code',$code);
        if ($id !== null) $duplicate->where('company_id !=',$id);
        if ($duplicate->countAllResults() > 0) throw new DomainException('Company code is already in use.');
        return $this->saveRecord($actorId, 'org_companies', 'company_id', $id, $existing,
            ['company_code'=>$code,'company_name'=>$name], 'ORGANIZATION_COMPANY');
    }

    public function saveBranch(int $actorId, array $input, ?int $id = null): int
    {
        $code = $this->normalizeCode($input['code'] ?? null, 'Branch');
        $name = $this->normalizeName($input['name'] ?? null, 'Branch');
        $companyId = filter_var($input['company_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$companyId || $this->db->table('org_companies')->where(['company_id'=>$companyId,'status'=>'ACTIVE'])->countAllResults() !== 1) {
            throw new DomainException('Choose an active company for this branch.');
        }
        $existing = $id === null ? null : $this->db->table('org_branches')->where('branch_id',$id)->get()->getRowArray();
        if ($id !== null && $existing === null) throw new DomainException('The branch is unavailable.');
        if ($existing !== null && (int)$existing['company_id'] !== (int)$companyId) throw new DomainException('A branch cannot be moved to a different company.');
        $duplicate = $this->db->table('org_branches')->where(['company_id'=>$companyId,'branch_code'=>$code]);
        if ($id !== null) $duplicate->where('branch_id !=',$id);
        if ($duplicate->countAllResults() > 0) throw new DomainException('Branch code is already in use for this company.');
        return $this->saveRecord($actorId, 'org_branches', 'branch_id', $id, $existing,
            ['company_id'=>(int)$companyId,'branch_code'=>$code,'branch_name'=>$name], 'ORGANIZATION_BRANCH');
    }

    public function setStatus(int $actorId, string $kind, int $id, string $status): void
    {
        $configs = ['company'=>['org_companies','company_id','ORGANIZATION_COMPANY'],'branch'=>['org_branches','branch_id','ORGANIZATION_BRANCH']];
        if (!isset($configs[$kind])) throw new DomainException('Choose a valid organization record.');
        $status = strtoupper(trim($status));
        if (!in_array($status,['ACTIVE','INACTIVE'],true)) throw new DomainException('Choose a valid organization status.');
        [$table,$key,$event] = $configs[$kind];
        $row = $this->db->table($table)->where($key,$id)->get()->getRowArray();
        if ($row === null) throw new DomainException('The organization record is unavailable.');
        if ($status === 'ACTIVE' && $kind === 'branch' && $this->db->table('org_companies')->where(['company_id'=>$row['company_id'],'status'=>'ACTIVE'])->countAllResults() !== 1) {
            throw new DomainException('Activate the parent company before activating this branch.');
        }
        if ($row['status'] === $status) return;
        $this->db->transBegin();
        try {
            $this->db->table($table)->where($key,$id)->update(['status'=>$status,'updated_at'=>date('Y-m-d H:i:s')]);
            $this->audit->recordChange($actorId,$event.'_STATUS_CHANGED','SUCCESS',['record_id'=>$id,'status'=>$row['status']],['record_id'=>$id,'status'=>$status],null);
            if (!$this->db->transStatus() || !$this->db->transCommit()) throw new DomainException('The organization status could not be saved.');
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            if ($exception instanceof DomainException) throw $exception;
            throw new DomainException('The organization status could not be saved. Please try again.');
        }
    }

    private function saveRecord(int $actorId,string $table,string $key,?int $id,?array $existing,array $data,string $event): int
    {
        $this->db->transBegin();
        try {
            if ($existing === null) {
                $data += ['status'=>'ACTIVE','created_at'=>date('Y-m-d H:i:s')];
                $this->db->table($table)->insert($data);
                $id=(int)$this->db->insertID();
                $this->audit->recordChange($actorId,$event.'_CREATED','SUCCESS',null,['record_id'=>$id]+$data,null);
            } else {
                $data['updated_at']=date('Y-m-d H:i:s');
                $this->db->table($table)->where($key,$id)->update($data);
                $this->audit->recordChange($actorId,$event.'_UPDATED','SUCCESS',$existing,array_replace($existing,$data),null);
            }
            if (!$this->db->transStatus() || !$this->db->transCommit()) throw new DomainException('The organization record could not be saved.');
            return (int)$id;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            if ($exception instanceof DomainException) throw $exception;
            throw new DomainException('The organization record could not be saved. Please try again.');
        }
    }

    private function normalizeCode(mixed $value,string $label): string
    {
        $code = strtoupper(trim(is_scalar($value)?(string)$value:''));
        if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,49}$/',$code)) throw new DomainException($label.' code must be 2 to 50 letters, numbers, underscores, or hyphens.');
        return $code;
    }

    private function normalizeName(mixed $value,string $label): string
    {
        $name = trim(is_scalar($value)?(string)$value:'');
        if ($name === '' || mb_strlen($name,'UTF-8') > 150) throw new DomainException($label.' name is required and must be 150 characters or fewer.');
        return $name;
    }
}
