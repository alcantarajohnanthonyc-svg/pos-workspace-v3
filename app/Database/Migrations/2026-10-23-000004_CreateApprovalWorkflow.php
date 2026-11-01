<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateApprovalWorkflow extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `apr_module_approvers` (
  `module_action_id` BIGINT(20) UNSIGNED NOT NULL,
  `stage_order` SMALLINT(5) UNSIGNED NOT NULL,
  `approver_user_id` BIGINT(20) UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`module_action_id`,`stage_order`),
  KEY `idx_apr_module_approvers_user` (`approver_user_id`),
  CONSTRAINT `fk_apr_module_approvers_action` FOREIGN KEY (`module_action_id`) REFERENCES `mod_module_actions` (`module_action_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_apr_module_approvers_user` FOREIGN KEY (`approver_user_id`) REFERENCES `usr_accounts` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE `apr_requests` (
  `approval_request_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_code` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_action_id` BIGINT(20) UNSIGNED NOT NULL,
  `target_type` ENUM('OUTLET','USER_ACCESS') COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_record_id` BIGINT(20) UNSIGNED DEFAULT NULL,
  `requester_user_id` BIGINT(20) UNSIGNED NOT NULL,
  `company_id` BIGINT(20) UNSIGNED DEFAULT NULL,
  `branch_id` BIGINT(20) UNSIGNED DEFAULT NULL,
  `request_status` ENUM('PENDING','APPROVED','REJECTED','APPLIED','CANCELLED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `requested_data` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `applied_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`approval_request_id`),
  UNIQUE KEY `uq_apr_requests_code` (`request_code`),
  KEY `idx_apr_requests_status_created` (`request_status`,`created_at`),
  KEY `idx_apr_requests_action_target` (`module_action_id`,`target_type`,`target_record_id`),
  KEY `idx_apr_requests_requester_status` (`requester_user_id`,`request_status`),
  KEY `idx_apr_requests_company_branch` (`company_id`,`branch_id`),
  CONSTRAINT `fk_apr_requests_action` FOREIGN KEY (`module_action_id`) REFERENCES `mod_module_actions` (`module_action_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_apr_requests_requester` FOREIGN KEY (`requester_user_id`) REFERENCES `usr_accounts` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_apr_requests_company` FOREIGN KEY (`company_id`) REFERENCES `org_companies` (`company_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_apr_requests_branch` FOREIGN KEY (`company_id`,`branch_id`) REFERENCES `org_branches` (`company_id`,`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE `apr_request_stages` (
  `approval_stage_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `approval_request_id` BIGINT(20) UNSIGNED NOT NULL,
  `stage_order` SMALLINT(5) UNSIGNED NOT NULL,
  `approver_user_id` BIGINT(20) UNSIGNED NOT NULL,
  `stage_status` ENUM('WAITING','PENDING','APPROVED','REJECTED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'WAITING',
  `decision_reason` VARCHAR(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decided_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`approval_stage_id`),
  UNIQUE KEY `uq_apr_request_stage_order` (`approval_request_id`,`stage_order`),
  KEY `idx_apr_request_stage_approver_status` (`approver_user_id`,`stage_status`),
  CONSTRAINT `fk_apr_request_stages_request` FOREIGN KEY (`approval_request_id`) REFERENCES `apr_requests` (`approval_request_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_apr_request_stages_approver` FOREIGN KEY (`approver_user_id`) REFERENCES `usr_accounts` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        throw new \RuntimeException('Approval history and workflow configuration are retained. Do not roll back automatically.');
    }
}
