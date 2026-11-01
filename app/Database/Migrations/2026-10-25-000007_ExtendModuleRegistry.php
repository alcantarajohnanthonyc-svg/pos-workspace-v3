<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class ExtendModuleRegistry extends Migration
{
    public function up(): void
    {
        $this->db->query("ALTER TABLE `mod_module_actions` MODIFY `approval_mode` ENUM('NONE','ADMIN','HIERARCHY','COMBINED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NONE'");
        $this->db->query("ALTER TABLE `mod_module_actions` ADD COLUMN `action_name` VARCHAR(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `action_code`, ADD COLUMN `action_description` VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `action_name`, ADD COLUMN `handler_key` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `action_description`");

        $this->db->query(<<<'SQL'
CREATE TABLE `mod_module_audiences` (
  `module_id` BIGINT(20) UNSIGNED NOT NULL,
  `audience_code` ENUM('SUPERADMIN','USER','VIEWER') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`module_id`,`audience_code`),
  CONSTRAINT `fk_mod_module_audience_module` FOREIGN KEY (`module_id`) REFERENCES `mod_modules` (`module_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE `mod_module_administrators` (
  `module_id` BIGINT(20) UNSIGNED NOT NULL,
  `user_id` BIGINT(20) UNSIGNED NOT NULL,
  `assigned_by` BIGINT(20) UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`module_id`,`user_id`),
  KEY `idx_mod_module_admin_user` (`user_id`),
  CONSTRAINT `fk_mod_module_admin_module` FOREIGN KEY (`module_id`) REFERENCES `mod_modules` (`module_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_mod_module_admin_user` FOREIGN KEY (`user_id`) REFERENCES `usr_accounts` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_mod_module_admin_assigner` FOREIGN KEY (`assigned_by`) REFERENCES `usr_accounts` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        throw new \RuntimeException('Module Registry configuration is retained. Do not roll back automatically.');
    }
}
