# Database Documentation

## Current live database state (rechecked 2026-10-25 00:20 UTC)

On 2026-10-23, `SELECT VERSION(), DATABASE()` confirmed `10.1.19-MariaDB` and `pos_workspace_v3`. The initial inventory contained `migrations` (0 rows), `usr_accounts` (2 rows), and `usr_roles` (1 row). Before migration, a timestamped SQL backup was written to `writable/database-backups/pos_workspace_v3_pre_outlet_access_20261023_145930.sql`. The file was verified to contain CREATE statements for all three original tables and INSERT data for both account rows and the one role row; values were not displayed. The directory is under the protected CI4 writable area.

The five approved migrations `2026-10-23-000001` through `000005` are applied in dependency order, and `php spark migrate:status` reports all five as applied in batch 1. They added `mod_modules`, `log_audit_logs`, organization prerequisites, authorization/configuration tables, approval workflow tables, and `out_outlets`. No migration drops, truncates, or renames existing live tables. The original two `usr_accounts` rows, the one `usr_roles` row, and the CodeIgniter `migrations` table were preserved; the framework table now contains the five migration records. The current count-only inventory shows no company, branch, outlet, grant, scope, or approver records. The two module registry rows and eight action-catalog rows originated as migration-owned seeds. Their current status, enabled flags, and other configuration values were not selected during this read-only recheck.

On 2026-10-25, a second read-only check with `php spark migrate:status`, `php spark db:table --show`, and `php spark db:table <table> --metadata` confirmed the same five applied migrations and 20-table inventory. Metadata-only column inspection covered all 20 live tables; no row values, account identities, password hashes, or secrets were read. Table row/column counts were unchanged from the inventory below. Live indexes and foreign keys were not re-queried during this check; use the applied migration definitions for migration-created keys and relationships, and perform another metadata-only constraint check if a proposed implementation depends on their current live state.

### Latest read-only live inventory

Rechecked the live database on 2026-10-25 using `php spark migrate:status`, `php spark db:table --show`, and metadata-only `db:table` output for all 20 tables. The live table list contains 20 tables. The inventory below reports table names, row counts, and column counts only; no account, role, or business-record values were read. The previous 2026-10-23 inspection had already recorded detailed metadata for selected tables; the 2026-10-25 check reconfirmed their current columns and inspected the remaining table column metadata.

| Live table | Rows | Columns |
| --- | ---: | ---: |
| `apr_module_approvers` | 0 | 4 |
| `apr_request_stages` | 0 | 8 |
| `apr_requests` | 0 | 13 |
| `log_audit_logs` | 0 | 8 |
| `migrations` | 5 | 7 |
| `mod_module_actions` | 8 | 10 |
| `mod_module_branches` | 0 | 4 |
| `mod_module_companies` | 0 | 3 |
| `mod_modules` | 2 | 14 |
| `org_branches` | 0 | 7 |
| `org_companies` | 0 | 6 |
| `org_reporting_lines` | 0 | 6 |
| `out_outlets` | 0 | 12 |
| `rbac_role_module_actions` | 0 | 3 |
| `rbac_user_branch_scopes` | 0 | 6 |
| `rbac_user_company_scopes` | 0 | 5 |
| `rbac_user_module_actions` | 0 | 4 |
| `sys_mandatory_approval_policies` | 0 | 5 |
| `usr_accounts` | 2 | 15 |
| `usr_roles` | 1 | 8 |

Metadata output reconfirmed that `usr_accounts` includes nullable `avatar_url` and `birthday`, and that `usr_roles`, `mod_modules`, and `out_outlets` have the documented primary-key fields. The five application migrations remain applied. This check did not inspect row contents, re-query every table's indexes/foreign keys, or perform writes. For migration-created keys and relationships, the applied migration files remain the authoritative definition.

## Confirmed table naming and ownership rules

The project owner confirmed lowercase `snake_case`, meaningful functional prefixes, consistent plural naming following existing tables, one documented owner per table, and a read-only schema/dependency reconciliation before creating or renaming tables. The current prefix registry is recorded in `PROJECT_BLUEPRINT.md`: `usr_`, `rbac_`, `mod_`, `org_`, `out_`, `tsk_`, `dpl_`, `doc_`, `apr_`, `log_`, and `ntf_`. Prefix entries are conventions, not evidence that example tables exist.

