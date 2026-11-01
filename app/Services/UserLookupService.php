<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DomainException;

final class UserLookupService
{
    private const LOOKUPS = [
        'positions' => ['table' => 'org_positions', 'id' => 'position_id', 'code' => 'position_code', 'name' => 'position_name', 'label' => 'Position'],
        'departments' => ['table' => 'org_departments', 'id' => 'department_id', 'code' => 'department_code', 'name' => 'department_name', 'label' => 'Department'],
    ];

    public function __construct(private readonly BaseConnection $db, private readonly AuditLogService $audit)
    {
    }

    /** @return array<string,list<array<string,mixed>>> */
    public function all(): array
    {
        $result = [];
        foreach (self::LOOKUPS as $key => $config) {
            $this->assertAvailable($config['table']);
            $result[$key] = $this->db->table($config['table'])->orderBy('status')->orderBy($config['name'])->get()->getResultArray();
        }
        return $result;
    }

    public function save(string $kind, int $actorId, array $input, ?int $id = null): int
    {
        $config = $this->config($kind);
        $this->assertAvailable($config['table']);
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));
        if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{1,49}$/', $code)) {
            throw new DomainException($config['label'] . ' code must be 2 to 50 letters, numbers, underscores, or hyphens.');
        }
        if ($name === '' || mb_strlen($name, 'UTF-8') > 120) {
            throw new DomainException($config['label'] . ' name is required and must be 120 characters or fewer.');
        }
        $existing = null;
        if ($id !== null) {
            $existing = $this->db->table($config['table'])->where($config['id'], $id)->get()->getRowArray();
            if ($existing === null) throw new DomainException('The lookup record is unavailable.');
        }
        $duplicate = $this->db->table($config['table'])->where($config['code'], $code);
        if ($id !== null) $duplicate->where($config['id'] . ' !=', $id);
        if ($duplicate->countAllResults() > 0) throw new DomainException($config['label'] . ' code is already in use.');

        $data = [$config['code'] => $code, $config['name'] => $name];
        $this->db->transBegin();
        try {
            if ($existing === null) {
                $data += ['status' => 'ACTIVE', 'created_by' => $actorId, 'created_at' => date('Y-m-d H:i:s')];
                $this->db->table($config['table'])->insert($data);
                $id = (int) $this->db->insertID();
                $after = [$config['id'] => $id] + $data;
                $this->audit->recordChange($actorId, 'USER_LOOKUP_CREATED', 'SUCCESS', null, $after, null);
            } else {
                $data['updated_at'] = date('Y-m-d H:i:s');
                $this->db->table($config['table'])->where($config['id'], $id)->update($data);
                $this->audit->recordChange($actorId, 'USER_LOOKUP_UPDATED', 'SUCCESS', $existing, array_replace($existing, $data), null);
            }
            if (!$this->db->transStatus() || !$this->db->transCommit()) throw new DomainException('The lookup record could not be saved.');
            return (int) $id;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            if ($exception instanceof DomainException) throw $exception;
            throw new DomainException('The lookup record could not be saved. Please try again.');
        }
    }

    public function setStatus(string $kind, int $actorId, int $id, string $status): void
    {
        $config = $this->config($kind);
        $this->assertAvailable($config['table']);
        $status = strtoupper(trim($status));
        if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) throw new DomainException('Choose a valid lookup status.');
        $row = $this->db->table($config['table'])->where($config['id'], $id)->get()->getRowArray();
        if ($row === null) throw new DomainException('The lookup record is unavailable.');
        if ($row['status'] === $status) return;

        $this->db->transBegin();
        try {
            $this->db->table($config['table'])->where($config['id'], $id)->update(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
            $after = array_replace($row, ['status' => $status]);
            $this->audit->recordChange($actorId, 'USER_LOOKUP_STATUS_CHANGED', 'SUCCESS', ['lookup_type' => $kind, 'status' => $row['status']], ['lookup_type' => $kind, 'status' => $status], null);
            if (!$this->db->transStatus() || !$this->db->transCommit()) throw new DomainException('The lookup status could not be saved.');
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            if ($exception instanceof DomainException) throw $exception;
            throw new DomainException('The lookup status could not be saved. Please try again.');
        }
    }

    private function config(string $kind): array
    {
        if (!isset(self::LOOKUPS[$kind])) throw new DomainException('The lookup type is invalid.');
        return self::LOOKUPS[$kind];
    }

    private function assertAvailable(string $table): void
    {
        if (!$this->db->tableExists($table)) {
            throw new DomainException('Position and department records are unavailable until the approved database update is deployed.');
        }
    }
}
