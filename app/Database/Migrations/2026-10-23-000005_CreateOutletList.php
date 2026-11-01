<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateOutletList extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `out_outlets` (
  `outlet_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT(20) UNSIGNED NOT NULL,
  `branch_id` BIGINT(20) UNSIGNED NOT NULL,
  `outlet_code` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `outlet_name` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` ENUM('ACTIVE','INACTIVE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `archived_at` DATETIME DEFAULT NULL,
  `archived_by` BIGINT(20) UNSIGNED DEFAULT NULL,
  `created_by` BIGINT(20) UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT(20) UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`outlet_id`),
  UNIQUE KEY `uq_out_outlets_branch_code` (`company_id`,`branch_id`,`outlet_code`),
  KEY `idx_out_outlets_company_status` (`company_id`,`status`,`archived_at`),
  KEY `idx_out_outlets_branch_status` (`branch_id`,`status`,`archived_at`),
  KEY `idx_out_outlets_name` (`outlet_name`),
  CONSTRAINT `fk_out_outlets_branch` FOREIGN KEY (`company_id`,`branch_id`) REFERENCES `org_branches` (`company_id`,`branch_id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_out_outlets_archiver` FOREIGN KEY (`archived_by`) REFERENCES `usr_accounts` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_out_outlets_creator` FOREIGN KEY (`created_by`) REFERENCES `usr_accounts` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `fk_out_outlets_updater` FOREIGN KEY (`updated_by`) REFERENCES `usr_accounts` (`user_id`) ON DELETE SET NULL ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        throw new \RuntimeException('Outlet records and archive history are retained. Do not roll back automatically.');
    }
}