| Verified live table | Prefix assessment | Purpose and owner | Relationships / status |
| --- | --- | --- | --- |
| `migrations` | Framework-managed name; no domain prefix | CodeIgniter migration bookkeeping; shared platform infrastructure | Live InnoDB table, now records five applied migrations. Preserved as a framework exception. |
| `usr_accounts` | `usr_` conforms | Account identities, profile, and status; shared Login/User Management component | Existing table and rows preserved; `role_id` references `usr_roles.role_id`. |
| `usr_roles` | Existing legacy-compatible name retained | Existing role definitions; shared RBAC component | Existing table and row preserved; referenced by `usr_accounts` and new RBAC role-grant rows. |
| `mod_modules` | `mod_` conforms | Shared module registry; Module Registry / Module Management | Created by migration 000001; parent self-reference and creator FK to `usr_accounts`. Contains the two DRAFT scope modules. |
| `log_audit_logs` | `log_` conforms | Shared audit history; Audit component | Created by migration 000001; nullable actor/module FKs; no test or business events inserted during migration. |
| `org_companies` | `org_` conforms | Shared organization prerequisites | Created by migration 000002; unique company code and composite key for branch relationship. Empty after migration. |
| `org_branches` | `org_` conforms | Shared organization prerequisites | Created by migration 000002; company FK and unique `(company_id, branch_id)` key. Empty after migration. |
| `org_reporting_lines` | `org_` conforms | Employee Management hierarchy | Created by migration 000002; one primary manager per employee, FKs to `usr_accounts`. Empty after migration. |
| `mod_module_actions` | `mod_` conforms | Module Management action availability and approval settings | Created by migration 000003; FK to `mod_modules`; eight action catalog rows, disabled by default. |
| `rbac_role_module_actions` | `rbac_` conforms | Shared role grants | Created by migration 000003; `(role_id, module_action_id)` grants reference legacy `usr_roles` and module actions. Empty after migration. |
| `rbac_user_module_actions` | `rbac_` conforms | Shared per-user grants | Created by migration 000003; user, module-action, and grantor FKs. Empty after migration. |
| `mod_module_companies` | `mod_` conforms | Per-module company allowlist; Module Management | Created by migration 000003; composite primary key `(module_id, company_id)`; FKs to `mod_modules` and `org_companies`. Empty after migration. |
| `mod_module_branches` | `mod_` conforms | Per-module branch allowlist; Module Management | Created by migration 000003; composite primary key `(module_id, company_id, branch_id)`; FKs to `mod_modules` and the matching `(company_id, branch_id)` in `org_branches`. Empty after migration. |
| `rbac_user_company_scopes` | `rbac_` conforms | Per-user, per-module company scopes; Account Management | Created by migration 000003; composite primary key `(user_id, module_id, company_id)`; FKs to `usr_accounts`, `mod_modules`, `org_companies`, and grantor account. Empty after migration. |
| `rbac_user_branch_scopes` | `rbac_` conforms | Per-user, per-module branch scopes; Account Management | Created by migration 000003; composite primary key `(user_id, module_id, company_id, branch_id)`; FKs to `usr_accounts`, `mod_modules`, matching `org_branches` pair, and grantor account. Empty after migration. |
| `sys_mandatory_approval_policies` | `sys_` conforms | Global mandatory approval policy | Created by migration 000003; unique action code. Empty after migration; no mandatory rule is implicitly enabled. |
| `apr_module_approvers` | `apr_` conforms | Ordered module-action approver configuration; Module Management | Created by migration 000004; primary key `(module_action_id, stage_order)`; FKs to `mod_module_actions` and `usr_accounts`. Empty after migration. |
| `apr_requests` | `apr_` conforms | Pending/applied Outlet List and Employee Management requests; Approval Queue | Created by migration 000004; unique request code; FKs to action, requester, company, and composite company/branch. Request payload is LONGTEXT for MariaDB 10.1 compatibility. Empty after migration. |
| `apr_request_stages` | `apr_` conforms | Sequential approval decisions; Approval Queue | Created by migration 000004; unique request/stage order; FKs to `apr_requests` and the assigned `usr_accounts` approver. Empty after migration. |
| `out_outlets` | `out_` conforms | Outlet List business records | Created by migration 000005; composite branch FK, unique branch/code, archive and attribution fields. Empty after migration. |

The CI migration count-only verification after application confirmed 20 tables on MariaDB 10.1.19: the three retained base tables plus 17 migration-created tables. Row counts are `usr_accounts` 2, `usr_roles` 1, `migrations` 5, `mod_modules` 2, and `mod_module_actions` 8; all other newly created tables contain 0 rows. No fixture or operational business data was seeded. `org_positions` and departments, account activation history, notification queues, and general-purpose audit viewer/export functionality are outside these migrations.

The verified live table registry above supersedes earlier pending-state notes in the history below. `usr_roles` and CodeIgniter's `migrations` remain preserved naming exceptions. Do not infer that other historical tables documented below remain live.

Migration 000001 creates:

- `mod_modules`: unsigned auto-increment `module_id`; nullable `parent_module_id`; unique `module_code`; `module_name`, `short_name`, `module_type`, `description`, descriptive `route_path`, `icon`, `sort_order`, `status`, nullable `created_by`, and timestamps. It defines parent and creator foreign keys, plus parent and status/order indexes. Module types are `MASTERFILE`, `TRANSACTION`, `REPORT`, and `ADMIN`; statuses are `DRAFT`, `ACTIVE`, and `INACTIVE`.
- `log_audit_logs`: unsigned auto-increment `audit_log_id`; nullable actor and module IDs; event code; result (`SUCCESS`, `DENIED`, `FAILURE`); nullable before/after LONGTEXT payloads; and timestamp. It defines actor/date, module/date, and event/date indexes and foreign keys to `usr_accounts` and `mod_modules` with `SET NULL` on delete.

