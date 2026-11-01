<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

final class SuperadminDashboardService
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /** @return array{accountsTotal: int, activeAccounts: int, pendingAccounts: int, activeModules: int, assignedApprovals: int} */
    public function snapshot(int $actorUserId): array
    {
        if ($actorUserId < 1) {
            throw new \InvalidArgumentException('A valid dashboard actor is required.');
        }

        $approvals = $this->db->table('apr_request_stages AS s')
            ->join('apr_requests AS r', 'r.approval_request_id = s.approval_request_id')
            ->where('s.approver_user_id', $actorUserId)
            ->where('s.stage_status', 'PENDING')
            ->where('r.request_status', 'PENDING')
            ->countAllResults();

        return [
            'accountsTotal' => $this->db->table('usr_accounts')->countAllResults(),
            'activeAccounts' => $this->db->table('usr_accounts')->where('account_status', 'ACTIVE')->countAllResults(),
            'pendingAccounts' => $this->db->table('usr_accounts')->where('account_status', 'PENDING')->countAllResults(),
            'activeModules' => $this->db->table('mod_modules')->where('status', 'ACTIVE')->countAllResults(),
            'assignedApprovals' => $approvals,
        ];
    }
}
