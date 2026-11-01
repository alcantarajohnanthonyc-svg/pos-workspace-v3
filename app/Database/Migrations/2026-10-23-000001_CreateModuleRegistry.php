<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateModuleRegistry extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `mod_modules` (
  `module_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_module_id` BIGINT(20) UNSIGNED DEFAULT NULL,
  `module_code` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `module_name` VARCHAR(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `short_name` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module_type` ENUM('MASTERFILE','TRANSACTION','REPORT','ADMIN') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route_path` VARCHAR(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('DRAFT','ACTIVE','INACTIVE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DRAFT',
  `created_by` BIGINT(20) UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`module_id`),
  UNIQUE KEY `uq_mod_modules_code` (`module_code`),
  KEY `idx_mod_modules_parent` (`parent_module_id`),
  KEY `idx_mod_modules_status_order` (`status`,`sort_order`),
  CONSTRAINT `fk_mod_modules_parent` FOREIGN KEY (`parent_module_id`) REFERENCES `mod_modules` (`module_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_mod_modules_creator` FOREIGN KEY (`created_by`) REFERENCES `usr_accounts` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE `log_audit_logs` (
  `audit_log_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_user_id` BIGINT(20) UNSIGNED DEFAULT NULL,
  `module_id` BIGINT(20) UNSIGNED DEFAULT NULL,
  `event_code` VARCHAR(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `result` ENUM('SUCCESS','DENIED','FAILURE') COLLATE utf8mb4_unicode_ci NOT NULL,
  `before_data` LONGTEXT COLLATE utf8mb4_unicode_ci,
  `after_data` LONGTEXT COLLATE utf8mb4_unicode_ci,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`audit_log_id`),
  KEY `idx_log_audit_actor_created` (`actor_user_id`,`created_at`),
  KEY `idx_log_audit_module_created` (`module_id`,`created_at`),
  KEY `idx_log_audit_event_created` (`event_code`,`created_at`),
  CONSTRAINT `fk_log_audit_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `usr_accounts` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_log_audit_module` FOREIGN KEY (`module_id`) REFERENCES `mod_modules` (`module_id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        throw new \RuntimeException('Module Registry and audit tables are retained. Do not roll back automatically.');
    }
}