All five migration files use InnoDB and `utf8mb4_unicode_ci` and were applied successfully to the confirmed MariaDB 10.1.19 server. Every migration `down()` method now throws to prevent automated removal of registry, audit, organization, access, approval, or outlet structures. Do not use rollback as a routine deployment operation.

## Historical verification status

The detailed inventories below record dated historical observations and must not be treated as the current live schema. The pre-migration inspection on 2026-10-23 found `migrations`, `usr_accounts`, and `usr_roles`, with 0, 2, and 1 rows. The current post-migration inventory above supersedes that snapshot. Historical details are retained for traceability only; re-inspect live MySQL before relying on them for implementation. Older observations included a 32-table inventory with 4 accounts, 3 roles, 2 module registry rows, one company, and one branch. Earlier profile schema work and backups described below are historical events, not evidence that those objects or rows still exist.

**Historical login schema repair and Superadmin account setup (2026-10-23):** Immediately before the schema repair, a read-only table inventory of `pos_workspace_v3` showed only `usr_roles` (8 columns, 0 rows); `usr_accounts` was absent. This differed from the earlier 32-table inventory. The reason for that difference is unknown. With explicit owner approval, `usr_roles.role_id` was changed from signed to unsigned `BIGINT(20)`, and `usr_accounts` was created using the documented schema below. The owner then approved creation of one active Superadmin account; no credential value or account identity is recorded here. Post-write checks verified one matching active Superadmin assignment. A later inspection found two account rows and one role row; an additional account row's origin and timing were not established or inspected. The latest inspection now also confirms the framework `migrations` table exists with zero rows. Do not infer that earlier missing tables or records were deleted by the application cleanup.

The 2026-10-23 application cleanup itself made no database changes. At that time, the earlier read-only inventory found module/ACL/company/branch/approval/audit/report/notification/settings tables and the inspected records described above. The application cleanup did not remove those database structures; their absence from the later inventory has an unknown cause. The prepared SQL cleanup plan is separate, review-only, and has not been executed.

The former application migration proposal for `mod_module_actions` was inspected as pending and then removed from application source during the Login + Landing Page cleanup. The table was absent from the live table inventory; no migration or SQL was applied. Its former schema proposal is retained below as historical documentation only.

Do not infer schema from controller queries, model assumptions, product requirements, or a database name. Record a table or column as verified only when supported by an application migration or another authoritative schema artifact. Keep test fixtures separate from application schema.

### Owner-approved login schema repair performed 2026-10-23

The following DDL was executed against `pos_workspace_v3` only after confirming `usr_accounts` was absent, `usr_roles` contained no rows, and the existing role table definition. This is a record of the completed operation, **not a script to rerun**. The role key was aligned to the documented unsigned account key, then the account table was created with its documented indexes and role foreign key. Account/role rows were inserted later in the separately approved Superadmin account setup described above.

```sql
ALTER TABLE `usr_roles`
  MODIFY COLUMN `role_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;

