-- POS Workspace V3 login-foundation reset plan
-- Target database: pos_workspace_v3 (MariaDB 10.1 compatible)
--
-- This is a review-only plan. The preflight SELECTs are read-only.
-- All data/schema changes below are deliberately commented out.
-- Do not uncomment or execute them without separate approval to apply this exact plan.
--
-- Before any approved execution:
-- 1. Take a full backup outside the web root and verify that it is non-empty.
-- 2. Review the preflight output and stop if any count differs from the expected values.
-- 3. Run during a maintenance window with the application stopped.
-- 4. Do not disable foreign-key checks. MariaDB DDL implicitly commits; the backup
--    is the recovery point if a later DROP TABLE fails.
--
-- Approved dispositions represented by this plan:
-- * Preserve usr_accounts, usr_roles, migrations, all ACL/RBAC/scope tables and rows,
--   mod_modules and its two rows, audit_logs and its one row, org_companies,
--   org_branches, sys_settings, and sys_mandatory_approval_policies.
-- * Clear the three account position references, then drop the organization
--   position/hierarchy tables.
-- * Drop only the listed empty approval, local-policy, notification, report, task,
--   department, and reporting-line tables.
-- * No account, role, company, branch, ACL, module-registry, audit, or settings row
--   is deleted by this plan.

USE `pos_workspace_v3`;

-- Confirm the selected database and key preserved-row counts.
SELECT DATABASE() AS current_database;

SELECT
    (SELECT COUNT(*) FROM `usr_accounts`) AS account_rows,
    (SELECT COUNT(*) FROM `usr_roles`) AS role_rows,
    (SELECT COUNT(*) FROM `mod_modules`) AS module_rows,
    (SELECT COUNT(*) FROM `audit_logs`) AS audit_rows,
    (SELECT COUNT(*) FROM `org_companies`) AS company_rows,
    (SELECT COUNT(*) FROM `org_branches`) AS branch_rows,
    (SELECT COUNT(*) FROM `sys_settings`) AS system_setting_rows,
    (SELECT COUNT(*) FROM `usr_accounts` WHERE `position_id` IS NOT NULL) AS account_position_references,
    (SELECT COUNT(*)
       FROM `usr_accounts` a
       JOIN `org_positions` p ON p.`position_id` = a.`position_id`
      WHERE a.`position_id` IS NOT NULL) AS matched_position_references,
    (SELECT COUNT(*) FROM `org_positions`) AS position_rows;

-- Review target-table row counts. Expected values are zero except org_positions (10).
SELECT 'apr_approvers' AS table_name, COUNT(*) AS row_count FROM `apr_approvers`
UNION ALL SELECT 'apr_decisions', COUNT(*) FROM `apr_decisions`
UNION ALL SELECT 'apr_module_approval_rules', COUNT(*) FROM `apr_module_approval_rules`
UNION ALL SELECT 'apr_requests', COUNT(*) FROM `apr_requests`
UNION ALL SELECT 'mod_local_policy_categories', COUNT(*) FROM `mod_local_policy_categories`
UNION ALL SELECT 'ntf_notifications', COUNT(*) FROM `ntf_notifications`
UNION ALL SELECT 'org_company_positions', COUNT(*) FROM `org_company_positions`
UNION ALL SELECT 'org_departments', COUNT(*) FROM `org_departments`
UNION ALL SELECT 'org_position_hierarchy', COUNT(*) FROM `org_position_hierarchy`
UNION ALL SELECT 'org_positions', COUNT(*) FROM `org_positions`
UNION ALL SELECT 'org_reporting_lines', COUNT(*) FROM `org_reporting_lines`
UNION ALL SELECT 'rpt_definitions', COUNT(*) FROM `rpt_definitions`
UNION ALL SELECT 'rpt_recipients', COUNT(*) FROM `rpt_recipients`
UNION ALL SELECT 'rpt_runs', COUNT(*) FROM `rpt_runs`
UNION ALL SELECT 'rpt_schedules', COUNT(*) FROM `rpt_schedules`
UNION ALL SELECT 'tsk_tasks', COUNT(*) FROM `tsk_tasks`
ORDER BY table_name;

-- Expected preflight values:
-- current_database = pos_workspace_v3
-- account_rows = 4; role_rows = 3; module_rows = 2; audit_rows = 1
-- company_rows = 1; branch_rows = 1; system_setting_rows = 7
-- account_position_references = 3; matched_position_references = 3; position_rows = 10
-- All target tables above = 0 except org_positions = 10.
-- Stop and request a revised plan if any expected value differs.

-- DESTRUCTIVE SECTION — intentionally disabled pending separate execution approval.
--
-- Clear the three account position references in its own transaction.
-- DDL below is not transactional in MariaDB 10.1.
--
-- START TRANSACTION;
-- UPDATE `usr_accounts`
--    SET `position_id` = NULL
--  WHERE `position_id` IS NOT NULL;
-- SELECT ROW_COUNT() AS account_position_references_cleared;
-- COMMIT;
--
-- Drop children before their referenced parents. No foreign-key-check bypass is used.
--
-- DROP TABLE `apr_approvers`;
-- DROP TABLE `apr_decisions`;
-- DROP TABLE `apr_module_approval_rules`;
-- DROP TABLE `apr_requests`;
-- DROP TABLE `mod_local_policy_categories`;
-- DROP TABLE `ntf_notifications`;
-- DROP TABLE `org_position_hierarchy`;
-- DROP TABLE `org_company_positions`;
-- DROP TABLE `org_positions`;
-- DROP TABLE `org_departments`;
-- DROP TABLE `org_reporting_lines`;
-- DROP TABLE `rpt_recipients`;
-- DROP TABLE `rpt_runs`;
-- DROP TABLE `rpt_schedules`;
-- DROP TABLE `rpt_definitions`;
-- DROP TABLE `tsk_tasks`;
--
-- Post-change checks, to run only after separately approved execution:
-- SELECT COUNT(*) AS accounts FROM `usr_accounts`;
-- SELECT COUNT(*) AS roles FROM `usr_roles`;
-- SELECT COUNT(*) AS nonnull_position_references
--   FROM `usr_accounts` WHERE `position_id` IS NOT NULL;
-- SELECT COUNT(*) AS modules FROM `mod_modules`;
-- SELECT COUNT(*) AS audit_rows FROM `audit_logs`;
-- SELECT COUNT(*) AS companies FROM `org_companies`;
-- SELECT COUNT(*) AS branches FROM `org_branches`;
