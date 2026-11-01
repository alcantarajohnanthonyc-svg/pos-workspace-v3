<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateOrganizationPrerequisites extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `org_companies` (
  `company_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_code` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_name` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` ENUM('ACTIVE','INACTIVE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`company_id`),
  UNIQUE KEY `uq_org_companies_code` (`company_code`),
  UNIQUE KEY `uq_org_companies_id_code` (`company_id`,`company_code`),
  KEY `idx_org_companies_status_name` (`status`,`company_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE `org_branches` (
  `branch_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT(20) UNSIGNED NOT NULL,
  `branch_code` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch_name` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` ENUM('ACTIVE','INACTIVE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`branch_id`),
  UNIQUE KEY `uq_org_branches_company_code` (`company_id`,`branch_code`),
  UNIQUE KEY `uq_org_branches_company_branch` (`company_id`,`branch_id`),
  KEY `idx_org_branches_company_status_name` (`company_id`,`status`,`branch_name`),
  CONSTRAINT `fk_org_branches_company` FOREIGN KEY (`company_id`) REFERENCES `org_companies` (`company_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->db->query(<<<'SQL'
CREATE TABLE `org_reporting_lines` (
  `reporting_line_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_user_id` BIGINT(20) UNSIGNED NOT NULL,
  `reports_to_user_id` BIGINT(20) UNSIGNED NOT NULL,
  `created_by` BIGINT(20) UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`reporting_line_id`),
  UNIQUE KEY `uq_org_reporting_employee` (`employee_user_id`),
  KEY `idx_org_reporting_manager` (`reports_to_user_id`),
  CONSTRAINT `fk_org_reporting_employee` FOREIGN KEY (`employee_user_id`) REFERENCES `usr_accounts` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_org_reporting_manager` FOREIGN KEY (`reports_to_user_id`) REFERENCES `usr_accounts` (`user_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_org_reporting_creator` FOREIGN KEY (`created_by`) REFERENCES `usr_accounts` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        throw new \RuntimeException('Organization records are retained. Do not roll back automatically.');
    }
}