CREATE TABLE `usr_accounts` (
  `user_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role_id` bigint(20) unsigned DEFAULT NULL,
  `position_id` bigint(20) unsigned DEFAULT NULL,
  `default_module_id` bigint(20) unsigned DEFAULT NULL,
  `account_status` enum('PENDING','ACTIVE','SUSPENDED','INACTIVE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_usr_accounts_username` (`username`),
  UNIQUE KEY `uq_usr_accounts_employee_code` (`employee_code`),
  UNIQUE KEY `uq_usr_accounts_email` (`email`),
  KEY `idx_usr_accounts_role` (`role_id`),
  KEY `idx_usr_accounts_status` (`account_status`),
  CONSTRAINT `fk_usr_accounts_role` FOREIGN KEY (`role_id`) REFERENCES `usr_roles` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Verified live account and role schema

The account/profile and selected module facts below describe dated live MySQL observations, not migration-defined schema. The former module-action migration was removed from application source during the Login + Landing Page cleanup and was never applied.

### `usr_accounts`

| Column | Verified live definition |
| --- | --- |
| `user_id` | `bigint(20) unsigned`, not null, auto-increment primary key |
| `username` | `varchar(50)`, nullable; screenshot shows unique key |
| `employee_code` | `varchar(50)`, nullable; screenshot shows unique key |
| `first_name` | `varchar(100)`, nullable |
| `last_name` | `varchar(100)`, nullable |
| `avatar_url` | `varchar(2048)`, nullable; added 2026-10-22 |
| `birthday` | `date`, nullable; added 2026-10-22 |
| `email` | `varchar(191)`, not null |
| `password_hash` | `varchar(255)`, nullable |
| `role_id` | `bigint(20) unsigned`, nullable |
| `position_id` | `bigint(20) unsigned`, nullable |
| `default_module_id` | `bigint(20) unsigned`, nullable |
| `account_status` | `enum('PENDING','ACTIVE','SUSPENDED','INACTIVE')`, not null, default `PENDING` |
| `created_at` | `datetime`, not null, default `CURRENT_TIMESTAMP` |
| `updated_at` | `datetime`, nullable; screenshot shows `ON UPDATE CURRENT_TIMESTAMP` |

The screenshot showed unique keys for `username`, `employee_code`, and `email`, and non-unique keys for `role_id` and `account_status`. The later live metadata query confirmed indexes `PRIMARY(user_id)`, `uq_usr_accounts_username(username)`, `uq_usr_accounts_employee_code(employee_code)`, `uq_usr_accounts_email(email)`, `idx_usr_accounts_role(role_id)`, and `idx_usr_accounts_status(account_status)`, plus `fk_usr_accounts_role(role_id) -> usr_roles(role_id)`. These definitions were confirmed again by `SHOW CREATE TABLE` after the owner-approved table creation. The new profile columns are nullable and have no indexes or foreign keys. No account identities or credential values are recorded here.

### Approved profile fields added 2026-10-22

The owner approved adding a Google profile avatar URL and an optional user-entered birthday. The following additive DDL was executed against `pos_workspace_v3` after confirming both columns were absent and creating a timestamped pre-change backup outside the web root. The backup filename is `pos_workspace_v3_pre_profile_fields_20261022_204140.sql` under the local temporary directory:

```sql
ALTER TABLE `usr_accounts`
  ADD COLUMN `avatar_url` varchar(2048) DEFAULT NULL AFTER `last_name`,
  ADD COLUMN `birthday` date DEFAULT NULL AFTER `avatar_url`;
```

Post-change `php spark db:table usr_accounts --metadata` confirmed `avatar_url` as nullable `varchar(2048)` and `birthday` as nullable `date`. The backup file was non-empty (55,956 bytes); restoring it to a scratch database was not tested. No account row was changed by the DDL. Google login stores only validated HTTPS Google-hosted picture URLs. The birthday column remains in the database, but its editor was removed from the authenticated account summary during the Login + Landing Page cleanup.

Rollback SQL, **not run**:

```sql
ALTER TABLE `usr_accounts`
  DROP COLUMN `birthday`,
  DROP COLUMN `avatar_url`;
```

Dropping these columns after profile data is entered permanently loses birthdays and saved avatar URLs. Before any rollback, back up and export those values and obtain separate owner approval.

### `usr_roles`

The current verified columns are `role_id` (`bigint(20) unsigned`, primary key, auto-increment), `role_code` (`varchar(50)`, not null), `role_name` (`varchar(120)`, not null), nullable `description` (`varchar(255)`), `is_system_role` (`tinyint(1)`, not null, default `0`), `status` (`enum`, not null, default `ACTIVE`), `created_at` (`datetime`, not null, default `CURRENT_TIMESTAMP`), and nullable `updated_at` (`datetime`). `role_id` was changed from signed to unsigned on 2026-10-23 with explicit owner approval while the table contained no rows, so the documented account-role foreign key could be created. The current `usr_roles` table has 1 row.

An earlier live inspection found these role records; their presence in the current table was not established:

| Role ID | Role code | Role name | Record status |
| ---: | --- | --- | --- |
| 1 | `SUPERADMIN` | Super Administrator | `ACTIVE` |
| 2 | `USER` | Standard User | `ACTIVE` |
| 3 | `VIEWER` | Viewer | `ACTIVE` |

`usr_roles.status` is `enum('ACTIVE','INACTIVE')`, not null, default `ACTIVE`. The role table uses `utf8mb4_unicode_ci`, with `PRIMARY(role_id)` and unique `uq_usr_roles_code(role_code)`. Current account login checks require `usr_accounts.account_status = 'ACTIVE'` and an active assigned role; they must not treat `PENDING`, `SUSPENDED`, or `INACTIVE` accounts as active. The latest inventory did not inspect account or role values, so row counts alone do not establish whether a login is possible.

### Historical account registration and credential-login migration proposal

**Status: historical proposal, not executed.** The SQL below is retained as context and must not be used as a current migration instruction. The present live schema was separately verified after the owner-approved repair above.

The proposed structural migration preserves existing column types, unsigned attributes, collation, indexes, and the role foreign key. It makes only the username and pending-account columns nullable/unique as needed:

```sql
ALTER TABLE `usr_accounts`
  ADD COLUMN `username` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `user_id`,
  MODIFY COLUMN `employee_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  MODIFY COLUMN `first_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  MODIFY COLUMN `last_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  MODIFY COLUMN `role_id` bigint(20) unsigned DEFAULT NULL,
  ADD UNIQUE KEY `uq_usr_accounts_username` (`username`);
```

MySQL/MariaDB unique indexes permit multiple `NULL` entries; existing accounts therefore need non-null distinct usernames before enabling credential login. A privacy-preserving deterministic candidate that does not use names, email, or employee codes is `pwv3_u_<user_id>`. The following backfill is a **separate proposed data update** and is not approved or run:

```sql
UPDATE `usr_accounts`
SET `username` = CONCAT('pwv3_u_', CAST(`user_id` AS CHAR))
WHERE `username` IS NULL;
```

The username format/backfill above was a historical proposal. Whether that exact backfill was applied is not established by the screenshot. New Google registrations retain unique verified email and `PENDING` status with no role, employee code, or module assignments; when Google supplies them, first and last name may be populated from the verified profile claims rather than invented placeholders. Pending accounts still cannot authenticate to protected pages.

#### Proposed rollback SQL (not run)

First confirm that no account has null values in columns that rollback would make mandatory:

```sql
SELECT COUNT(*) AS rows_blocking_not_null_rollback
FROM `usr_accounts`
WHERE `role_id` IS NULL
   OR `employee_code` IS NULL
   OR `first_name` IS NULL
   OR `last_name` IS NULL;
```

If the result is zero, the compatibility rollback restores the old required-column rules while **retaining** the username column and its values:

```sql
ALTER TABLE `usr_accounts`
  MODIFY COLUMN `employee_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  MODIFY COLUMN `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  MODIFY COLUMN `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  MODIFY COLUMN `role_id` bigint(20) unsigned NOT NULL;
```

This rollback cannot support accounts created with missing role/name/employee data. Before any rollback, disable registration and credential features, resolve those accounts through the approved administrator process, and rerun the precheck. A full structural rollback that drops usernames is destructive to the new credential identifiers and must not be used unless the owner separately approves that data loss and has a verified export. No drop-column statement is included in the approved-ready rollback.

#### Migration validation and backup/restore plan

Before migration: take a timestamped `mysqldump` backup of `pos_workspace_v3` (all tables are InnoDB, so `--single-transaction` is appropriate), verify the command exit code and nonzero dump file size, and restore that dump into a separately named scratch database. Compare table/column/index/foreign-key metadata and exact per-table row counts between source-at-backup and scratch restore; do not print or export account identities or hashes. Retain the backup outside the web root and do not overwrite an existing backup. The backup and restore have **not** been performed or verified.

In a scratch database first, apply the structural DDL, verify the exact `SHOW CREATE TABLE`, unique username index, nullability and preserved role FK, and run the backfill. Check `username IS NULL` count is zero and duplicate non-null username group count is zero. Test insert shape for a pending row with verified unique email and null username/employee code/names/role, then roll back the scratch DB. Test the compatibility rollback against zero-null data and test that it refuses/does not run when nullable pending rows exist. After separate final owner approval and a verified backup restore, execute against production in a maintenance window; repeat metadata/count/uniqueness checks, and do not enable dependent application code until validation succeeds.

Useful non-personal pre/post checks:

```sql
SELECT COUNT(*) AS null_username_count
FROM `usr_accounts`
WHERE `username` IS NULL;

SELECT COUNT(*) AS duplicate_username_groups
FROM (
  SELECT `username`
  FROM `usr_accounts`
  WHERE `username` IS NOT NULL
  GROUP BY `username`
  HAVING COUNT(*) > 1
) AS `duplicates`;
```

### Session storage

`app/Config/Session.php` configures CodeIgniter's `FileHandler`, so the current application session setup does not require a database session table. This is a configuration fact, not a claim about a live database table.

## Test-support fixture schema (not production schema)

`tests/_support/Database/Migrations/2020-02-22-222222_example_migration.php` sets `$DBGroup = 'tests'` and creates a test table named `factories`. The migration declares:

| Column | Definition in migration |
| --- | --- |
| `id` | Added with Forge shorthand `addField('id')`; the project migration does not spell out the generated SQL type. |
| `name` | `varchar`, constraint 31 |
| `uid` | `varchar`, constraint 31 |
| `class` | `varchar`, constraint 63 |
| `icon` | `varchar`, constraint 31 |
| `summary` | `varchar`, constraint 255 |
| `created_at` | `datetime`, nullable |
| `updated_at` | `datetime`, nullable |
| `deleted_at` | `datetime`, nullable |

The migration calls `addKey` for `name`, `uid`, (`deleted_at`, `id`), and `created_at`. It declares no foreign keys. The test-support model sets `id` as its model primary key and enables timestamps; a test temporarily enables soft deletes. These test artifacts do not establish production database schema. The migration itself does not explicitly declare a primary key.

## Migration/configuration facts

- `app/Config/Database.php` selects the MySQLi driver. The runtime connection selected `pos_workspace_v3`; secret connection values were not read or recorded.
- `app/Config/Migrations.php` enables migrations and configures the migration-history table name as `migrations`. This configuration does not prove the table exists in any database.
- The test-support `factories` fixture above is not production schema. The former module-action migration source was removed after a prior `migrate:status` check showed it pending; the live migration-history table contained zero rows in the 2026-10-23 read-only inventory.

### Historical proposal, unapplied and removed from source: module action configuration

The removed application migration file had defined a proposed `mod_module_actions` table. The proposal was never applied and does not describe live schema.

| Column | Proposed migration definition |
| --- | --- |
| `module_action_id` | `bigint unsigned`, auto-increment primary key |
| `module_id` | `bigint unsigned`, required; foreign key to `mod_modules.module_id` with `RESTRICT` on update/delete |
| `action_code` | `varchar(50)`, required |
| `is_enabled` | `tinyint(1)`, required, default `1` |
| `requires_approval` | `tinyint(1)`, required, default `0` |
| `created_at` | nullable `datetime` |
| `updated_at` | nullable `datetime` |

The migration creates a unique key on `(module_id, action_code)`. Its seed code inserts enabled rows only for statically supported actions: `MODULE_SETTINGS` (`VIEW`, `CREATE`, `UPDATE`, `DELETE`) and `COMPANY_BRANCH` (`VIEW`, `CREATE`, `UPDATE`). The approved defaults set Required Approval for Module Settings `CREATE`, `UPDATE`, and `DELETE`, and Company & Branch `CREATE` and `UPDATE`. Unsupported actions are not seeded or executable through registry metadata.

This proposal was never executed; the read-only table inventory confirmed no live table or seed rows for it. The related Module Settings and Company Management authorization code has been removed from the current application baseline. No SQL writes, migrations, permission grants, or approver assignments occurred during the cleanup.

## Relationships

The live `usr_accounts.role_id` foreign key to `usr_roles.role_id` is verified. Other application foreign keys have not been comprehensively inventoried for this review. The test-support migration declares no relationships or foreign keys.

## Historical selected module and workflow schema (read-only inspection, 2026-10-23; superseded by the current inventory above)

The following facts were confirmed against the live MariaDB `pos_workspace_v3` database using read-only schema and row-count queries. These are live-schema observations, not migration-defined guarantees. The MariaDB server reported `10.1.19-MariaDB`. No secrets or account identity values were displayed.

### `mod_modules`

`SHOW CREATE TABLE` confirmed InnoDB with `utf8mb4_unicode_ci`. The table has a `bigint unsigned` auto-increment primary key `module_id`; nullable `parent_module_id`; unique `module_code`; `module_name`; `module_type` enum (`MASTERFILE`, `TRANSACTION`, `REPORT`, `ADMIN`); description and route/component/table-prefix metadata; icon and sort order; approval/company-filter/branch-filter/hierarchy-report flags; status enum (`DRAFT`, `ACTIVE`, `INACTIVE`); creator and timestamps. The parent column has an index and self-referencing foreign key; status/sort and parent/status/sort indexes are present. The live table contained 12 module records at inspection, including `BRANCH_MANAGEMENT` in `DRAFT` and the existing active `COMPANY_BRANCH` module. This does not mean `route_path` or `component_key` may execute or dispatch a route; route definitions and controller mappings remain static application code.

#### Subsequent live registry data changes (2026-10-23)

- The `BRANCH_MANAGEMENT` DRAFT row (module ID 12) was first deleted after a read-only check found no references. A later, separately approved cleanup removed every module except `MODULE_SETTINGS` (ID 2) and `COMPANY_BRANCH` (ID 11).
- Before the broader cleanup, the live registry had 11 rows: 9 cleanup targets and the 2 retained modules. The removed module set had 13 `acl_module_access` rows, 6 dependent `acl_module_permissions` rows, 9 `acl_user_company_scope` rows, 9 `acl_user_branch_scope` rows, 9 `acl_module_companies` rows, and 9 `acl_module_branches` rows. Two `usr_accounts.default_module_id` values pointed to modules being removed.
- The owner explicitly approved deleting those module-specific access/scope rows and clearing the two default-module references while preserving user accounts and both retained modules with their related records. A full-database backup was created outside the web root before the transaction.
- The transaction deleted the 9 module records and the linked rows above, and cleared the 2 account default-module values. Checks found zero attached approval rules, approvers, requests, decisions, audit events, local policy categories, report definitions/schedules/runs/recipients, notifications, or related tasks. The `sys_mandatory_approval_policies` table had zero rows and was not changed because its entries are global/action-based rather than module-specific.
- Post-transaction live queries confirmed exactly the two retained module rows remain and both are `ACTIVE`; no non-retained module rows or account defaults referencing missing modules remain. No schema or migration was changed.

### `mod_local_policy_categories`

The live table exists with `module_policy_category_id`, `module_id`, `category_code`, `is_permitted`, and timestamps; it has a unique key on `(module_id, category_code)` and a foreign key to `mod_modules`. It contained zero rows at inspection. No policy category definitions were inserted as part of the Module Settings work. The precise meaning and write semantics of `is_permitted` should follow the approved policy design; the current Module Settings interface does not write this table.

### Access, approval, audit, report, and notification tables

- The live `acl_permissions` catalog contains the action codes used by the inspected application. Individual user grants remain separate from the proposed module-wide action table; the new application migration has not been applied.
- `apr_module_approval_rules`, `apr_approvers`, `apr_decisions`, `apr_requests`, and `sys_mandatory_approval_policies` were present at inspection; the inspected approval-rule, approver, decision, request, and mandatory-policy tables had no configured rows. This is not evidence that a Company Management approval workflow can complete.
- Read-only `SHOW CREATE TABLE` inspection confirmed `apr_requests.target_user_id` is currently `NOT NULL`, `module_id` is required, and `request_type` currently supports only `SHARE_COMPANY`, `SHARE_BRANCH`, `GRANT_MODULE`, `REVOKE_MODULE`, `CHANGE_PERMISSION`, `CHANGE_SCOPE`, and `OTHER`. It has a unique request-code key, status/priority/date index, and foreign keys to requester, target user, and module. `apr_decisions` supports ordered stages and `SUPERVISOR`, `MODULE_ADMIN`, `FINAL_APPROVER`, and `CUSTOM` stage codes; its request foreign key cascades on request deletion. `apr_module_approval_rules` is unique by `(module_id, action_code)`. `apr_approvers` stores ordered approvers and backup flags. `sys_mandatory_approval_policies` is unique by action code and has no module key. No approval data rows were present.
- In that historical live inventory, `audit_logs` existed and had no dedicated `approval_request_id`; the then-current shared audit writer allowlisted payload fields. It was absent in the subsequent pre-migration inventory. The current installed audit table is `log_audit_logs`, created by the approved migration set above.
- `rpt_definitions`, `rpt_schedules`, `rpt_runs`, `rpt_recipients`, and `ntf_notifications` exist. Module Settings reads limited configuration/status summaries from these areas and does not change report, recipient, schedule, run, or notification data.
- A complete current column/index/foreign-key inventory for every table in these domains was not retained in this inspection record. Do not infer omitted definitions from application queries. Re-run `SHOW CREATE TABLE` and `information_schema` checks before any schema proposal or migration.

### Proposed, not implemented: company-target approval requests

This is a database design proposal only. It has not been approved or executed. Its purpose is to extend the existing request header with an explicit company target while retaining existing user-request meaning and approval stage tables. The proposed request kinds map to canonical actions as follows: `COMPANY_CREATE` → `CREATE`; `COMPANY_UPDATE` and `COMPANY_STATUS_CHANGE` → `UPDATE`. The module is resolved by the active `COMPANY_BRANCH` code at runtime; do not hardcode its current ID. For create requests, `target_company_id` remains null until final application. For edit/status requests, it contains the existing company ID.

Proposed MariaDB 10.1.19-compatible DDL, in execution order:

```sql
ALTER TABLE `apr_requests`
  MODIFY COLUMN `target_user_id` bigint(20) unsigned DEFAULT NULL,
  ADD COLUMN `target_company_id` bigint(20) unsigned DEFAULT NULL AFTER `target_user_id`,
  MODIFY COLUMN `request_type` enum(
    'SHARE_COMPANY',
    'SHARE_BRANCH',
    'GRANT_MODULE',
    'REVOKE_MODULE',
    'CHANGE_PERMISSION',
    'CHANGE_SCOPE',
    'OTHER',
    'COMPANY_CREATE',
    'COMPANY_UPDATE',
    'COMPANY_STATUS_CHANGE'
  ) COLLATE utf8mb4_unicode_ci NOT NULL,
  ADD KEY `idx_apr_requests_company_status` (`target_company_id`, `request_status`, `submitted_at`),
  ADD CONSTRAINT `fk_apr_requests_company`
    FOREIGN KEY (`target_company_id`) REFERENCES `org_companies` (`company_id`)
    ON DELETE RESTRICT;
```

The nullable user target preserves the existing `target_user_id` foreign key for user requests while allowing company requests to have no artificial target user. The new company ID uses the verified unsigned `bigint(20)` type and references the verified `org_companies.company_id`; the FK prevents orphaned update/status requests and prevents deleting a company referenced by a request. MariaDB 10.1 does not provide an enforced check suitable for matching targets to request types, so application validation must require a user target for legacy user requests, no target for a company-create request, and a company target (with no user target) for company update/status requests. Existing requests keep their target user, and the new company target defaults to null. No new approval table is proposed because `apr_decisions` already stores stage assignments/decisions and is linked to `apr_requests`.

Do not insert global mandatory policy rows or local approval rules/approvers as part of this DDL. The action-only unique global policy table can affect every module using `CREATE` or `UPDATE`; the owner must approve the canonical action catalog and global mapping separately. Company actions must fail closed if any required stage has no valid approver. Local rules cannot disable a mandatory global requirement. Resolve whether all configured approvers at a stage must approve or whether one valid approver is sufficient before implementation; the existing schema permits multiple approvers but does not encode quorum.

Pending requested values remain an immutable, validated JSON payload in the existing `request_details` longtext column. Company records are not changed at submission. At each decision and final application, the service must revalidate request state, active account/role, explicit module/action permission, company scope, stage assignment, and self-approval policy. Update/status approval locks the target company and request in one transaction. Create approval inserts the new INACTIVE company, links its generated ID to `target_company_id`, marks the request applied, records the success audit entry, and commits as one transaction. The final approver decision, company mutation, request status/`applied_at`, and success audit insert must share this transaction. A failed audit insert rolls back the protected mutation and applied status. If a valid route disappears, leave the protected request unapplied and block completion; do not auto-approve or substitute a fallback approver. Audit linkage can include `request_id` in the allowlisted JSON details because `audit_logs` has no dedicated request FK; adding a dedicated audit column is not included in this minimal proposal.

Read-only pre/post checks for a separately approved migration:

```sql
SHOW CREATE TABLE `apr_requests`;
SHOW CREATE TABLE `org_companies`;
SHOW CREATE TABLE `apr_decisions`;
SHOW CREATE TABLE `apr_module_approval_rules`;
SHOW CREATE TABLE `apr_approvers`;
SHOW CREATE TABLE `sys_mandatory_approval_policies`;
SHOW CREATE TABLE `audit_logs`;

SELECT `request_type`, `request_status`, COUNT(*) AS row_count
FROM `apr_requests`
GROUP BY `request_type`, `request_status`
ORDER BY `request_type`, `request_status`;

SELECT COUNT(*) AS null_target_user_rows
FROM `apr_requests`
WHERE `target_user_id` IS NULL;

SELECT COUNT(*) AS missing_company_targets
FROM `apr_requests` r
LEFT JOIN `org_companies` c ON c.`company_id` = r.`target_company_id`
WHERE r.`target_company_id` IS NOT NULL AND c.`company_id` IS NULL;

SELECT COUNT(*) AS company_requests_missing_expected_target
FROM `apr_requests`
WHERE (`request_type` = 'COMPANY_CREATE'
       AND (`target_user_id` IS NOT NULL OR `target_company_id` IS NOT NULL))
   OR (`request_type` IN ('COMPANY_UPDATE', 'COMPANY_STATUS_CHANGE')
       AND (`target_user_id` IS NOT NULL OR `target_company_id` IS NULL));
```

Expected immediately after DDL and before request submissions: the preexisting request type/status counts are unchanged; all legacy `target_user_id` values remain unchanged; `target_company_id` is null for every legacy row; the new FK/index exist; and the new request-type group counts are zero. The final target-shape query should report zero invalid rows after application behavior is implemented and test requests are created.

DDL rollback is not a transaction in MariaDB 10.1. Before considering rollback, verify that there are no company request types and no non-null `target_company_id` values. If new company approval history exists, removing the column or enum values destroys request meaning; preserve the schema and migrate history through a separately approved archival plan. Restoring `target_user_id NOT NULL` is safe only if no row has a null user target. Dropping `fk_apr_requests_company`, its index and column, restoring the original enum, and restoring `target_user_id NOT NULL` are destructive once company requests exist and are not authorized by this proposal.

No new request columns/tables, mandatory-policy mappings, approval rules, approvers, decisions, grants, or scope rows were written during the Module Settings work. Review and explicit database approval are required before applying this SQL. Approval completion and atomic company mutation/audit behavior remain unimplemented and untested.

## Verification needed

The 2026-10-25 read-only check verified live migration status, all table names/row counts/column counts, and column metadata for all 20 tables. Complete live indexes and foreign keys were not comprehensively re-queried; rely on migration-defined constraints only where applicable and recheck live metadata before work depending on exact current constraints. Do not place credentials, account identities, password hashes, or tokens in this file or in logs.

## Product requirement mapping and verification boundary (reviewed 2026-10-25)

This section maps the requested logical entities to authoritative migration artifacts; it does not add schema or claim feature completion. `app/Database/Migrations/2026-10-23-000001` through `000005` are the source for migration-created schema. Their definitions provide tables for module metadata/actions, role/user action grants, module and user company/branch scopes, reporting lines, approval requests/stages/approvers, audit events, organizations, and outlets. Account identity and role definitions predate those migrations and are recorded separately above.

The documented migration set does not establish an account-level single-active-session registry, notification delivery queue, a fully specified handler/action registry lifecycle, or effective-access-preview persistence. CI4 currently uses file-backed sessions per `app/Config/Session.php`; session files are not an account-level one-session policy. Any additional entity or change requires a schema/behavior gap analysis and explicit approval before migration or live-data changes.

Live MySQL **was inspected read-only on 2026-10-25** as documented above. The metadata check did not inspect row values or re-query every live index/foreign key. Requirements in `PROJECT_BLUEPRINT.md`, source-code queries, and the database name do not independently verify live tables, columns, indexes, foreign keys, or row state. Recheck exact live constraints before a schema proposal or implementation that depends on them.
