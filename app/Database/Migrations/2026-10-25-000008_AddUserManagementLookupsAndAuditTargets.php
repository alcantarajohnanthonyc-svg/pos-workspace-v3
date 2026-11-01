<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Additive Phase 4 lookup references and account-targeted audit indexing. */
final class AddUserManagementLookupsAndAuditTargets extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `org_positions` (
  `position_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `position_code` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position_name` VARCHAR(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` ENUM('ACTIVE','INACTIVE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `created_by` BIGINT(20) UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`position_id`),
  UNIQUE KEY `uq_org_positions_code` (`position_code`),
  KEY `idx_org_positions_status_name` (`status`,`position_name`),
  CONSTRAINT `fk_org_positions_creator` FOREIGN KEY (`created_by`) REFERENCES `usr_accounts` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->db->query(<<<'SQL'
CREATE TABLE `org_departments` (
  `department_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `department_code` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_name` VARCHAR(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` ENUM('ACTIVE','INACTIVE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `created_by` BIGINT(20) UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`department_id`),
  UNIQUE KEY `uq_org_departments_code` (`department_code`),
  KEY `idx_org_departments_status_name` (`status`,`department_name`),
  CONSTRAINT `fk_org_departments_creator` FOREIGN KEY (`created_by`) REFERENCES `usr_accounts` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->db->query('ALTER TABLE `usr_accounts` ADD COLUMN `department_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `position_id`, ADD KEY `idx_usr_accounts_position` (`position_id`), ADD KEY `idx_usr_accounts_department` (`department_id`), ADD CONSTRAINT `fk_usr_accounts_position` FOREIGN KEY (`position_id`) REFERENCES `org_positions` (`position_id`) ON DELETE RESTRICT ON UPDATE RESTRICT, ADD CONSTRAINT `fk_usr_accounts_department` FOREIGN KEY (`department_id`) REFERENCES `org_departments` (`department_id`) ON DELETE RESTRICT ON UPDATE RESTRICT');
        $this->db->query('ALTER TABLE `log_audit_logs` ADD COLUMN `target_user_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `actor_user_id`, ADD KEY `idx_log_audit_target_created` (`target_user_id`,`created_at`), ADD CONSTRAINT `fk_log_audit_target_user` FOREIGN KEY (`target_user_id`) REFERENCES `usr_accounts` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT');
    }

    public function down(): void
    {
        throw new \RuntimeException('User lookup and account audit history are retained. Do not roll back automatically.');
    }
}
