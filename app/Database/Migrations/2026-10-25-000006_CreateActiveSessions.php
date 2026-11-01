<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateActiveSessions extends Migration
{
    public function up(): void
    {
        $this->db->query(<<<'SQL'
CREATE TABLE `sec_active_sessions` (
  `user_id` BIGINT(20) UNSIGNED NOT NULL,
  `session_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `last_seen_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `idx_sec_active_sessions_expiry` (`expires_at`),
  CONSTRAINT `fk_sec_active_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `usr_accounts` (`user_id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        throw new \RuntimeException('Active session history is retained. Do not roll back automatically.');
    }
}
