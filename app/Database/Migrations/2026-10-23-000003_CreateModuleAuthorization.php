<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateModuleAuthorization extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `mod_module_actions` (
  `module_action_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `module_id` BIGINT(20) UNSIGNED NOT NULL,
  `action_code` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `requires_approval` TINYINT(1) NOT NULL DEFAULT 0,
  `approval_mode` ENUM('NONE','ADMIN','HIERARCHY') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'NONE',
  `requires_company_scope` TINYINT(1) NOT NULL DEFAULT 1,
  `requires_branch_scope` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`module_action_id`),
  UNIQUE KEY `uq_mod_module_actions_module_code` (`module_id`,`action_code`),
  KEY `idx_mod_module_actions_enabled` (`module_id`,`is_enabled`),
  CONSTRAINT `fk_mod_module_actions_module` FOREIGN KEY (`module_id`) REFERENCES `mod_modules` (`module_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE `rbac_role_module_actions` (
  `role_id` BIGINT(20) UNSIGNED NOT NULL,
  `module_action_id` BIGINT(20) UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`,`module_action_id`),
  KEY `idx_rbac_role_module_action_action` (`module_action_id`),
  CONSTRAINT `fk_rbac_role_module_action_role` FOREIGN KEY (`role_id`) REFERENCES `usr_roles` (`role_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_rbac_role_module_action_action` FOREIGN KEY (`module_action_id`) REFERENCES `mod_module_actions` (`module_action_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE `rbac_user_module_actions` (
  `user_id` BIGINT(20) UNSIGNED NOT NULL,
  `module_action_id` BIGINT(20) UNSIGNED NOT NULL,
  `created_by` BIGINT(20) UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`module_action_id`),
  KEY `idx_rbac_user_module_action_action` (`module_action_id`),
  KEY `idx_rbac_user_module_action_creator` (`created_by`),
  CONSTRAINT `fk_rbac_user_module_action_user` FOREIGN KEY (`user_id`) REFERENCES `usr_accounts` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_rbac_user_module_action_action` FOREIGN KEY (`module_action_id`) REFERENCES `mod_module_actions` (`module_action_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_rbac_user_module_action_creator` FOREIGN KEY (`created_by`) REFERENCES `usr_accounts` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE `mod_module_companies` (
  `module_id` BIGINT(20) UNSIGNED NOT NULL,
  `company_id` BIGINT(20) UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`module_id`,`company_id`),
  KEY `idx_mod_module_companies_company` (`company_id`),
  CONSTRAINT `fk_mod_module_companies_module` FOREIGN KEY (`module_id`) REFERENCES `mod_modules` (`module_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_mod_module_companies_company` FOREIGN KEY (`company_id`) REFERENCES `org_companies` (`company_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE `mod_module_branches` (
  `module_id` BIGINT(20) UNSIGNED NOT NULL,
  `company_id` BIGINT(20) UNSIGNED NOT NULL,
  `branch_id` BIGINT(20) UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`module_id`,`company_id`,`branch_id`),
  KEY `idx_mod_module_branches_company_branch` (`company_id`,`branch_id`),
  CONSTRAINT `fk_mod_module_branches_module` FOREIGN KEY (`module_id`) REFERENCES `mod_modules` (`module_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_mod_module_branches_branch` FOREIGN KEY (`company_id`,`branch_id`) REFERENCES `org_branches` (`company_id`,`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE `rbac_user_company_scopes` (
  `user_id` BIGINT(20) UNSIGNED NOT NULL,
  `module_id` BIGINT(20) UNSIGNED NOT NULL,
  `company_id` BIGINT(20) UNSIGNED NOT NULL,
  `created_by` BIGINT(20) UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`module_id`,`company_id`),
  KEY `idx_rbac_user_company_scope_module_company` (`module_id`,`company_id`),
  CONSTRAINT `fk_rbac_user_company_scope_user` FOREIGN KEY (`user_id`) REFERENCES `usr_accounts` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_rbac_user_company_scope_module` FOREIGN KEY (`module_id`) REFERENCES `mod_modules` (`module_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_rbac_user_company_scope_company` FOREIGN KEY (`company_id`) REFERENCES `org_companies` (`company_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_rbac_user_company_scope_creator` FOREIGN KEY (`created_by`) REFERENCES `usr_accounts` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE `rbac_user_branch_scopes` (
  `user_id` BIGINT(20) UNSIGNED NOT NULL,
  `module_id` BIGINT(20) UNSIGNED NOT NULL,
  `company_id` BIGINT(20) UNSIGNED NOT NULL,
  `branch_id` BIGINT(20) UNSIGNED NOT NULL,
  `created_by` BIGINT(20) UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`module_id`,`company_id`,`branch_id`),
  KEY `idx_rbac_user_branch_scope_module_company_branch` (`module_id`,`company_id`,`branch_id`),
  CONSTRAINT `fk_rbac_user_branch_scope_user` FOREIGN KEY (`user_id`) REFERENCES `usr_accounts` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_rbac_user_branch_scope_module` FOREIGN KEY (`module_id`) REFERENCES `mod_modules` (`module_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_rbac_user_branch_scope_branch` FOREIGN KEY (`company_id`,`branch_id`) REFERENCES `org_branches` (`company_id`,`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_rbac_user_branch_scope_creator` FOREIGN KEY (`created_by`) REFERENCES `usr_accounts` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE `sys_mandatory_approval_policies` (
  `mandatory_policy_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `action_code` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_mandatory_global` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`mandatory_policy_id`),
  UNIQUE KEY `uq_sys_mandatory_approval_action` (`action_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
INSERT INTO `mod_modules` (`module_code`,`module_name`,`short_name`,`module_type`,`description`,`route_path`,`icon`,`sort_order`,`status`)
SELECT 'OUTLET_LIST','Outlet List','Outlets','MASTERFILE','Manage authorized outlet records.','/outlets','store',10,'DRAFT'
WHERE NOT EXISTS (SELECT 1 FROM `mod_modules` WHERE `module_code`='OUTLET_LIST')
SQL);
        $this->db->query(<<<'SQL'
INSERT INTO `mod_modules` (`module_code`,`module_name`,`short_name`,`module_type`,`description`,`route_path`,`icon`,`sort_order`,`status`)
SELECT 'EMPLOYEE_MANAGEMENT','Employee Management','Employees','ADMIN','Manage account access and delegated scope.','/employee-management','users',20,'DRAFT'
WHERE NOT EXISTS (SELECT 1 FROM `mod_modules` WHERE `module_code`='EMPLOYEE_MANAGEMENT')
SQL);

        $this->seedActions('OUTLET_LIST', ['VIEW','CREATE','UPDATE','DELETE','APPROVE']);
        $this->seedActions('EMPLOYEE_MANAGEMENT', ['VIEW','MANAGE_ACCESS','APPROVE']);
    }

    private function seedActions(string $moduleCode, array $actions): void
    {
        $module = $this->db->table('mod_modules')->select('module_id')->where('module_code', $moduleCode)->get()->getRowArray();
        if ($module === null) {
            throw new \RuntimeException('Required initial module registry entry is unavailable.');
        }
        foreach ($actions as $action) {
            $this->db->query(
                'INSERT INTO `mod_module_actions` (`module_id`,`action_code`,`is_enabled`,`requires_approval`,`approval_mode`) SELECT ?, ?, 0, 0, \'NONE\' WHERE NOT EXISTS (SELECT 1 FROM `mod_module_actions` WHERE `module_id`=? AND `action_code`=?)',
                [(int) $module['module_id'], $action, (int) $module['module_id'], $action]
            );
        }
    }

    public function down(): void
    {
        throw new \RuntimeException('Module authorization data is retained. Do not roll back this migration automatically.');
    }
}
