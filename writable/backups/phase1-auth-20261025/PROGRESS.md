# Project Progress

Last reviewed: 2026-10-23

## Approved Outlet List access workflow — implementation pass (2026-10-23)

### Completed

- Created and verified the protected backup `writable/database-backups/pos_workspace_v3_pre_outlet_access_20261023_145930.sql` (6,068 bytes). The dump contains schema and data statements for the original `migrations`, `usr_accounts`, and `usr_roles` tables. Verification reported table/row statement counts only; no row values were printed.
- Applied five approved migrations in dependency order on MariaDB 10.1.19. `php spark migrate:status` confirms all five are applied in batch 1. A count-only `information_schema` check confirms 20 tables: 2 account rows, 1 role row, 5 migration rows, 2 DRAFT modules, 8 disabled-by-default module action rows; all other new tables are empty. No test or operational company, branch, outlet, account, role, grant, scope, or approver records were inserted into the development database.
- Preserved `usr_accounts`, `usr_roles`, and the CI `migrations` table. The pending Module Registry audit table and all application references now use `log_audit_logs`. Every migration `down()` method refuses automated rollback.
- Implemented database-backed Outlet List, Outlet List Module Management, Employee Management / Account Management, and the shared Approval Queue. Static CodeIgniter routes are protected by auth, Superadmin, and CSRF filters as applicable.
- Added fail-closed action checks requiring an active account and role, active module/action, role grant, and individual-user grant. Viewer mutations are denied. Company and branch access is checked as the intersection of module allowlists and individual scopes, with the company/branch relationship and active organization statuses checked server-side.
- Added per-action enabled/approval settings, `NONE`/`ADMIN`/`HIERARCHY` modes, ordered approvers, pending requests, sequential decisions, rejection reasons, requester self-approval prevention, scope revalidation, transactional application/audit, and exact missing-approver failure text. Employee access changes use the same approval gate when configured; ordinary users cannot change their own access or assign permissions/scopes outside their own grants and scope. Superadmin role/status and Reports To assignment are restricted to the existing Superadmin route; reporting cycles are rejected.
- Outlet deletion is soft archive; authorized restore preserves the record. Outlet listings/search/status filtering honor active user scope. Employee Management supports search, account status/role assignment for Superadmin, module action grants, company/branch scopes, primary Reports To, and an effective Outlet List action preview.
- Updated the responsive workspace navigation to show implemented destinations based on explicit access; Login source was not changed.

### Files changed

- Migrations: `app/Database/Migrations/2026-10-23-000001_CreateModuleRegistry.php` through `000005_CreateOutletList.php`.
- Application: `app/Services/OutletAccessService.php`, `app/Services/ModuleRegistryService.php`, `app/Config/Services.php`, `app/Config/Routes.php`, `app/Controllers/OutletList.php`, `ModuleManagement.php`, `EmployeeManagement.php`, `OutletApprovals.php`, `app/Filters/SuperadminFilter.php`, `app/Views/layouts/workspace.php`, and new Outlet/Management views.
- Tests and docs: `tests/unit/OutletAccessServiceTest.php`, `tests/unit/ModuleRegistryServiceTest.php`, `DATABASE.md`, and this `PROGRESS.md`. `PROJECT_BLUEPRINT.md` was not changed because the confirmed requirements did not change.

### Verification actually run

- PHP syntax: passed for 93 PHP files under `app` and `tests`.
- `vendor/bin/phpunit --no-coverage`: **25 tests, 75 assertions passed**. The new isolated SQLite fixtures cover role+user grant intersection, Viewer read-only, inactive module denial, company/branch scope, sequential approval, rejection, no/self approver fail-closed behavior, archive/restore, and approval-gated employee access.
- `php spark migrate:status`: all five application migrations applied. `php spark routes`: new handlers appear as explicit static routes with expected filters.
- Local HTTP smoke check: `/login` returned 200; unauthenticated `/dashboard`, `/module-management`, and `/outlets` redirected to Login. Existing credential and Google OAuth sign-in were not exercised.
- `composer test` was also attempted. All tests passed, but that command exits nonzero because PHPUnit reports that no code-coverage driver is installed; `--no-coverage` runs cleanly.
- Browser automation was not available/configured, so viewport, keyboard/touch, authenticated Superadmin/Supervisor/Regular User browser flows, and visual checks were not run. The MariaDB application data remains empty for companies, branches, outlets, grants, scopes, approvals, and approvers; therefore the live role-boundary scenarios still need a controlled manual test with isolated fixtures.

### Known limitations and next step

- The first implementation pass is not finalized. Position/department assignment and activation history are unsupported by the approved live schema; Account Management does not yet have pagination or a history screen. The access preview shows effective Outlet List actions, not a full record-by-record scope explanation.
- Isolated service tests do not replace authenticated request tests for direct URL/parameter tampering, nor end-to-end browser tests of each role. Run those checks with isolated organization/account fixtures before calling the workflow complete. Do not seed fixtures into the development database.
- Exact next step: review the Module Management and Employee Management screens in a browser, then run authenticated role-boundary and request-tampering tests in an isolated database; fix defects and record results before finalizing Outlet List.

## Outlet List, Module Management, and Employee Management implementation preflight (historical; superseded)

### Completed read-only checks

- Re-read `AGENTS.md`, `PROJECT_BLUEPRINT.md`, `DATABASE.md`, and this progress log before planning implementation.
- Reconfirmed the live database through the CI4 connection: `pos_workspace_v3` on MariaDB `10.1.19-MariaDB`; base tables are `migrations` (0 rows), `usr_accounts` (2 rows), and `usr_roles` (1 row). Rechecked the complete current `SHOW CREATE TABLE` definitions for `usr_accounts` and `usr_roles`. No identity, role, or credential values were selected.
- `php spark migrate:status` still reports `2026-10-23-000001_CreateModuleRegistry` as pending. Its current source creates `mod_modules` and `audit_logs`; the latter conflicts with the confirmed `log_` prefix. Neither table exists live.
- Inspected current static routes, Module Registry controller/service, authentication and Superadmin filters, account model, service registration, current test files, and available migrations. Login is separate from the proposed business-module handlers and must remain unchanged.
- Confirmed there are currently no live company, branch, module, permission, scope, Outlet List, approval, or audit tables. The live account/role tables and their existing rows must be preserved.

### Implementation status and blockers

- No application, route, migration, schema, seed, account, role, or other database change was made. No backup was created because the exact migration set has not yet been reviewed and approved.
- No automated or browser tests were run in this preflight.
- The subsequent owner-provided implementation brief resolves the role/user rule: module action availability, role grant, and individual user grant must all allow access. It also confirms archive/restore rather than hard deletion, isolated organization fixtures for automated tests, no arbitrary live organization/outlet seeds, and the `NONE`, `ADMIN`, or `HIERARCHY` approval modes with ordered stages and fail-closed missing-approver handling.
- Live company/branch tables contain no data. The brief permits isolated test fixtures; the production UI must show an empty state until organization data is supplied through an authorized process, rather than inserting sample live records.
- The current pending registry migration uses `audit_logs`; applying it unchanged would contradict the approved `log_` naming registry and all subsequent application references must be coordinated if it is corrected.

### Approval checkpoint

- The implementation plan is constrained to the three requested areas and their dependencies. It includes correcting the pending audit-table name, additive organization/access/approval/outlet migrations, static routes, shared authorization/audit/approval services, and isolated tests. The exact migration and security-sensitive application changes must be reviewed and explicitly approved before modification, as required by `AGENTS.md`.

### Exact next task

- Obtain explicit approval of the exact migration and security-sensitive implementation plan. Then create and verify a timestamped development-database backup, apply only the approved MariaDB 10.1-compatible migrations, validate schema and preservation of the existing two accounts and one role, and implement/test the three requested areas.

## Initial schema reconciliation for Module Settings and Outlet List (2026-10-23)

### Completed read-only inspection

- Verified the configured database is `pos_workspace_v3` on MariaDB `10.1.19-MariaDB`.
- Live base tables and count-only row totals: `migrations` (0), `usr_accounts` (2), `usr_roles` (1). No identity, role, account, or credential values were selected or displayed.
- Verified all three are InnoDB. `usr_accounts` and `usr_roles` use `utf8mb4_unicode_ci`; framework-managed `migrations` uses `utf8mb4_general_ci`.
- `usr_accounts`: unsigned `BIGINT(20)` primary key; unique email, username, employee-code indexes; role and status indexes; FK `role_id -> usr_roles.role_id` with RESTRICT update/delete. `position_id` and `default_module_id` currently have no foreign keys.
- `usr_roles`: unsigned `BIGINT(20)` primary key and unique role-code index; one live row. No role values were read.
- `migrations`: unsigned `BIGINT(20)` primary key and no rows. `php spark migrate:status` reports `2026-10-23-000001_CreateModuleRegistry` pending.
- No live module registry, RBAC, organization/company/branch/outlet, hierarchy, approval, or audit tables were found. Existing hierarchy tables and records therefore were not available to reuse in this inventory.
- Source inspection found Module Registry routes/service/filter/views, with static route definitions. The pending migration creates `mod_modules` and `audit_logs`; its service, filter, and tests refer to `audit_logs`. Login uses the existing `usr_roles`; no RBAC module/action authorization service is present.

### Reconciliation conflicts and blockers

- The pending migration's `mod_modules` fits the `mod_` prefix, but its `audit_logs` name conflicts with the confirmed `log_` registry. Renaming it before application would require coordinated migration, service, filter, and test updates.
- Existing `usr_roles` is live and referenced by `usr_accounts`; preserve its name. `migrations` is a CodeIgniter-managed table without a domain prefix and should not be renamed without verifying framework configuration/dependencies. Both require explicit treatment as legacy/framework exceptions to the new naming rule.
- The prior RBAC proposal's `acl_` names must be reconciled to `rbac_`. The prior query attachment's duplicate registries, incompatible module-type/lifecycle enums, cascades, and duplicate hierarchy/approval/audit tables remain rejected for implementation.
- No `org_` company/branch/department or `out_` outlet table exists in the live inventory. `usr_accounts.position_id` exists but has no live referenced position table/FK. Outlet List cannot yet safely rely on company/branch associations; those dependencies need a reviewed design.
- RBAC grants, approval policies/workflows, account activation history, company/branch scopes, reporting hierarchy, notification queue, and audit table are absent live. Module Settings and Account Management cannot truthfully present saved settings/access workflows until their schema and backend authorization are approved and implemented.
- The requested backup was not created because no database write was authorized/performed in this inspection phase. No SQL writes, migrations, seeds, application changes, or tests were run.

### Files updated and exact next task

- Documentation updated: `PROJECT_BLUEPRINT.md` now records the requested first scope (Module Settings, Account Management, and Outlet List); `DATABASE.md` records the latest verified base-table inventory and names/ownership conflicts; this `PROGRESS.md` records the reconciliation.
- **Exact next task:** Owner review of this reconciliation, especially (1) retaining `usr_roles` and framework `migrations` as exceptions, (2) changing pending audit storage to a `log_` name and updating its code/test references, and (3) the company/branch prerequisites for Outlet List. After that review, prepare the corrected migration set and backup/rollback plan for separate approval. Only then create the verified timestamped backup and apply approved additive migrations.

## Database naming registry added (2026-10-23)

### Completed

- Added the owner-confirmed lowercase `snake_case` and functional-prefix rules and initial prefix registry to `PROJECT_BLUEPRINT.md`.
- Added a current/live and pending-table ownership map to `DATABASE.md`. Recorded `usr_roles` as an existing name to preserve and `audit_logs` in the pending migration as a prefix conflict requiring reconciliation.
- No tables were created or renamed. No migration or database write was run.

### Files and checks

- Files changed: `PROJECT_BLUEPRINT.md`, `DATABASE.md`, and this `PROGRESS.md` entry.
- Reviewed the current database inventory already recorded in `DATABASE.md`, the pending `CreateModuleRegistry` migration, `AGENTS.md`, and prior progress. No automated tests were run; the changes are documentation-only.

### Follow-up status

- The requested live inventory and initial dependency review were completed in the later “Initial schema reconciliation for Module Settings and Outlet List” entry above. Naming conflicts remain unresolved pending owner review; no migration was applied.

## POS Team Masterfile requirements merged into blueprint (2026-10-23)

### Completed

- Merged the owner-provided POS Team Masterfile objective and development guidance into `PROJECT_BLUEPRINT.md` while retaining existing confirmed Superadmin, authentication, route, module, scope, approval, and audit requirements.
- Updated source-backed implementation facts to reflect the Module Registry routes/source and its still-pending migration, and clarified that RBAC tables are not live.
- Added incremental module finalization, mobile-first responsiveness, Light/Dark theme foundations, local RBAC delegation boundaries, approval notifications, work tracking, transparent activity/audit, and security/testing requirements.

### Files and checks

- Files changed: `PROJECT_BLUEPRINT.md` and this `PROGRESS.md` entry.
- No application code, routes, migration, or database was changed. No tests were run; this was a documentation-only update.
- Reviewed the supplied instructions, `AGENTS.md`, prior progress, existing blueprint, current routes, and the pending migration status.

### Exact next task

- Review and approve or revise the four-table RBAC database proposal and confirm the prerequisite Module Registry migration before any database implementation. Once approved and applied, verify the schema and action seeds, then implement the authorization service and route/action enforcement before starting business modules.

## Minimal RBAC database proposal prepared (2026-10-23)

### Completed

- Prepared a proposal for `acl_actions`, `mod_module_actions`, `acl_role_module_actions`, and `acl_user_module_actions`, reusing the confirmed `usr_roles` / `usr_accounts` keys and the pending `mod_modules` migration rather than introducing duplicate registries.
- Proposed fail-closed permission evaluation: active account and role, active module, enabled module/action pair, an allowed role grant, and an allowed explicit user grant are all required. Proposed action seeds are limited to `VIEW`, `CREATE`, `UPDATE`, `DELETE`, and `APPROVE` from the supplied request.
- Kept module scope, approval workflow, position hierarchy, and audit storage out of this RBAC proposal; those require their own confirmed designs and must reuse existing approved structures.

### Status and verification

- Proposal only. No migration file was created or changed, and no SQL write, migration, seed, or application-code change was performed.
- Read `AGENTS.md`, `PROJECT_BLUEPRINT.md`, `DATABASE.md`, the current Module Registry migration, account/role metadata, and migration status. `CreateModuleRegistry` remains pending.
- No automated tests were run. No live RBAC behavior exists or is claimed.

### Exact next task

- Review and explicitly approve or revise the four-table RBAC proposal and the prerequisite application of the pending Module Registry migration. Only after approval, create the migration and apply it in the approved order; then verify table definitions, foreign keys, indexes, and seeded action codes before starting application authorization work.

## Review of supplied RBAC SQL (2026-10-23)

### Completed

- Read the supplied SQL attachment and compared it with the project instructions, confirmed blueprint requirements, current database documentation, and current account/role metadata.
- Confirmed via `php spark db:table ... --metadata` that `usr_accounts` and `usr_roles` are present with the documented key columns. `php spark migrate:status` still lists `CreateModuleRegistry` as pending.
- Did not execute any SQL from the attachment. No database rows or schema were changed.

### Findings and known issues

- The script creates parallel `rbac_module_registry` and `rbac_role_registry` tables despite the existing Module Registry migration and `usr_roles` table. It also creates separate hierarchy and approval schemas that conflict with existing project decisions and historical structures.
- It uses module types `SUPERADMIN`, `USER`, and `VIEWER` and omits the confirmed `DRAFT` module lifecycle. These conflict with the blueprint and current Module Registry migration.
- `ON DELETE CASCADE` across role, module, permission, scope, hierarchy, and approval rows risks deleting dependent authorization/history data, contrary to preservation rules.
- It declares `JSON` columns although the project database is MariaDB 10.1.19; MariaDB introduced its `JSON` alias in 10.2.7. Use a compatible text representation only if separately approved.
- The existing `mod_modules` dependency is not live because its migration is pending. Several user/account/organization references in the submitted script also lack foreign keys, and the hierarchy tables duplicate the approved position architecture.
- The script seeds duplicate role records (`SUPERADMIN`, `USER`, `VIEWER`) rather than using existing roles. Role/action seeds and approval/scope behavior require a reconciled design.

### Verification performed

- Read-only project/schema documentation and metadata inspection only. No automated tests, migration, SQL write, or browser test was run for this review.

### Exact next task

- Replace the submitted script with a minimal, MariaDB 10.1.19-compatible migration proposal that reuses `usr_roles` and the existing Module Registry migration, avoids duplicate position/approval/audit structures, defines the approved role-versus-user grant semantics, and includes validation and rollback limits. Obtain explicit approval of that exact proposal before applying any database changes.

## Module Registry — Phase 1 implementation (2026-10-23)

### Completed in source

- Added a Superadmin-only Module Registry with search, type/status filters, pagination, create/edit, parent selection, metadata validation, and status changes. Newly created records are always `DRAFT`; no hard-delete action is provided.
- Kept CodeIgniter routes developer-maintained and static. `route_path` is descriptive metadata only. Activation is rejected unless the module code is present in the service's developer-maintained static route allowlist; currently that allowlist supports `MODULE_REGISTRY` only.
- Added server-side duplicate-code, parent-existence, self-parenting, and cycle validation. Hierarchy writes lock/read the existing module hierarchy in a transaction on MySQL before validating the parent chain.
- Added a migration defining `mod_modules` and `audit_logs`. Successful create/update/status changes and rejected writes use the audit table; successful record changes and their audit insert share one transaction. The access filter records authenticated non-Superadmin attempts when the audit table exists.
- Preserved the Login and Dashboard routes and the existing login readiness checks. This phase does not implement RBAC grants/action catalogs, module scopes, approvals, or routes for business modules.

### Current live database facts

- Read-only CI4 connection confirmed MariaDB `10.1.19-MariaDB`, database `pos_workspace_v3`, and the current live table listing contains only `usr_accounts` and `usr_roles`.
- Count-only queries returned 2 `usr_accounts` rows and 1 `usr_roles` row. No identity or account values were selected or displayed.
- The new Module Registry migration is pending; it was not executed. The live database still has no `mod_modules` or `audit_logs` table. No database writes were performed.

### Files changed

- `app/Config/Filters.php`
- `app/Config/Routes.php`
- `app/Config/Services.php`
- `app/Controllers/ModuleRegistry.php`
- `app/Database/Migrations/2026-10-23-000001_CreateModuleRegistry.php`
- `app/Filters/SuperadminFilter.php`
- `app/Services/ModuleRegistryService.php`
- `app/Views/layouts/workspace.php`
- `app/Views/module_registry/index.php`
- `app/Views/module_registry/form.php`
- `tests/unit/ModuleRegistryServiceTest.php`
- `DATABASE.md`
- This `PROGRESS.md` entry.
- Clarified in `DATABASE.md` that earlier larger live inventories are historical and superseded by the current 2026-10-23 read-only inventory.

### Verification performed

- PHP lint passed for all 11 changed PHP application, migration, view, and test files.
- Full PHPUnit suite passed: 17 tests, 51 assertions (in-memory SQLite service tests plus the existing suite).
- `php spark routes` completed and listed the Module Registry GET/POST routes. `php spark filter:check` confirmed authentication and Superadmin filtering on GET, with CSRF on POST.
- `php spark migrate:status` lists `2026-10-23-000001_CreateModuleRegistry` as pending. No migration execution, seed, or database write was run.
- Reconciled the database documentation's older inventory figures with the latest verified live state; retained older figures as historical context.
- No browser/device visual check or live Module Registry submission was performed.

### Known issues and exact next task

- The registry is unavailable until its pending migration is approved and applied. Its rollback drops both newly created tables and would destroy records if run after use; no automatic rollback was run or prepared against live data.
- Only `MODULE_REGISTRY` currently has an implemented static route. Other module codes remain unable to activate until developers add routes/handlers and extend the allowlist. Registry code does not create routes.
- RBAC action definitions, role/module/action grants, explicit user grants, data-scope enforcement, approvals, and audit retention/export controls are not implemented in this phase.
- **Exact next task:** Review the pending `mod_modules` and `audit_logs` DDL in `app/Database/Migrations/2026-10-23-000001_CreateModuleRegistry.php`; separately approve applying it to `pos_workspace_v3`. After approval, run the migration and verify both table definitions, keys, foreign keys, and zero initial rows. Then plan the RBAC action catalog and grant model as a separate phase before adding non-Superadmin module access.

## Confirm reset to Login and Landing Page baseline (2026-10-23)

### Current state confirmed from source

- The owner reports that the database has also been cleared. This is an owner-reported state only; no live database connection or read-only inventory was performed in this documentation update, so the remaining database schema and data are unverified.
- Source inspection confirms the application currently has routes for the root redirect, Login, Google authorization/callback, credential login, protected Dashboard, and logout only.
- The current source has the Login and temporary Dashboard landing page; Company Management and Module Settings routes/controllers are not part of this baseline. Sidebar module links and policy links are therefore not currently present in the main navigation implementation.
- The older progress entries below describe historical work and earlier database states. They should not be treated as the current application/database state without rechecking.

### Files changed

- This `PROGRESS.md` entry only. No application files or database objects were changed.

### Verification performed

- Read `AGENTS.md`, `PROJECT_BLUEPRINT.md`, `DATABASE.md`, and the current top-level route and controller/view references.
- Confirmed the current route list by source inspection. No PHP lint, automated tests, live database queries, or browser checks were run for this documentation update.

### Known issues and exact next task

- `DATABASE.md` contains historical verified inventories that conflict with the owner's report that the database was cleared. The actual live schema/data state has not been verified, and the report does not specify whether “cleared” means rows, schema, or both.
- **Exact next task:** Before database-dependent implementation, perform an approved read-only inventory of the intended `pos_workspace_v3` database to establish whether tables, rows, and migrations remain; reconcile `DATABASE.md` with verified results. Then continue only with the Login and Dashboard baseline unless the owner explicitly expands scope.

## Sidebar navigation adjustments (2026-10-23)

### Completed

- Reordered the sidebar to show Overview first, Company next, and Module Settings under the `SETUP` heading.
- Hid other module links from the sidebar temporarily, and removed the Global Policies and Local Policies links from sidebar navigation at the owner's request.
- These are navigation-only changes. Module records, policy pages/routes, backend access checks, permissions, and database data were not removed or changed.

### Files changed

- `app/Views/layouts/workspace.php`
- This `PROGRESS.md` entry.

### Verification performed

- `php -l app/Views/layouts/workspace.php` passed after the policy navigation links were removed.
- The preceding sidebar reorder was followed by a PHPUnit run: 30 tests, 95 assertions passed. PHPUnit was not rerun after removal of the two sidebar links.
- No live database inspection or browser visual check was performed for these latest navigation changes.

### Known issues and exact next task

- Sidebar rendering has not been visually checked as an authorized Superadmin. The current implementation also leaves the Global Policies and Local Policies routes/pages in place; only their sidebar links are hidden.
- **Exact next task:** Sign in as an authorized Superadmin and verify that the sidebar shows Overview, Company, then Setup with Module Settings and no Global/Local Policies links. Clarify the user's unfinished message, “on module,” before making any additional module-specific change.

## Creating an active Superadmin login (2026-10-23)

### Applied with explicit owner approval

- Created the `SUPERADMIN` role because none existed, then created one `ACTIVE` account linked to that role, using the username and email supplied by the owner.
- Rejected the requested weak password `bounty`; generated a cryptographically random initial password and stored only its `password_hash` in the database. The initial password was returned directly to the owner and is intentionally not recorded here or in `DATABASE.md`.
- The account does not have a forced password-reset flag; treat the generated credential as sensitive and arrange a secure password change/reset before routine use.
- The post-write inventory showed two account rows and one role row. The account created in this task was confirmed by a count-only check as one active Superadmin assignment. A second account row was present without explanation; its identity was not inspected and it was not changed.

### Verification performed

- A post-commit, read-only database check confirmed: two account rows, two active accounts, one active `SUPERADMIN` role, and one active account assigned to that role.
- The one-time setup script was removed after execution.
- No other table, account, or role was modified by this account-creation operation.

### Exact next task

- Resolve the unexplained account-row count discrepancy without inspecting or changing the other account unless separately authorized. Provide a secure password-change/reset path before routine use of the newly created Superadmin credential. Do not record the password in project files or logs.

## Restoring the login database schema (2026-10-23)

### Applied with explicit owner approval

- A read-only inspection found that the current `pos_workspace_v3` database contained only `usr_roles` (0 rows), and `usr_accounts` was absent. This differed from the earlier 2026-10-23 inventory documenting 32 tables and existing account/role rows; the cause is unknown.
- Changed the empty `usr_roles.role_id` from signed to unsigned `BIGINT(20)` to match the documented account relationship.
- Created the empty `usr_accounts` table with its documented columns, indexes, unique keys, and foreign key to `usr_roles(role_id)`.
- No account or role rows were inserted during the schema repair itself. An active Superadmin role/account were inserted later under the separate owner approval documented above.

### Verification performed

- `SHOW CREATE TABLE usr_roles` and `SHOW CREATE TABLE usr_accounts` confirmed the resulting definitions, including the foreign key.
- A read-only `information_schema` query confirmed the two login tables and the `usr_accounts.role_id` foreign key to `usr_roles.role_id`; the later account-creation entry above records current row counts.
- `php spark db:table usr_accounts --metadata` and `php spark db:table usr_roles --metadata` displayed the expected columns and exited 0. `php spark db:table --show` printed both tables with 0 rows but exited 1, so its output was not treated as a successful command check.
- `php spark routes` completed successfully with the retained Login, Google authorization/callback, credentials, Dashboard, and logout routes.
- `php vendor\bin\phpunit -c phpunit.dist.xml --no-coverage --testdox` passed: 12 tests, 39 assertions.

### Exact next task

- Confirm why the current database differs from the earlier 32-table inventory and identify the authoritative source for missing non-login tables/records. Do not recreate them without separate owner approval.

## Login + Landing Page baseline cleanup (2026-10-23)

### Implemented in source

- Reduced application routes and navigation to the root redirect, Login, Google Workspace authorization/callback, credential login, protected Dashboard landing page, and authenticated CSRF-protected logout.
- The root route now sends unauthenticated visitors to Login and authenticated sessions to the protected Dashboard. The Dashboard remains the post-login landing page and retains its account summary.
- Preserved the existing Login visual design and authentication behavior, including OAuth state and verified-email checks, TLS verification, password verification/rate limiting, active account/role checks, session regeneration, and logout. The pending-account modal remains part of Login but no longer presents module/company/position setup steps.
- Removed the Dashboard birthday form and its route/controller; no longer reads the birthday field for the workspace session. The existing database column was not changed.
- Removed module-driven workspace navigation, notifications/theme/profile placeholder controls, and module-only CSS. The responsive workspace shell, shared theme, Login background/logo, account menu, and global navigation loading indicator remain.
- Removed the Company Management and Module Settings controllers, filters, model, services, views, and related focused tests. Removed the unused standalone pending-account and starter welcome views, plus the unapplied module-action migration and its source-only test suite.
- `AGENTS.md`, `PROGRESS.md`, `PROJECT_BLUEPRINT.md`, `DATABASE.md`, environment files, and the review-only [login-foundation-reset.sql](./login-foundation-reset.sql) are retained. The SQL plan remains unexecuted.

### Database status and verification

- No database table, row, schema, or migration was changed as part of the application cleanup. The live MariaDB inventory at that time was inspected read-only on 2026-10-23; the account, role, ACL, registry, company/branch, settings, and audit tables and rows were then present as inspected.
- The application cleanup only stopped using the module/business tables; it did not remove those database structures or the three account position references identified in the earlier audit. Their absence from the later live inventory is unexplained and is recorded in the newer database-schema progress entry above.
- `php spark routes` completed successfully and listed only root, Login, Google authorization/callback, credential login, protected Dashboard, and logout routes.
- PHP syntax checks passed for the retained/modified PHP files. `php spark filter:check GET dashboard`, `php spark filter:check POST logout`, and `php spark filter:check POST login/credentials` passed; Dashboard remains authentication-filtered, while logout and credential login retain CSRF protection.
- `php vendor\bin\phpunit -c phpunit.dist.xml --no-coverage --testdox` passed: 12 tests, 39 assertions.
- `php spark migrate:status` reported that no migrations were found. No migration, seed, SQL write, or other database operation was run.
- An unauthenticated HTTP check returned 302 redirects to Login for both `/` and `/dashboard`. In the authenticated browser session, Login rendered and the Dashboard landing page showed Overview and Account Summary without business-module navigation.
- The Login theme stylesheet, logo, and background image returned HTTP 200 in the browser. Bootstrap CSS and JavaScript requests to jsDelivr reported browser `responseStatus: 0`; this indicates the external resources were unavailable to that browser check, not a confirmed HTTP 404. The earlier two-404 observation was not reproduced as a local application asset failure.
- Source searches found no references in the application to the removed module/business controllers, services, routes, birthday feature, or removed pending/starter views.
- Google OAuth, credential submission, and logout were not exercised end-to-end. OAuth requires the configured external service; credential and logout behavior should be tested in an approved isolated environment. No live account data was changed.

### Exact next task

- Validate Google OAuth, credential login, and logout in an approved isolated test environment, and investigate Bootstrap CDN availability if the Login modals need it. Do not execute the prepared SQL plan; database cleanup requires separate approval of the final removal plan and execution.

## Permissions / Module Access action controls (2026-10-23)

### Implemented in source; live migration not applied

- Added a developer-maintained action catalog for Read (`VIEW`), Create, Update, Delete, Import, Export, View Report, and Schedule Report. Only actions backed by known static routes are configurable; unsupported actions are visibly unavailable.
- Replaced the informational Permissions / Module Access panel with responsive action and Required Approval switches. Superadmin changes use a dedicated authenticated, Superadmin-only, CSRF-protected POST route, are validated server-side, and are recorded in the audit log within the same database transaction.
- Added the `mod_module_actions` application migration with unique module/action rows and defaults for the statically supported Module Settings and Company & Branch actions. The migration has **not** been run. Until it is applied, the controls are read-only and Company Management continues using its prior per-user permission checks.
- Once the mapping exists, non-Superadmin Company Management requests must pass both module-wide action availability and the existing individual module grant. Unsupported actions are rejected. Superadmin's existing GOD MODE bypass is preserved.
- Required Approval settings cannot weaken mandatory global rules. Existing module approval rules are kept separate; toggling Required Approval updates an existing rule's enabled flag but does not create approval rules or assign approvers. Actions requiring approval remain blocked without an active workflow and an active approver.
- Company create/update operations remain blocked because the supported company approval-request workflow is still incomplete. The UI states this instead of indicating that an enabled action can complete.

### Verification performed

- Complete PHPUnit suite passed: 47 tests, 153 assertions.
- PHP lint passed for all changed PHP files.
- `php spark routes` completed and confirmed the new POST endpoint has authentication, Superadmin, and CSRF filters.
- Bun parsed both inline scripts in the Module Settings configuration view.
- No migration, SQL write, live database read, browser interaction, permission grant, or approver assignment was performed for this task.

### Exact next task

- After separate approval, apply the module action migration in an approved test environment and verify regular-user allowed/denied actions and Superadmin settings. Do not run it against live data without separate approval. Then implement and test the company approval-request workflow before enabling company mutations.

## Drag-and-drop module icon upload (2026-10-23)

### Implemented with owner approval

- Added click-to-select and drag-and-drop image selection to the Module Icon field, with a live client-side preview and upload feedback.
- Added server validation for PNG/JPEG/WebP MIME and image headers, a 2 MB file limit, and a 20-megapixel dimension limit. Accepted images are center-cropped and re-encoded to 128×128 WebP with a server-generated filename; client filenames and image contents are never used as stored paths or executable content.
- Stores re-encoded images under `public/assets/images/module-icons/`, with Apache directory listing disabled and script-like extensions denied. The existing `mod_modules.icon` field stores only the generated filename; no schema changes or routes were added.
- Uploaded icon images render in the Profile preview, module registry rows/cards, and workspace navigation. A failed profile save removes the newly written image; replaced images are retained so existing audit snapshots referencing their filenames remain valid. Eligible module deletion removes its icon file after the registry delete succeeds.
- Added `ModuleIconService` tests for WebP re-encoding/dimensions, MIME spoof rejection, and the 2 MB limit.

### Verification performed

- PHP lint passed for all changed PHP files.
- Focused icon tests passed: 3 tests, 11 assertions.
- Complete PHPUnit suite passed: 39 tests, 121 assertions.
- Bun parsed the profile upload JavaScript.
- In the authenticated browser, selecting the existing PNG logo displayed the client-side preview and showed the Save Changes prompt. The form reported `multipart/form-data`; no save was submitted and no live module/database data or uploaded asset was changed.
- `php spark routes` completed; the existing profile POST route remains protected by authentication, Superadmin, and CSRF filters. No route changes were made.

### Exact next task

- Exercise a complete upload/save/render cycle in an approved test environment, including actual drag-and-drop, and confirm the existing public asset deployment handles WebP correctly. Do not submit a profile change against live project data without separate approval.

## Module profile layout aligned with supplied reference (2026-10-23)

### Implemented

- Reworked the Module Settings Profile tab into a responsive card layout with module information, lifecycle controls, module preview, descriptive route/component metadata, saved policy summaries, approval-rule/approver counts, and save actions matching the supplied visual reference.
- Profile metadata fields continue to submit through the existing validated and audited profile-save flow. Lifecycle status remains a separate submission through the existing protected route.
- Policy flags and approval summaries are explicitly read-only; no new policy, permission, approver, scope, or approval write behavior was added. The icon remains a text identifier, not an upload control, and route metadata is not used to generate APIs or executable routes.
- The preview link is rendered only for the two statically supported module codes. Unimplemented codes are shown without a fabricated functional link.
- Tuned the narrow-screen workspace header context width after browser inspection detected horizontal overflow at a 390px viewport.
- No database schema, live data, routes, or approval configuration changed.

### Verification performed

- PHP lint passed for `app/Views/modules/configuration.php` and `app/Views/layouts/workspace.php`.
- `vendor\bin\phpunit -c phpunit.dist.xml --no-coverage`: passed, 36 tests and 110 assertions.
- Reviewed the authenticated Module Settings Profile page in the browser at 1366×900 and 390×844. At the narrow viewport, the browser reported no document horizontal overflow after the workspace header adjustment. The profile title and two-column/stacked card layouts rendered as intended.
- Browser DOM inspection confirmed the lifecycle select and button belong to the separate lifecycle form and the metadata controls remain in their own profile form. No profile/status form was submitted.
- No live database writes or schema checks were performed.

### Exact next task

- Review icon selection/upload expectations and whether policy and approver write controls should be implemented only after their authorized workflows and approval requirements are approved.

## Module action menu and shared loading indicator (2026-10-23)

### Implemented

- Removed the standalone Configure action from the Module Settings desktop table and mobile cards. Configure, a quick lifecycle status form, and Delete are now grouped in each module's three-dot menu.
- Quick status changes reuse the existing authenticated, Superadmin-only, CSRF-protected lifecycle route and its audited service rules. The registry return target is a fixed server-selected URL; activation still requires a statically supported implementation, and Module Settings cannot be deactivated.
- Added an accessible loading overlay to the authenticated workspace shell. It appears for same-origin navigation links and server-submitted forms, including actions from the existing Company Management and Module Settings views. Cancelled submissions, modified/new-tab/external links, downloads, and same-page anchors are excluded.
- Exposed `window.PWLoading.show(message)` and `window.PWLoading.hide()` for asynchronous actions. No asynchronous application actions were found in the inspected workspace views; any future AJAX-style action must use this API and hide it on success and error.
- Updated `PROJECT_BLUEPRINT.md` with the confirmed three-dot action and shared loading behavior requirements. No schema or live database changes were made; `DATABASE.md` was not changed.

### Verification performed

- PHP lint passed for `app/Controllers/ModuleSettings.php`, `app/Views/modules/index.php`, and `app/Views/layouts/workspace.php`.
- `vendor\bin\phpunit -c phpunit.dist.xml --no-coverage --testdox`: passed, 36 tests and 110 assertions.
- `php spark routes`: completed successfully; lifecycle remains a POST route with authentication, Superadmin, and CSRF filters.
- `php spark filter:check POST module-settings/20/lifecycle` and `php spark filter:check POST module-settings/20/delete`: both confirmed authentication, Superadmin, and CSRF filters.
- Bun parsed the shared workspace inline JavaScript successfully.
- No authenticated browser interaction or visual review was performed, and no live lifecycle/delete action was submitted.

### Exact next task

- Sign in as an authorized Superadmin and verify the three-dot menu and quick status flow on desktop and mobile. Exercise DRAFT/ACTIVE/INACTIVE transitions only in an approved test environment, and confirm the shared loading overlay appears during navigation and form submissions without appearing when delete confirmation is cancelled. Wire `PWLoading` into any future asynchronous actions.

## Module Settings registry layout and guarded delete action (2026-10-23)

### Completed

- Updated the Module Settings registry to follow the supplied reference: four summary cards, search/category/status controls, a New Module button, compact module rows, approval and access-scope indicators, Configure actions, and a three-dot action menu. Mobile cards retain their own Configure and Delete controls.
- Added a static `POST module-settings/{id}/delete` endpoint protected by authentication, Superadmin authorization, and CSRF.
- Added transactional deletion for non-core modules only. `MODULE_SETTINGS` and `COMPANY_BRANCH` are protected. Before deletion, the service checks child modules, account default-module references, access assignments, company/branch scope, approval rules/requests, audit references, local policy categories, report definitions, tasks, and module-related notifications. Any dependency blocks deletion; no linked records are cascaded or removed.
- Successful deletion writes a `MODULE_DELETED` audit event in the same transaction. Audit failure or commit failure rolls back the deletion. The confirmation prompt warns that deletion is permanent.
- Summary totals are based on the full module registry even when the displayed rows are filtered. Access-scope text reflects configured module company/branch filter flags, not user grants.
- No live module was deleted, and no database schema change or migration was made for this implementation.

### Verification

- `vendor\bin\phpunit -c .\phpunit.dist.xml --no-coverage --testdox`: passed, 36 tests and 110 assertions. New tests cover successful unreferenced module deletion and audit, protected core modules, access/audit/account-default dependencies, and audit-failure rollback.
- `php -l` passed for the changed controller, service, route, view, and test files.
- `php spark filter:check POST module-settings/20/delete` confirmed `auth`, `superadmin`, and `csrf` filters.
- The Module Settings browser URL redirected an unauthenticated session to Sign In, so the protected screen, visual match, and menu interaction could not be reviewed. No live delete endpoint was submitted.

### Exact next task

- Sign in as an authorized Superadmin and compare the Module Settings screen with the supplied reference, including the three-dot menu and confirmation dialog on desktop and mobile. Verify a linked module is rejected with the preserved dependency warning, and an unreferenced non-core draft can be deleted in a non-production/test database.

## Remove unreferenced Branch Management registry entry (2026-10-23)

### Completed

- The subsequent broader cleanup is recorded in the next section. The initial `BRANCH_MANAGEMENT` deletion was completed separately as documented below.

### Verification

- At that point, live post-delete queries confirmed `BRANCH_MANAGEMENT` absent and `COMPANY_BRANCH` still ACTIVE, with 11 module registry rows remaining.
- No automated tests or application routes were run; no application code changed.

### Exact next task

- See “Remove all modules except Module Settings and Company & Branch Management” below for the later owner-approved cleanup and remaining verification.

## Remove all modules except Module Settings and Company & Branch Management (2026-10-23)

### Completed with explicit owner approval

- Read-only inspection found 9 module rows outside `MODULE_SETTINGS` and `COMPANY_BRANCH`, plus the linked access/scope records listed below. A complete `information_schema` dependency scan found direct module references and their dependent tables.
- Created a non-empty full-database `mysqldump` backup outside the web root before the cleanup.
- In a transaction, deleted the 9 other `mod_modules` rows and their module-specific records. This removed 13 module-access assignments, 6 module permission records, 9 user-company scope rows, 9 user-branch scope rows, and 9 module-level company allowlist and 9 module-level branch allowlist rows.
- Preserved all user account rows and cleared `default_module_id` for the 2 accounts whose defaults pointed to removed modules.
- No approval rules/requests/decisions/approvers, audit events, local policy categories, report definitions/schedules/runs/recipients, module-linked notifications, or module-linked tasks were present for those modules; the guarded cleanup deleted zero rows in those tables.
- Preserved the active `MODULE_SETTINGS` (ID 2) and `COMPANY_BRANCH` (ID 11) module rows and all records linked to those retained modules. The global mandatory-policy table had zero rows; it is global/action-based and was not altered.

### Verification

- The first cleanup attempt failed on a MariaDB temporary-table limitation and was rolled back. A read-only recheck confirmed no changes from that attempt.
- The corrected transaction reported 9 module rows deleted, 2 default-module values cleared, and the row counts above. Post-commit checks show exactly `MODULE_SETTINGS` and `COMPANY_BRANCH` remain, both ACTIVE; zero non-retained module rows remain; and no account default references a missing module.
- The full-database backup completed successfully and was non-empty. No application code, routes, schema, or migrations were changed.
- No automated tests, application route checks, or browser checks were run.

### Exact next task

- Sign in as an authorized Superadmin and verify the Module Settings registry now lists only Module Settings and Company & Branch Management. Confirm the Company & Branch workflows still behave as expected; their create/update/status submissions remain subject to the previously documented approval blocker.

## Dynamic Superadmin Module Registry requirement (2026-10-23)

### Confirmed requirement

- The owner confirmed that Superadmin must be able to inspect and manage the complete registered module catalog, independently of regular-user assignments, with search and category/status/availability filters.
- The intended registry includes add/edit/configure, lifecycle controls, and module navigation, permission, eligibility, company/branch scope, approval, hierarchy, report, and notification configuration.
- Registered module metadata must not execute arbitrary code or dynamically supplied routes. Functional availability depends on trusted implemented backend components and explicitly maintained CI4 routes. Missing functionality must be disclosed and must not be presented as active or successfully implemented.
- Deletion requires authorization, dependency checks, and explicit confirmation; deactivation is preferred when references or history exist. No module removal may silently delete business data, assignments, approval records, or audit history. Sensitive changes must be audited.

### Existing implementation versus remaining work

- Implemented: Superadmin-only Module Settings lists registered module rows across lifecycle states and supports search, module-type/category filtering, status filtering, profile metadata edits, DRAFT creation, and audited lifecycle changes among `DRAFT`, `ACTIVE`, and `INACTIVE`.
- Implemented safeguard: lifecycle activation is rejected unless the module code is in the service's static route/implementation allowlist. Route/controller metadata remains descriptive, and the sidebar exposes only active modules with known static route mappings and authorization.
- Partial: registry data and navigation are database-backed/shared in parts, but currently supported sidebar route mappings remain explicitly allowlisted in application code. Newly registered modules do not become functional or navigable merely by entering metadata.
- Not implemented: availability filtering and an explicit implementation-status presentation/filter are not present in the inspected registry UI. The intended meaning/source of “availability” remains unresolved.
- Partially implemented: a confirmed, audited Delete action is available in each module's three-dot menu. The two core modules are protected; deletion is blocked when child, account-default, access/scope, approval, audit, policy, report, task, or notification references exist. The action never deletes dependencies. Browser interaction remains unverified.
- Not implemented: eligibility configuration and write workflows for module permissions, user access, company/branch scope, approval rules, hierarchy, reports, and notifications. Those configuration panels remain informational/read-only.
- No application code or database schema was changed for this documentation-only requirement update. The `mod_modules` and related schema information below remains based on the prior dated read-only inspection; this task did not reconnect to or query MySQL.

### Exact next task

- Define “availability” and implementation-status presentation, then review the registry at representative browser sizes. Keep executable routes and components developer-maintained and allowlisted; do not run schema or protected database changes without separate explicit approval.

## Documentation recovery and current verification (2026-10-23)

### Implemented and verified from current source

- Module Settings provides a searchable/filterable module registry, module profile creation/editing, DRAFT creation, lifecycle controls, configuration sections, and separate Global/Local Policies pages. The module profile and lifecycle operations are backed by audited service writes; executable routes remain statically defined.
- Company Management provides company listing and create/edit/status request forms. Its access checks require an active account and role, the active `COMPANY_BRANCH` module, explicit action grants for non-Superadmins, and applicable company/branch scope.
- Shared Light Theme tokens are defined in `public/assets/css/theme.css` and referenced by the login and workspace/module views. Responsive CSS exists in the workspace and Module Settings views.
- These points describe current code; they do not establish that protected browser workflows or live database behavior were exercised.

### Implemented but not fully tested

- Module Settings registry/profile/lifecycle behavior has focused unit coverage, but its interactive screens, Superadmin browser access, and live database writes were not exercised in this recovery.
- Company Management form and access-control code is present, but actual user grants/scopes and end-to-end company approval completion are not verified. Current company submissions fail closed, so no successful company create/update/status workflow is implemented.
- Responsive layouts and shared Light Theme styling exist in source, but their rendered behavior and accessibility have not been reviewed in a browser.

### Incomplete, read-only, or blocked

- Module Settings permission/module-access, company/branch scope, approval workflow, hierarchy, report, notification, activity/audit, and policy pages display summaries only. They do not configure grants, scopes, approval rules/approvers, policy rows, reports, or notifications.
- Company create, update, and status operations require approval and fail closed before a company mutation. The current `apr_requests` schema has no explicit company target, and prior read-only inspection reported no configured approval rules, approvers, or mandatory policies. Do not apply the proposed schema changes or configure policy/approval rows without owner approval.
- Prior read-only inspection reported zero `acl_module_permissions` grants for `COMPANY_BRANCH`; non-Superadmin users therefore remain blocked until explicit grants are configured through an approved process. Superadmin access still requires an active account and role.
- Responsive styling is present, but phone, tablet, laptop, desktop, large/event-display, and ultrawide rendering, keyboard access, clipping, and interactions have not been browser-verified. The shared tokens establish the Light Theme; per-user theme selection and persistence remain future work.

### Checks actually run for this documentation recovery

- `vendor\bin\phpunit -c phpunit.dist.xml --no-coverage --testdox`: passed, 30 tests and 95 assertions (exit code 0). The configured test suite uses its isolated test connection; this does not verify live MariaDB behavior.
- `php spark routes`: passed. The output listed Company Management behind authentication/action filters, Module Settings and policy pages behind authentication/Superadmin filters, and CSRF on POST routes.
- No PHP lint was run because application code was not changed. No browser test, live database query, SQL write, migration, seeder, permission grant, or approver assignment was performed.
- Git status/diff was unavailable because the project directory has no `.git` repository.

### Exact next task

- Have the owner review the proposed company-target approval schema and resolve approval-stage quorum, eligible approver scope, rejection/resubmission behavior, and the global `CREATE`/`UPDATE` policy mapping. Only after explicit approval should an authorized database operator apply and verify the schema/configuration, followed by implementation and tests for atomic approval completion. Separately, perform the documented responsive browser review at 390×844, 768×1024, 1366×768, 1920×1080, and 2560×1080.

## Simplify sidebar module navigation (2026-10-23)

### Completed

- Kept Overview, moved the authorized Company module link directly below it, and placed Module Settings under a `SETUP` heading.
- Hid other registry module links from the sidebar for now. This is navigation presentation only; module records, routes, backend authorization, and access grants were not changed.
- Kept Global Policies and Local Policies as separate Superadmin setup links.

### Files changed

- `app/Views/layouts/workspace.php`
- This `PROGRESS.md` entry.

### Verification performed

- `php -l app/Views/layouts/workspace.php` passed.
- `vendor\bin\phpunit -c phpunit.dist.xml --no-coverage` passed: 30 tests, 95 assertions.
- Live database module counts and statuses were not queried or re-verified.

### Exact next task

- Sign in as an authorized Superadmin and visually verify that Company appears above Setup and that Module Settings, Global Policies, and Local Policies are reachable under the intended layout. Then confirm which additional modules should be restored to sidebar navigation and map each only to its developer-maintained route and existing authorization rule.

## Restore light workspace navigation colors (2026-10-23)

### Completed

- Restored the light sidebar and white top bar colors at the owner's request, including matching text, hover, active-navigation, icon, notification, and mobile-menu contrast.
- Kept the Module Settings layout and registry/detail-page refinements from the prior visual pass.
- No routes, authorization, application behavior, or database objects changed.

### Files changed

- `public/assets/css/theme.css`
- `app/Views/layouts/workspace.php`
- This `PROGRESS.md` entry.

### Verification performed

- `php -l app/Views/layouts/workspace.php` passed.
- Static CSS token check passed: all 57 referenced `--pw-*` tokens are defined.
- PHPUnit and browser visual checks were not run in this color-only change.

### Exact next task

- Review the Module Settings list and configuration pages in a browser at 390×844, 768×1024, 1366×768, 1920×1080, and 2560×1080. Record visual/accessibility issues and obtain owner feedback before further UI or functional changes.

## Module Settings visual alignment with supplied reference (2026-10-23)

### Completed

- Updated the authenticated workspace chrome with a navy sidebar and blue gradient top bar, light navigation text, and a blue active-navigation state, following the supplied Module Settings reference while retaining POS Workspace V3 branding.
- Refined the Module Settings registry with a breadcrumb, module identity icons, summary cards, a clearer registry heading, and responsive table/card presentation.
- Refined module configuration pages with a breadcrumb, icon-led module identity header, and horizontally scrollable section tabs with a clear active indicator and visible keyboard focus treatment.
- Kept existing routes, authorization, module records, and configuration behavior unchanged. Read-only panels remain read-only; no simulated permission, scope, or approval controls were added.
- No database, SQL, migration, seed, application service, or product-requirement changes were made. The existing responsive and Light Theme requirements in `PROJECT_BLUEPRINT.md` already cover this work, so no blueprint update was needed.

### Files changed

- `public/assets/css/theme.css`
- `app/Views/layouts/workspace.php`
- `app/Views/modules/index.php`
- `app/Views/modules/configuration.php`
- This `PROGRESS.md` entry.

### Verification performed

- `php -l` passed for `app/Views/layouts/workspace.php`, `app/Views/modules/index.php`, and `app/Views/modules/configuration.php`.
- `vendor\bin\phpunit -c phpunit.dist.xml --no-coverage` passed: 30 tests, 95 assertions.
- `php spark routes` completed successfully and listed the existing routes and filters. No routes were changed.
- Browser rendering, interactive viewport checks, and direct comparison with the supplied image were not performed in this environment.
- No database access or writes were performed.

### Known limitations and exact next task

- Visual behavior at phone, tablet, laptop, desktop, and ultrawide sizes remains unverified in a browser. In particular, review navigation drawer behavior, configuration tabs, long tables, focus visibility, and clipping/overflow.
- Current Module Settings access and non-profile configuration panels retain their existing implementation limits; this visual pass does not make read-only panels editable or change grants/policies.
- **Exact next task:** Run the application in a browser and review the Module Settings list and configuration pages at 390×844, 768×1024, 1366×768, 1920×1080, and 2560×1080. Record visual/accessibility issues and obtain owner feedback before making further UI or functional changes.

## Central Light Theme tokens for Module Settings (2026-10-23)

### Completed

- Added `public/assets/css/theme.css` with shared Light Theme tokens for page and panel surfaces, navigation, text, borders, primary controls, status/feedback colors, modal surfaces, focus, shadows, and reusable dimensions. `color-scheme: light` is the safe default.
- Linked the shared stylesheet from the authenticated workspace shell and standalone login page. Refactored the existing workspace, login, Module Settings list, profile form, configuration, and policy-page CSS to use shared palette variables while preserving the current blue/white and navy identity.
- Updated `PROJECT_BLUEPRINT.md` with the confirmed responsive requirements across phone through ultrawide displays, the Light Theme default/token requirements, and future user-specific theme preferences as planned work. No user theme selector, persistence, database schema, or global branding editor was added.
- No application routes, authorization, approval behavior, database records, schema, migrations, or seeders were changed.

### Files changed

- `public/assets/css/theme.css` (new)
- `app/Views/layouts/workspace.php`
- `app/Views/auth/login.php`
- `app/Views/modules/index.php`
- `app/Views/modules/form.php`
- `app/Views/modules/configuration.php`
- `app/Views/modules/policies.php`
- `PROJECT_BLUEPRINT.md`
- This `PROGRESS.md` entry. `DATABASE.md` was not changed because no schema or confirmed database decision changed.

### Verification performed

- `php -l` passed on all six changed PHP views.
- `vendor\\bin\\phpunit -c phpunit.dist.xml --no-coverage` passed: 30 tests, 95 assertions.
- `php spark routes` passed; route/filter output was unchanged and the Module Settings endpoints remain behind authentication and Superadmin filters, with CSRF on POST routes.
- A static token-reference check passed: all 57 unique `--pw-*` variables used by the updated views are defined in `theme.css`.
- No browser viewport run was available. The referenced `POS Module Settings Dashboard Collage.png` is still absent from the workspace, so no visual comparison was performed.
- No database writes or live application form submissions were performed.

### Known limitations and exact next task

- Responsive CSS exists, but the login, Module Settings list/profile/configuration/policy pages, and mobile navigation have not been inspected in a browser at representative sizes. Modal viewport behavior also remains unverified.
- The shared stylesheet establishes Light Theme tokens only. Theme selection, per-user preference storage, Dark/System themes, accent editing, and protected branding controls remain future work and have no schema or implementation in this increment.
- **Exact next task:** Start the local CI4 application, sign in with an authorized active Superadmin test account, and review the Module Settings pages at phone (390×844), tablet (768×1024), laptop (1366×768), desktop (1920×1080), and ultrawide (2560×1080) viewport sizes. Check the drawer, forms, tables, filters, cards, modal fit, keyboard focus, touch targets, clipping, and unnecessary page-level horizontal scrolling. Record findings and wait for owner feedback before expanding Module Registry functionality. If matching the collage is required, add the missing image to the workspace for visual comparison.

## Module Settings implementation and documentation (2026-10-23)

### Completed

- Implemented a Superadmin-only Module Settings registry with search/filtering, module profile editing, DRAFT creation, lifecycle controls, nested module configuration pages, and separate read-only Global Policies and Local Policies pages.
- Added server-side Superadmin filtering, static CI4 routes, module settings service/controller registration, and allowlisted module metadata audit fields. Database route/controller metadata remains descriptive; route dispatch is not dynamic.
- Added read-only summaries for permission catalog, company/branch scope assignments, approval rules and approvers, hierarchy, reports, notifications, and audit activity. No user access grants, scopes, policy categories, approval rules, approvers, or report/notification configuration are changed by these pages.
- Updated the shared workspace navigation to derive authorized entries from active registry records and known static route mappings, with parent visibility based on accessible descendants. Parent visibility remains navigation-only.
- Added responsive registry, configuration, policy, and workspace navigation layouts. Browser/device testing was not performed.
- Reconfirmed selected live schema read-only on MariaDB `10.1.19-MariaDB`: `mod_modules` has 12 rows, `COMPANY_BRANCH` remains active, `BRANCH_MANAGEMENT` remains DRAFT, and `mod_local_policy_categories` is empty. Inspected approval/policy tables remain unconfigured. No database writes, migrations, or seeders were run.
- Added a parent-navigation test confirming active accessible descendant visibility and hidden inactive descendants. Corrected the SQLite test fixture to avoid a CodeIgniter base-property collision and inconsistent batch insert columns; adjusted the hierarchy-cycle case to exercise an actual ancestor cycle.

### Files changed

- Application: `app/Filters/SuperadminFilter.php`, `app/Services/ModuleSettingsService.php`, `app/Controllers/ModuleSettings.php`, `app/Services/AuditLogService.php`, `app/Config/Filters.php`, `app/Config/Services.php`, `app/Config/Routes.php`, `app/Views/modules/index.php`, `app/Views/modules/_profile_fields.php`, `app/Views/modules/form.php`, `app/Views/modules/configuration.php`, `app/Views/modules/policies.php`, and `app/Views/layouts/workspace.php`.
- Tests: `tests/unit/ModuleSettingsServiceTest.php`.
- Documentation: `PROJECT_BLUEPRINT.md`, `DATABASE.md`, and this `PROGRESS.md` entry. `AGENTS.md` was not changed.

### Verification performed

- `php -l` passed on all 14 changed PHP application/test files.
- `php spark routes` passed and listed the Module Settings GET/POST routes behind authentication and Superadmin filters; POST routes also include CSRF filtering.
- `vendor\bin\phpunit -c phpunit.dist.xml --no-coverage` passed: 30 tests, 95 assertions. The configured test connection uses in-memory SQLite; this does not verify live MariaDB write behavior.
- Read-only live schema inspection confirmed current approval request columns/types, enum values, keys, and related approval/audit table definitions; read-only row checks reconfirmed the selected module and policy facts recorded in `DATABASE.md`. No live application POST was submitted.
- No browser-based responsive/visual comparison was performed. The referenced `POS Module Settings Dashboard Collage.png` was not present in the workspace, so only its textual design requirements could be applied.
- Approval completion, final Company Management mutation/audit transaction, real user grant configuration, and browser interaction remain untested or unavailable under the current schema/configuration. Git is not initialized, so no Git diff/status was available.

### Known issues and exact next task

- Module Settings is implemented, but its policy, grant, scope, approval, report, and notification panels are currently read-only where configuration writes lack a reviewed and approved design. Metadata `route_path` and component fields never activate a route by themselves.
- Company create/edit/status still fails closed because the current approval request structure does not safely identify a company target and no valid approval route is configured. No automatic approval or fallback approver is enabled.
- The database proposal is now recorded separately in `DATABASE.md`; unresolved design choices include approval quorum when several people are assigned to a stage, eligible scope for approving a company-create request, and whether rejected requests may be amended/resubmitted or must be replaced with a new request. The action-only global policy table also requires explicit owner approval of the `CREATE`/`UPDATE` mapping before any policy rows are added.
- **Exact next task:** Review the `Proposed, not implemented: company-target approval requests` section in `DATABASE.md`, resolve the identified approval-stage/scope/resubmission decisions, and explicitly approve or revise the DDL. Do not apply DDL, policy rows, grants, or approver assignments before separate database approval. After approval and valid configuration are established, implement and test the approval completion transaction. Separately perform browser review of responsive breakpoints; provide the missing collage reference if visual matching is required.

## Company Management implementation (2026-10-23)

### Completed in this session

- Added static Company Management routes for list, create form/submission, edit, update submission, and status-change submission. Routes use the existing authentication filter, new action authorization filter, and CSRF for POST requests.
- Added reusable `AccessPolicyService` checks for active account/role, active `COMPANY_BRANCH` module, explicit active per-user module/action grants, access expiry, and the intersection of module-level and user-level company/branch scopes. Superadmin is resolved from the active `SUPERADMIN` role and still must pass account readiness.
- Added `AuditLogService` using the verified `audit_logs` columns. Audit payload fields are allowlisted and exclude tax IDs, addresses, credentials, and arbitrary form values. Access denials, validation failures, and approval blocks are recorded when the audit insert succeeds.
- Added Company Management list and form views. The list includes in-scope inactive companies for administration. Company codes are trimmed and validated against the existing column lengths and unique key. The create form indicates the confirmed `INACTIVE` initial status.
- Added `CompanyAvailabilityService` to require an active company and, where applicable, an active branch belonging to that company.
- Company create, update, and status submissions recheck permissions and scope, require approval, and fail closed before any company mutation. Superadmin does not bypass this approval gate.
- Added isolated tests for Superadmin and explicit user grants, inactive/pending/revoked access, company/branch scope intersection, account/role readiness, inactive operational status, and the no-approver block preserving the company row count.

### Live-schema findings and implementation blocker

- Read-only checks reconfirmed MariaDB `10.1.19-MariaDB`, active module ID `11` / code `COMPANY_BRANCH`, and active role code `SUPERADMIN`. Two active Superadmin accounts exist; no account identities were displayed.
- `acl_permissions` contains the required `VIEW`, `CREATE`, and `UPDATE` codes, but `acl_module_permissions` has zero action-grant rows for `COMPANY_BRANCH`. Non-Superadmin users are therefore denied until explicit action grants are separately configured through an approved process.
- `apr_module_approval_rules`, `apr_approvers`, `apr_requests`, `apr_decisions`, and `sys_mandatory_approval_policies` contain zero rows. No valid Company Management approver is assigned.
- The existing `apr_requests` schema is designed around user/access requests and requires `target_user_id`; it has no dedicated company/entity target. Using `request_type = OTHER` and overloading a user target would not safely associate a request with a company mutation. No request is inserted and no company mutation occurs.
- Current `org_companies` and `org_branches` counts remain 1 each; `audit_logs` remains at 0 rows. No live database writes, permission/policy writes, migrations, or seeders were run.
- Company create/edit/status cannot complete until an entity-aware approval-request representation, mandatory policy mapping, and valid approver/stage configuration are separately reviewed and approved. The current code returns the confirmed no-approver message when no approver is configured and a separate safe blocker if a route exists but cannot be represented.

### Files changed

- Application: `app/Config/Filters.php`, `app/Config/Routes.php`, `app/Config/Services.php`, `app/Controllers/CompanyManagement.php`, `app/Filters/AccessPolicyFilter.php`, `app/Models/CompanyModel.php`, `app/Services/AccessPolicyService.php`, `app/Services/AuditLogService.php`, `app/Services/CompanyAvailabilityService.php`, `app/Services/CompanyManagementService.php`, `app/Views/companies/form.php`, `app/Views/companies/index.php`, and `app/Views/layouts/workspace.php`.
- Tests: `tests/unit/AccessPolicyServiceTest.php` and `tests/unit/CompanyManagementRulesTest.php`.
- Documentation: `PROJECT_BLUEPRINT.md` and this `PROGRESS.md` entry.

### Verification performed

- `php -l` passed for all changed PHP application and test files.
- `php spark routes` passed and listed all six Company Management routes with authentication, permission, and CSRF filters as appropriate.
- `vendor/bin/phpunit -c phpunit.dist.xml --no-coverage` passed: 24 tests, 75 assertions. The configured test connection is in-memory SQLite; no live MariaDB writes were performed.
- A read-only live check confirmed both active Superadmin accounts pass the new `VIEW` authorization check and the authorized Company Management query returns one company row.
- No browser-based UI, POST, CSRF, duplicate-code race, audit-insert failure, approval-completion, or final mutation/rollback test was run. These require an approval-capable schema/configuration and an authorized local browser flow.
- Git is not initialized in this project directory, so no Git diff/status is available.

### Known limitations and next step recorded at that time

- Approval request submission and final company mutations remain intentionally unavailable. The current schema cannot safely persist a company-targeted request, and no approver route is configured. Audit rollback on a successful final mutation is therefore not yet exercised; once an approved request schema exists, mutation and success-audit insertion must share a transaction.
- The inactive-company availability service is ready for future operational routes, but no other module or branch CRUD was added. Any future business operation must call the service server-side.
- This entry's earlier next step was to prepare an entity-aware request proposal. That proposal is now recorded in `DATABASE.md`; follow the current exact next task in the newer Module Settings entry at the top of this file.

## Completed and verified

- CodeIgniter 4 starter application is present; Composer declares PHP `^8.2` and CI framework `^4.7`.
- Login and pending-account prototype views exist. The visible labels are English, though some decorative symbols/copyright characters appear garbled in the source display and should be checked when those views are edited.
- The login controller builds a Google OAuth authorization redirect from environment values.
- A test-support migration/model/seeder and starter tests for the `factories` fixture exist.
- Shared AI documentation and tool-specific entry instructions are maintained in the changed files listed below.
- Git is not initialized in this project directory (checked 2026-10-22).

## Current status

Google Workspace OAuth now has a registered callback, state validation, TLS-verified token/userinfo requests, verified-email checks, active-account and active-role checks, and session ID regeneration. A temporary authenticated landing page is protected by a server-side filter that rechecks the account and role against MySQL. The full dashboard and module authorization surfaces remain unimplemented. The configured live account data currently contains only pending accounts, so no current account can complete the active-account login path.

## In progress

- Browser-based OAuth validation remains pending. Do not change account data to enable it without separate owner approval.

## Files changed in this documentation task

- `AGENTS.md` — expanded shared assistant workflow, safety rules, and Gemini manual-context guidance.
- `PROJECT_BLUEPRINT.md` — recorded owner-confirmed requirements, source-confirmed facts, proposed features, and unresolved decisions separately.
- `DATABASE.md` — clarified that live schema is unverified and documented only migration/test-support evidence.
- `PROGRESS.md` — recorded current state, known gaps, this task's documentation changes, and the next task.
- `CLAUDE.md` — added Claude entry instructions pointing to shared documents.
- `.github/copilot-instructions.md` — added Copilot instructions pointing to shared documents and safety rules.

No application code, routes, configuration, database, or `.env` was modified. No tests were run; this task changed documentation only.

## Documentation change: Superadmin GOD MODE confirmed (2026-10-22)

- Initially recorded a proposed Superadmin exception in `PROJECT_BLUEPRINT.md`; the status was superseded by the owner confirmation below.
- Explicitly preserved ordinary access rules for regular users, viewers, supervisors, and Module Admins.
- This proposal status was superseded on 2026-10-22 when the project owner confirmed the Superadmin GOD MODE requirement recorded below.
- No application code, routes, configuration, or database schema was modified. No tests were run; this was a documentation-only change.
## Owner-confirmed requirement: Superadmin GOD MODE (2026-10-22)

- Updated `PROJECT_BLUEPRINT.md` to record system-wide access across all current and future registered modules, all companies/branches and filters, and all module data/reports within business rules and available data.
- Recorded exemptions from ordinary module assignments, company/branch scopes, reporting hierarchy restrictions, and ordinary Module Admin approvals while exercising authorized Superadmin privileges.
- Recorded Superadmin management capabilities, essential security controls, sensitive-action audit logging, and that ordinary users, viewers, supervisors, and Module Admins keep their own permissions and scopes.
- No application code, routes, configuration, or database schema was modified. No tests were run; this was a documentation-only change.

## Google login implementation (2026-10-22)

- Changed files: `app/Controllers/Login.php`, `app/Models/UserAccountModel.php`, `app/Filters/AuthFilter.php`, `app/Controllers/Dashboard.php`, `app/Views/dashboard/temporary_landing.php`, `app/Views/auth/login.php`, `app/Config/Routes.php`, `app/Config/Filters.php`, `app/Config/Session.php`, `PROJECT_BLUEPRINT.md`, `DATABASE.md`, and `PROGRESS.md`.
- Fixed the PHP parse error in `app/Controllers/Login.php`; removed the admin-credentials UI option because Google Workspace is the only approved login method.
- Added OAuth state validation, HTTPS-only cURL requests with peer/host verification, Google userinfo identity and email-verification checks, account lookup through `usr_accounts` joined to `usr_roles`, active account/role enforcement, and session ID regeneration.
- Registered the callback at `login/google/callback`; the configured redirect URI is `http://localhost:8080/index.php/login/google/callback` and matches the current `baseURL` and `indexPage` route construction. Google Cloud Console registration was not inspected.
- Added the protected temporary landing page and logout route. The `auth` filter checks current account and role status server-side on each protected request. Logout is POST-only and CSRF-filtered.
- The callback does not persist tokens. No database records were inserted, updated, or deleted; no schema was changed.
- Read-only live verification confirmed roles `SUPERADMIN` (1), `USER` (2), and `VIEWER` (3), all active; account status enum values `PENDING`, `ACTIVE`, `SUSPENDED`, `INACTIVE` with default `PENDING`; and `usr_accounts.role_id` referencing `usr_roles.role_id`. Three current accounts are all pending. Details are documented in `DATABASE.md` without account identities or credential values.

### Checks actually run

- `php -l` on all nine modified PHP files: passed.
- `php spark routes`: passed; callback, dashboard, and logout routes are listed.
- `php spark filter:check GET dashboard`: passed; the auth filter is applied.
- `php spark filter:check POST logout`: passed; auth and CSRF filters are applied.
- A read-only model smoke check for nonexistent account ID `-1`: passed; returned no account and exposed no account data.
- `composer test`: 5 tests and 7 assertions passed, but PHPUnit emitted a warning that no code coverage driver is available and the Composer command returned exit code 1.
- `vendor/bin/phpunit --no-coverage`: passed; 5 tests and 7 assertions, exit code 0.
- Google OAuth was not tested end-to-end in a browser or against the configured Google OAuth application. No currently active account exists to complete the callback path.
- Account matching uses Google's verified email because the verified `usr_accounts` schema has no Google subject identifier column; no schema change was made.

## Known limitations

- Verify that the exact configured callback URI is registered in Google Cloud Console, without changing credentials or allowed domains.
- Activate an authorized test account through the established administrator process before attempting the successful browser login test; account data changes were not authorized in this task.
- The temporary landing page is the only protected application page currently present. Module-level permissions, scopes, hierarchy rules, approvals, and administrative features are not implemented by this login work.
- Live migration status and the remaining application tables' full constraints/indexes have not been verified.
- Git is not initialized in the project directory, so a Git diff is unavailable.

## Exact next task

Confirm the callback URI in Google Cloud Console, then arrange administrator activation of an authorized test account and run a browser-based Google login, landing-page, and logout check. After that, define and approve the ordinary-role access matrix and module-level permission/scope behavior before building protected modules.

## Google Cloud and account activation review (2026-10-22)

- Rechecked the configured redirect URI without reading or displaying OAuth credential values: `http://localhost:8080/index.php/login/google/callback`. It matches `login/google/callback`, which is registered as a GET route, and matches the current `baseURL` plus `indexPage` URL construction.
- The Google Cloud Console configuration itself was not accessible for inspection. For the OAuth client used by this server-side flow, verify it is a **Web application** client and that **Authorized redirect URIs** contains the exact URI above. Google requires exact matching, including scheme, host, port, path, and trailing slash; localhost HTTP is accepted for development. This flow uses a server redirect, not Google JavaScript, so an Authorized JavaScript origin is not required by the inspected application code.
- Verify the consent/audience configuration is appropriate for the intended Workspace users and any Testing audience restrictions. The application requests only `openid email profile`; do not add broader scopes for this login flow. Do not expose or replace the configured client credentials during verification.
- The application currently has no account activation route/controller or administrator activation UI. Login rejects accounts unless both account and role statuses are `ACTIVE`; the auth filter repeats these checks on protected requests. The last read-only database verification recorded three accounts, all `PENDING`. No account was activated or modified in this review. A successful-login test requires an authorized administrator to activate a designated test account using an already-approved account-administration process; no such process is represented in the current application, so it must be established by the owner/DB administrator before use. Do not treat direct SQL changes as an existing approved workflow.
- Reviewed the login controller, account model, auth filter, routes, and available controllers/views. No code defect was identified that warrants a change in this review. OAuth end-to-end behavior and actual Google Cloud Console registration remain unverified.
- Preserved the owner-confirmed Superadmin GOD MODE requirement as a project requirement. No module access, dashboard, code, route, configuration, account data, or schema changes were made. This update changes `PROGRESS.md` only; no tests were run.

## Exact next task

Have the owner/authorized administrator confirm the Google OAuth client and consent/audience settings against the checklist, and establish an authorized activation path for one designated test account. Then follow the local browser checklist to test invalid OAuth state, pending-account rejection, active-account login, protected-page/session behavior, and logout. Record the actual browser results; do not claim end-to-end OAuth success until it has been tested against the configured Google application.

## Login readiness implementation (2026-10-22)

- Updated `UserAccountModel` with one shared login-readiness check requiring account status `ACTIVE`, a positive explicitly assigned role ID, and role status `ACTIVE`. Account lookups now use a left join and select `role_id`, so a role-less row is rejected instead of disappearing from the lookup. This does not grant module permissions; module access and company/branch scopes remain separate checks.
- Updated Google callback handling to reject accounts that fail the shared readiness rule and return them to the existing login screen with an English “Account Setup Pending” message. No authenticated session is created on this path. `AuthFilter` applies the same readiness check to protected requests, destroys stale sessions for accounts that are no longer ready, and safely handles nullable names.
- Added the pending/setup message to the existing login view without changing its layout/background. Removed the stale `login/pending-preview` route, which pointed to a controller method that does not exist.
- Added automated tests for active account/active role readiness and rejection of pending, inactive, suspended, missing/invalid role, and inactive-role cases.
- Username/password login and first-time Google account registration remain unimplemented. The last recorded live metadata in this document set says `usr_accounts` has no `username` column and requires `role_id`, `employee_code`, `first_name`, and `last_name`; credential lookup and role-less minimal registration therefore depend on the separately approved schema migration. No live database query was run in this implementation pass, and no database changes or account-data writes were made. CSRF and rate limiting for a credential submission endpoint also remain pending with that endpoint; Google OAuth continues to use state validation.
- Google OAuth was not tested end-to-end against Google. No account was activated or created, so successful browser login remains unverified.

### Checks actually run

- `php -l app/Controllers/Login.php`, `php -l app/Models/UserAccountModel.php`, `php -l app/Filters/AuthFilter.php`, `php -l app/Config/Routes.php`, and `php -l tests/unit/LoginReadinessTest.php`: all passed.
- `php spark routes`: passed. Google callback is registered; dashboard remains auth-filtered; logout remains auth- and CSRF-filtered; stale pending-preview route is absent.
- `vendor/bin/phpunit --no-coverage`: passed, 9 tests and 17 assertions (exit code 0).
- No Google browser test, credential login test, live schema query, or database operation was run.

## Exact next task

Review and separately approve the `usr_accounts` username/nullable-field migration in `DATABASE.md`. After it is applied through the authorized process and verified, implement the credential login endpoint with CSRF and configured rate limiting, plus first-time verified-Google pending registration using the confirmed schema. Then add tests for those paths and arrange an authorized active test account for browser verification. Do not claim either login method works end-to-end until tested.

## Login UI and credential endpoint (2026-10-22)

- The owner reported that the separately proposed username migration was applied. No SQL was run and the live schema was not queried in this task; `DATABASE.md` therefore remains a proposal pending independent schema verification.
- Updated the existing login view to retain the Google Workspace primary action and background/branding, add the OR divider and username/password form, show/hide toggle, same-page Contact Admin modal with the supplied administrator email, and responsive vertical scrolling to avoid clipping. The authorized-access notice and footer remain.
- Added `POST login/credentials` with an explicit CSRF filter. The controller looks up the username, uses `password_verify()` through the account model, applies the same active-account/explicit-active-role readiness check as Google OAuth, regenerates the session on success, and uses a generic credential failure message. A valid password for an account that is not ready receives the existing English setup-pending message and no session.
- Added CodeIgniter's throttler using the configured file cache handler: up to 5 attempts per 60 seconds for an IP/username pair and 60 attempts per 60 seconds per IP. Throttle keys are hashes; limiter errors fail closed and log only a fixed stage name.
- Google OAuth implementation was preserved. No role was assigned, account activated, or database data changed. The actual credential endpoint and browser layout were not exercised against the reported migrated live schema.

### Checks actually run

- `php -l app/Controllers/Login.php`, `php -l app/Models/UserAccountModel.php`, `php -l app/Config/Routes.php`, `php -l app/Views/auth/login.php`, and `php -l tests/unit/LoginReadinessTest.php`: all passed.
- `php spark routes`: passed; `POST login/credentials` is registered, and existing Google, protected dashboard, and logout routes remain registered.
- `php spark filter:check POST login/credentials`: passed; CSRF is applied to the credential route.
- `vendor/bin/phpunit --no-coverage`: passed, 10 tests and 22 assertions (exit code 0). The added assertions cover stored-hash verification, incorrect-password rejection, and missing-hash/account rejection alongside existing readiness checks.
- No live database query, credential endpoint integration test, browser UI check, account modification, or Google OAuth end-to-end test was run.

## Exact next task

Have the authorized database operator provide non-identifying verification that the approved username migration and username backfill are live (username column/index and required nullable fields), then test credential login with an authorized active account and verify pending/inactive rejection, throttling, session regeneration, and logout in a browser. Separately perform Google Workspace browser sign-in against the configured OAuth application. Update `DATABASE.md` only after schema details are independently verified.

## Google pending-account registration and setup modal (2026-10-22)

- Implemented automatic pending-account creation after Google returns a valid identity with a verified email and no existing `usr_accounts` match. The inserted row uses the verified email, `account_status = PENDING`, and null `username`, `password_hash`, employee code, names, role, reporting position, and default module. No module access, company/branch scope, or hierarchy rows are created.
- The account email unique key remains the duplicate protection. If concurrent first logins race, a database insert exception is followed by a lookup of the same verified email; if no row is found or persistence fails, sign-in fails closed with a generic message. No user receives an authenticated session from pending registration.
- Existing accounts that fail the shared account/role readiness check and newly created accounts are redirected to the login page with the Account Setup Pending modal. The modal follows the supplied visual reference and includes identity verification, setup categories, contact link, close icon, and Close Panel button. Its checklist is explanatory copy; live workflow/approval states are not queried.
- The owner previously reported the migration applied separately. This implementation did not query live schema metadata or run SQL. `DATABASE.md` still reflects the last verified schema and must not be treated as confirming that the migration is live. The first-time registration code depends on the reported nullable columns and may fail closed if that deployment is incomplete.
- `php -l` passed for the modified controller, model, view, and unit test. `php spark routes` passed with the existing login/callback, credential, dashboard, and logout routes. `vendor/bin/phpunit --no-coverage` passed: 11 tests and 31 assertions. Tests cover pending record attributes and password/readiness helpers; they do not run a database insert or end-to-end OAuth callback.

## Exact next task

Obtain non-identifying confirmation of the live `usr_accounts` migration, then test a first-time verified Google identity and a returning pending identity in a browser. Confirm each creates or reuses one pending record, displays the modal, and receives no authenticated session. Then verify credential login and Google Workspace login against the deployed database and configured OAuth application; update `DATABASE.md` only after schema metadata is independently verified.

## Google OAuth TLS diagnosis (2026-10-22)

- The reported generic Google sign-in error corresponds to the OAuth token exchange. Recent sanitized log markers show `token_exchange` followed by `token_response_validation`; cURL reported HTTP status 0 and errno 60 on the observed attempts. No identity verification, account lookup, or pending-account creation stage was reached.
- PHP reported no configured `curl.cainfo` or `openssl.cafile`. The XAMPP Apache CA bundle exists at `D:\XAMPP\apache\bin\curl-ca-bundle.crt`.
- Updated `Login::requestGoogleJson()` to prefer an existing configured cURL/OpenSSL CA file and otherwise use the readable XAMPP bundle. TLS peer and hostname verification remain enabled. No OAuth secrets, tokens, or account identifiers were read or logged.
- A read-only connection probe using the XAMPP bundle could not reach Google from this execution environment (cURL errno 7), so the bundle has not been verified against Google's endpoint here. Do not claim the browser sign-in is fixed until retested in the local browser. If errno 60 persists there, inspect whether the local proxy/VPN certificate is trusted by the selected CA bundle; do not disable TLS verification.
- `php -l app/Controllers/Login.php`, `php spark routes`, and `vendor/bin/phpunit --no-coverage` passed; PHPUnit: 11 tests, 31 assertions. No database operation or live OAuth transaction was run.

## Exact next task

Retry Google Workspace sign-in in the local XAMPP browser and inspect only the resulting fixed-stage OAuth log marker and cURL errno. Confirm whether the token exchange advances beyond TLS; if it does, verify pending account creation and the modal with non-identifying database counts. Do not expose tokens, emails, or account rows.

## Credential option collapsed by default (2026-10-22)

- Updated the login card to show a full-width “Sign in with Admin Credentials” button beneath the OR divider. Username/password controls are hidden until the button is activated; the Google Workspace button remains the first and primary option.
- Credential submission failures and valid-password-but-not-ready responses reopen the credentials panel after redirect so the user can read the message and retry. The password value is never repopulated.
- The admin credential toggle updates `aria-expanded`, focuses the username field when opened, and leaves the existing Contact Admin/modal, security notice, footer, and responsive scrolling in place.
- `php -l app/Controllers/Login.php` and `php -l app/Views/auth/login.php`: passed. No browser visual test was run.
- Updated the credential-option label to “Sign in with Masterfile Credentials” at the owner’s request; the authentication behavior is unchanged.

## Credential login and pending-registration migration review (2026-10-22)

- Reverified live metadata only for `pos_workspace_v3`: `usr_accounts` has no username column; username must therefore be added for the owner-confirmed secondary credential method. `employee_code`, `first_name`, `last_name`, and `role_id` are currently `NOT NULL`; the current table cannot represent the requested role-less pending Google registration. Email is `NOT NULL` with a unique index. The role FK references `usr_roles.role_id`; account column types, unsigned attributes, collations, keys, and account/role status enums are recorded in `DATABASE.md`.
- Role/status aggregates were checked without recording account identities, email addresses, employee codes, or credential values. Current account rows are all pending and have non-null current role/name/employee fields. No accounts were modified.
- Added a migration proposal, a non-destructive compatibility rollback, a separate full-rollback warning, anonymized username backfill candidate, validation queries, and a backup/restore and test plan to `DATABASE.md`. The candidate username format `pwv3_u_<user_id>` remains unapproved; it avoids personal attributes but reveals the internal numeric account ID.
- Updated `PROJECT_BLUEPRINT.md` to record the owner-confirmed priority of Google Workspace as primary and distinct username/password as secondary, with identical activation/authorization checks, automatic role-less pending registration, verified unique Google email, and the pending modal. These requirements are not yet implemented. No UI, authentication, or migration-dependent code was added because it would not work against the current verified schema.
- Updated `Login.php` to add fixed-stage, non-PII OAuth failure diagnostics. No secrets, tokens, provider response bodies, account identifiers, or emails are logged. Existing OAuth behavior and security checks are unchanged by this diagnostic change.
- No migration, database write, backup, restore, account activation, or account-data change was performed. No `.env` values were read or printed. The live backup and restore procedure remains unverified.

### Checks actually run for this review

- `php -l app/Controllers/Login.php`: passed, no syntax errors.
- `php spark routes`: passed; the Google callback, protected dashboard, and authenticated/CSRF-protected logout routes are registered.
- `vendor/bin/phpunit --no-coverage`: passed; 5 tests and 7 assertions (exit code 0).
- No browser OAuth test, migration scratch test, backup, or restore test was run. The PHPUnit suite does not verify the new schema proposal or a live Google OAuth transaction.

### Next task

Owner: approve or revise the privacy-preserving username format/backfill and exact migration/rollback proposal in `DATABASE.md`. After approval, prepare and verify a timestamped backup restore in a scratch database, then return the exact preflight results for separate final approval before any production migration or account-data update. Keep credential login and automatic pending-account registration disabled until their approved schema is live and the dependent implementation is ready.

## Credential redirect TypeError fix (2026-10-22)

- The browser screenshot showed a CodeIgniter `RedirectResponse::with()` TypeError after credential submission. `Login::credentials()` passed an array to `with()`, whose first argument must be a flash-data key string. The pending-account response and generic credential-failure response both used that unsupported call shape.
- Updated `app/Controllers/Login.php` to attach each flash-data item with a separate `with(key, value)` call. This preserves the pending-account modal, generic failure message, and credential-panel reopening behavior.
- The owner previously authorized updating the existing local Superadmin account credentials and activation. Its password is stored as a PHP password hash; no plaintext credential is recorded here.
- Checks run after the fix: `php -l app/Controllers/Login.php` passed; `php spark routes` passed and listed POST `login/credentials` with the CSRF filter; `vendor/bin/phpunit --no-coverage` passed (11 tests, 31 assertions). No remaining `->with([` call exists in `Login.php`.
- The browser flow has not yet been retested after the fix, so credential login is not yet confirmed end-to-end.

### Exact next task

Retry credential sign-in in the local browser. Confirm it reaches the protected landing page, then confirm logout; also verify a rejected credential returns the generic English message without a framework exception. Do not expose the password or account data in screenshots/logs.

## Credential failure message clarification (2026-10-22)

- Changed the generic credential failure text in `app/Controllers/Login.php` to “Invalid username or password. Please try again.” It remains generic across invalid credentials and throttling failures and does not reveal account existence or which field failed.
- The browser screenshot's submitted password did not match the password value previously requested for the local account, so rejection was expected. No account data was changed during this message update.
- `php -l app/Controllers/Login.php` and `vendor/bin/phpunit --no-coverage` were run after the message change; results are recorded in the current session report.

### Exact next task

Retry local credential login with the password value configured for the Superadmin account, then verify the protected landing page and logout. Do not include password values in screenshots.

## Authenticated workspace shell (2026-10-22)

- Added `app/Views/layouts/workspace.php` with a responsive sidebar, top bar, authenticated-user summary, POST/CSRF-protected sign-out form, main content area, and footer.
- Updated `app/Views/dashboard/temporary_landing.php` to render through the shared shell and retain the authenticated account identity, role, and status summary. The sidebar links only to the existing Overview route; no module links, permission rules, routes, controller behavior, or database structures were added.
- The mobile sidebar has an accessible toggle, overlay close control, Escape handling, and reduced-motion support. UI text remains English.
- `php -l app/Views/layouts/workspace.php` and `php -l app/Views/dashboard/temporary_landing.php` passed. A browser visual/responsive check has not yet been run.

### Exact next task

Sign in in the local browser and inspect the authenticated shell at desktop and mobile widths. Confirm the user summary renders, the mobile sidebar opens/closes, and the existing sign-out action returns to `/login`. Define the authorized module navigation and scope rules before adding any additional sidebar links.

## Avatar account menu and notifications placeholder (2026-10-22)

- Moved the account avatar control to the right side of the top bar and made it open a dismissible account menu with the signed-in identity, Account details link to the existing summary, Settings options, and the existing CSRF-protected POST sign-out action.
- Theme and Change profile image are shown as disabled “Coming soon” options because no settings routes or implementation exist. Added a disabled notification bell labeled “Notifications coming soon”; no notification data or behavior is claimed.
- No route, controller, authentication behavior, schema, or account data changed. The live logout route remains the only action in this menu that changes application state.
- `php -l app/Views/layouts/workspace.php` and `php -l app/Views/dashboard/temporary_landing.php` passed. Route/source inspection found no existing account settings or notification routes. Browser interaction testing remains pending.

### Exact next task

Open the account menu in the local browser at desktop and mobile widths. Verify it opens/closes by button, outside click, and Escape, Account details scrolls to the summary, disabled options do not navigate, and Sign Out still posts successfully.

## Profile status and local date/time (2026-10-22)

- Removed the “Secure workspace” sidebar footer.
- Added Active account status (green), browser Network status (blue online / red offline), and Database status (green Connected / red Offline) to the avatar profile menu. The dashboard controller performs a static `SELECT 1` health check and logs only a generic warning if the check fails; no database credentials or query errors are displayed.
- The Network badge uses `navigator.onLine`, which reports the browser's network state and does not guarantee public internet reachability. The existing authentication filter still validates the account against MySQL before rendering the protected page; it was not weakened or changed. If that validation cannot reach the database, the page may fail before the profile status is rendered.
- Added a live local date/time in the top bar, refreshed every 30 seconds using the browser's local timezone.
- PHP syntax checks passed for `app/Controllers/Dashboard.php`, `app/Views/layouts/workspace.php`, and `app/Views/dashboard/temporary_landing.php`. Browser visual/status verification remains pending; no route or schema changes were made.

### Exact next task

Check the profile status colors and live clock in the local browser. Verify the browser online/offline signal and database Connected indicator, then confirm the dashboard remains inaccessible when unauthenticated. Keep the authentication filter fail-closed.

## Profile status indicators removed (2026-10-22)

- Removed the Account Active, Network Online/Offline, and Database Connected/Offline indicators from the avatar menu at the owner's request because they did not fit the design.
- Removed the dashboard's standalone database connectivity query, which was only used to render the removed indicator. Authentication and its database-backed checks were not changed.
- Kept the local date and time in the top bar, along with the profile menu, settings placeholders, notification placeholder, and sign-out action.
- No routes, schema, or account data changed. PHP syntax checks were run for the modified PHP files; browser visual verification remains pending.

### Exact next task

Review the authenticated workspace in a local browser at desktop and mobile widths. Confirm the profile menu no longer shows the three status indicators, the date and time remain visible, and the sign-out action still works.

## Module setup planning and session close (2026-10-22)

### Completed in this session

- Removed the Account Active, Network Online/Offline, and Database Connected/Offline indicators from the profile menu and removed the dashboard-only `SELECT 1` query that supplied the database indicator.
- Kept the top-bar local date and time, account menu, settings placeholders, notification placeholder, and existing sign-out action.
- Inspected `app/Config/Modules.php`, `app/Config/Routes.php`, `app/Filters/AuthFilter.php`, the temporary landing view, and the relevant blueprint/database documentation for the proposed next Module Settings area.
- Confirmed from the current application source that there is no business Module Settings route, controller, model, or page yet. `app/Config/Modules.php` is CodeIgniter package auto-discovery configuration, not the product module registry.
- Reviewed the supplied Module Settings image as a visual reference. It is not treated as confirmation of an initial business-module catalog, permission matrix, approval rules, or scope behavior.

### Files changed and checks

- Application files changed: `app/Controllers/Dashboard.php` and `app/Views/layouts/workspace.php`.
- Documentation changed: this `PROGRESS.md` entry.
- `php -l app/Controllers/Dashboard.php`: passed; no syntax errors.
- `php -l app/Views/layouts/workspace.php`: passed; no syntax errors.
- No automated tests or browser checks were run in this session. No live module-related database metadata was queried. No routes, schema, or account data were changed.

### Known issues and planned work

- Module Settings and business module registry are not implemented. The exact module catalog and ordinary-user module permissions remain unresolved in `PROJECT_BLUEPRINT.md`.
- The live schema for module registry, module access, company/branch scope, approvals, hierarchy, and related audit records has not been comprehensively verified. `DATABASE.md` should only be updated after schema facts are verified against live metadata or authoritative migrations.
- Desktop/mobile visual confirmation of the profile menu cleanup and date/time remains pending.

### Exact next task

Perform a read-only inventory of the actual `pos_workspace_v3` module-related schema: enumerate relevant tables, columns, indexes, and foreign keys from metadata only, without retrieving account identities or other personal data. Compare those results with existing application code, then present the Module Settings implementation plan and any unresolved permission/catalog decisions before changing code or database data.

## Google profile details on sign-in (2026-10-22)

### Completed

- Updated the Google callback to use optional `given_name` and `family_name` claims from the already verified Google UserInfo response. New pending accounts save supplied names while remaining `PENDING` with no role, employee code, password, or module access.
- For existing Google accounts, the callback fills only blank first/last-name fields and preserves non-empty values previously assigned by an administrator. Name input is trimmed, control characters are removed, and values are limited to the verified 100-character column size. A best-effort profile-sync failure is logged generically and does not bypass or alter account-readiness checks.
- At this stage, the callback used the validated Google picture URL in the session only because no avatar column existed; that blocker was subsequently resolved by the approved schema change below.
- Recorded the owner-confirmed profile requirement in `PROJECT_BLUEPRINT.md`: birthday is optional user input, not requested from Google sign-in.
- Updated `DATABASE.md` to reflect the owner-provided phpMyAdmin `DESCRIBE usr_accounts` screenshot: nullable/unique username, nullable employee code/name/role fields, and no avatar/birthday column. The current foreign key and complete indexes were not re-queried. The old migration proposal is labeled historical because the owner reported applying it separately.

### Files changed and checks

- Application: `app/Controllers/Login.php`, `app/Models/UserAccountModel.php`, `app/Views/layouts/workspace.php`.
- Tests: `tests/unit/LoginReadinessTest.php`.
- Documentation: `PROJECT_BLUEPRINT.md`, `DATABASE.md`, and this `PROGRESS.md` entry.
- PHP syntax checks passed for all four changed PHP files.
- `vendor/bin/phpunit --no-coverage` passed: 12 tests, 35 assertions.
- `php spark routes` was run and confirmed the existing Google callback, protected dashboard, credential POST, and logout routes; no routes changed.
- No Google OAuth browser transaction, live database metadata query, account update, migration, or schema change was run in this session. Therefore end-to-end profile synchronization and avatar rendering remain unverified.

### Known issues and next work

- At this stage, birthday input and persistent avatar storage were blocked by missing columns; the owner later approved both fields, as documented below.
- `DATABASE.md` records screenshot-visible column/key information, but exact current foreign keys, collations, and complete indexes still require a read-only metadata check.

### Exact next task

Test Google sign-in locally with an authorized active account. Verify that missing name fields are filled from Google claims and that the avatar appears, without recording profile values or OAuth data. Do not claim end-to-end success before that browser check. Then prepare a separate optional-birthday storage and UI proposal, including exact DDL and rollback, for approval before any schema change.

## Approved avatar and optional birthday fields (2026-10-22)

### Completed

- After the owner's explicit approval, added nullable `avatar_url VARCHAR(2048)` and nullable `birthday DATE` to `usr_accounts`. Existing columns and account rows were not altered.
- Updated Google registration/profile synchronization to store only a valid HTTPS Google-hosted picture URL in `avatar_url`; pending registrations remain pending and receive no authenticated session. Google names continue to fill blank name fields only.
- Added birthday loading to the authenticated session and an optional Birthday field in the account summary. The authenticated, CSRF-protected POST endpoint accepts an empty value to clear the date and rejects malformed or future dates. All UI text is English.
- Updated `AuthFilter` and credential-login data selection so a saved avatar and birthday are available for authenticated sessions regardless of sign-in method.
- Updated `DATABASE.md` with the executed DDL, live post-change metadata, and a rollback statement that is documented but not run. Updated `PROJECT_BLUEPRINT.md` to record the confirmed profile requirement and implementation.

### Database operation and validation

- Pre-change read-only metadata confirmed both columns were absent. Read-only `information_schema` queries confirmed the account indexes and `fk_usr_accounts_role` before the change.
- Created a timestamped `mysqldump --single-transaction --quick --routines --triggers --no-tablespaces` backup outside the project web root. It was non-empty (55,956 bytes). Restore-to-scratch validation was not run.
- Executed:

  ```sql
  ALTER TABLE `usr_accounts`
    ADD COLUMN `avatar_url` varchar(2048) DEFAULT NULL AFTER `last_name`,
    ADD COLUMN `birthday` date DEFAULT NULL AFTER `avatar_url`;
  ```

- Post-change `php spark db:table usr_accounts --metadata` and a read-only `information_schema.columns` query confirmed nullable `avatar_url varchar(2048)` and `birthday date`, both with NULL defaults, and zero indexes on these columns. No account rows were modified by the schema operation.
- An initial DDL invocation failed before changing the schema due to shell quoting; the corrected command above succeeded and was verified.

### Files changed and checks

- Application: `app/Controllers/Login.php`, new `app/Controllers/AccountProfile.php`, `app/Models/UserAccountModel.php`, `app/Filters/AuthFilter.php`, `app/Config/Routes.php`, `app/Views/layouts/workspace.php`, and `app/Views/dashboard/temporary_landing.php`.
- Tests: updated `tests/unit/LoginReadinessTest.php`; added `tests/unit/AccountProfileTest.php`.
- Documentation: `DATABASE.md`, `PROJECT_BLUEPRINT.md`, and this `PROGRESS.md` entry.
- `php -l` passed for every changed PHP file.
- `php spark routes` passed and showed POST `account/profile/birthday` protected by `auth` and `csrf` filters.
- `vendor/bin/phpunit --no-coverage` passed: 15 tests, 47 assertions.
- No Google OAuth browser transaction or birthday-form browser test was run. Google profile sync is not claimed as end-to-end verified. Backup restore remains untested.

### Exact next task

Test the birthday form in the local browser with a valid date, clearing the field, an invalid date, and a future date; confirm other account fields remain unchanged. Then test Google Workspace sign-in with an authorized active account to verify the saved avatar and blank-name fill behavior without recording profile values, emails, or OAuth data. Do not execute the documented rollback; dropping either column after data is entered would lose profile data.

## Pending account modal avatar (2026-10-22)

### Completed

- Added an avatar to the Account Setup Pending modal. The view accepts only a normalized HTTPS Google-hosted avatar URL and escapes it in the image attribute; it keeps a neutral profile icon when no safe image is available.
- Passed the avatar through pending redirects for newly registered Google identities, existing not-ready Google accounts, credential attempts, and protected requests whose account is no longer ready. Pending users still receive no authenticated session.

### Files changed and checks

- Application: `app/Controllers/Login.php`, `app/Filters/AuthFilter.php`, and `app/Views/auth/login.php`.
- Documentation: this `PROGRESS.md` entry.
- `php -l app/Controllers/Login.php`, `php -l app/Filters/AuthFilter.php`, and `php -l app/Views/auth/login.php`: all passed.
- `vendor/bin/phpunit --no-coverage`: passed, 15 tests and 47 assertions.
- No browser or live Google OAuth test was run. Git is not initialized in the project directory, so no Git diff/status is available.
- A later screenshot showed the browser rendering image alt text when the avatar URL failed to load. The first fallback handler was attached at `DOMContentLoaded`, which could miss an earlier image error. It now handles both prior and subsequent image failures and shows the profile icon; `php -l app/Views/auth/login.php` passed. This rendering fix has not yet been browser-tested.

### Exact next task

Use the local browser to test a pending Google account and a not-ready credential account. Confirm the stored/Google avatar appears in the Account Setup Pending modal, the fallback icon appears when no safe avatar exists or the image request fails, and neither path creates an authenticated session. Separately review and approve the proposed authenticated profile-image upload before implementation; current profile menu still marks image changes as coming soon. Do not record profile values or OAuth data.

## Session close: pending avatar fallback follow-up (2026-10-22)

### Completed this turn

- Fixed the pending modal image-error fallback timing: it now catches a failed avatar request whether the failure occurs before or after the page script attaches its handler. The fallback hides the broken image and reveals the profile icon.
- Updated this progress record with the observed screenshot issue, the timing cause, and the browser test still required.

### Files and checks this turn

- Application: `app/Views/auth/login.php`.
- Documentation: `PROGRESS.md`.
- `php -l app/Views/auth/login.php`: passed, no syntax errors.
- No browser test, Google OAuth test, automated test suite, database operation, or schema change was run this turn.

### Known issue and exact next task

- The browser screenshot establishes that the image request failed, but does not identify why the Google-hosted image URL failed. Refresh `/login` and test pending Google and credential flows in the browser, confirming a loaded avatar displays where available, failed/missing images show the fallback icon, and pending users receive no authenticated session. Do not capture profile values or OAuth data.
- Profile-image upload remains unimplemented; the workspace profile menu still marks it as coming soon. Review/approve a secure upload design before adding its authenticated endpoint and storage behavior.

## Unified Module Settings workspace (2026-10-23)

### Completed

- Combined the Superadmin navigation entry into one **Module Settings** destination. `/module-registry` now redirects to the canonical Module Management workspace; existing registry create/edit/status handlers remain available and protected.
- Reworked the Outlet List management view into a responsive settings workspace with module profile summary, section navigation, saved action/role grants, approval mode and ordered approver configuration, company/branch scope, and a module catalog with profile and lifecycle actions.
- Kept unsupported hierarchy/report/notification configuration explicitly informational; no controls claim those settings are saved. No database schema, migration, seed, authentication behavior, or business data changed.
- Updated module profile form breadcrumbs and cancel navigation to return to the unified Module Settings workspace.

### Files changed and verification

- Changed `app/Views/layouts/workspace.php`, `app/Controllers/ModuleManagement.php`, `app/Controllers/ModuleRegistry.php`, `app/Views/management/module.php`, `app/Views/module_registry/form.php`, `PROJECT_BLUEPRINT.md`, and this progress log.
- PHP syntax checks passed for the changed PHP controller and view files. Automated, database, authenticated-role, and browser/viewport tests were not run for this presentation/navigation change.

### Exact next step

- Open the unified Module Settings page in a browser at phone, tablet, laptop, and desktop widths; confirm the catalog and Outlet List settings are legible and all existing save, profile edit, and lifecycle actions return to the unified workspace. Extend the selector only when additional module-specific settings have supported load/save handlers.

### Module Settings render error follow-up (2026-10-23)

- Fixed the logged `Undefined variable $registeredModules` error on `GET /module-management` by passing the first catalog page from `ModuleRegistryService::paginate()` into the view.
- `php -l` passed for `app/Controllers/ModuleManagement.php` and `app/Views/management/module.php`. The authenticated page was not browser-tested in this follow-up.

## Live database documentation recheck (2026-10-23)

- Rechecked `php spark migrate:status`: the five approved application migrations remain applied in batch 1.
- Rechecked `php spark db:table --show`: the live database contains 20 tables; counts match the documented state (2 accounts, 1 role, 5 migration records, 2 modules, 8 action catalog rows; remaining tables empty).
- Ran metadata-only inspection for `usr_accounts`, `usr_roles`, `mod_modules`, and `out_outlets`; no row values or credential fields were read. Confirmed 15 account columns including nullable avatar/birthday fields, 8 role columns, 14 module columns, and 12 outlet columns.
- Updated `DATABASE.md` with the timestamped current table/row/column inventory and verification limits. No database writes or migrations were performed. `PROJECT_BLUEPRINT.md` was not changed.
- Exact next step: re-query complete indexes and foreign keys only if implementation planning depends on those live constraints; the applied migration files remain authoritative for migration-created tables.

## Business screens/controllers removed; login retained (2026-10-23)

### Completed

- Removed Outlet List, Employee Management, Approval Queue, Module Registry, and Module Management controllers and views.
- Removed their static routes and workspace sidebar links. Kept Login, Google/credential sign-in routes, auth filters/configuration, `UserAccountModel`, BaseController, and Dashboard landing page so successful sign-in retains a destination.
- No database tables/data, migrations, login implementation, or authentication configuration were changed. No business model classes existed in `app/Models`; `UserAccountModel.php` remains for login.

### Verification

- `php -l` passed for `app/Config/Routes.php`, `app/Views/layouts/workspace.php`, `app/Controllers/Login.php`, and `app/Models/UserAccountModel.php`.
- `php spark routes` confirms Login, Google authorization/callback, credential POST, protected Dashboard, and logout remain; removed business routes are absent.
- No automated or browser tests were run. The two module services and their test files remain on disk but have no routes/screens after this reset.

### Exact next step

- Confirm the login and post-login Dashboard in a browser. If the owner wants the remaining unused business services/tests or schema migrations removed too, handle that separately after checking database dependencies; no live schema cleanup has been done.

### Empty application folders removed (2026-10-23)

- Removed only empty directories under `app/Views`, `app/Controllers`, and `app/Models`. No files or non-empty folders were removed.
- Exact next step: none for this cleanup; continue only with the next owner-requested task.

## Master blueprint and documentation update (2026-10-25)

### Completed

- Reviewed the supplied master blueprint request, `AGENTS.md`, this progress log, existing `PROJECT_BLUEPRINT.md` and `DATABASE.md`, `README.md`, current routes, workspace layout, session configuration, and the five application migrations. No `SETUP.md` exists.
- Updated `PROJECT_BLUEPRINT.md` with the current route/screen baseline, emerald UI direction, single-active-session requirement and proposed server-side design, module-specific action registry, separate request/approval permissions, hierarchy and scope rules, module registry lifecycle, approval flow, architecture/workflow diagrams, screen specifications, logical entity mapping, acceptance matrix, and implementation roadmap. Corrected stale top-level implementation claims and marked earlier first-scope notes as historical.
- Updated `DATABASE.md` with a requirement-to-migration mapping and an explicit statement that live MySQL was not inspected for this documentation update; the 2026-10-23 inventory remains dated evidence only.
- Replaced the generic CI4 starter `README.md` with project-specific setup and documentation guidance. Setup preserves the public document root requirement and warns against unapproved migrations/live-data operations; it includes no secrets.
- No application code, routes, authentication/session behavior, schema, or live data changed. No backup was needed because this was an incremental documentation edit.

### Checks and limits

- Inspected `app/Config/Routes.php`, `app/Config/Session.php`, `app/Views/layouts/workspace.php`, and migrations 000001–000005.
- No tests were run; this task changed documentation only.
- Live MySQL was not inspected. The documented migration and inventory evidence is dated and remains subject to a fresh metadata check before implementation.
- Existing UI source uses blue/navy tokens; emerald primary styling is documented as a requirement, not as implemented.
- Single-active-session enforcement, business routes/screens, module-action authorization, and approval workflow are requirements/proposed work, not implemented features.

### Exact next task

Reconcile the approved action catalog, single-session storage/concurrency design, and existing migration schema against a fresh read-only live metadata inventory. Then present the specific authentication/schema implementation proposal for owner approval before changing session behavior, routes, schema, or security-sensitive code.


## Read-only implementation and schema audit (2026-10-25)

### Scope and verification

- Reviewed AGENTS.md, PROJECT_BLUEPRINT.md, DATABASE.md, README.md, this progress log, route/authentication controllers, account model, filters, retained services, relevant route/security/session/database/migration configuration, all five app migrations, Login/Dashboard/workspace views, shared theme CSS, and the core auth/RBAC unit tests. Also inventoried all current controller/model/filter/service/view/test paths.
- Current source inventory: controllers BaseController, Home, Login, Dashboard; model UserAccountModel; filters AuthFilter and SuperadminFilter; services ModuleRegistryService and OutletAccessService. No business controller/model/view or account-management/approval screen exists. app/Config/Routing.php has autoRoute=false; service artifacts without routes/controllers are not HTTP features.
- Live MySQL verification succeeded with read-only php spark migrate:status, db:table --show, and metadata-only db:table table --metadata for all 20 tables. All five app migrations show applied in batch 1. Counts: usr_accounts 2, usr_roles 1, migrations 5, mod_modules 2, mod_module_actions 8; remaining domain tables are empty. Column metadata was inspected for all 20 tables. No account identities, password hashes, or business row values were queried. Live indexes and foreign keys were not re-queried; their definitions remain migration evidence, not fresh live constraint verification.
- DATABASE.md now records the 2026-10-25 verification date and scope. No app code, routes, migrations, or live rows changed. No tests were run; this was an audit.

### Gap analysis

Statuses reflect inspected code/configuration and migration evidence. A service without a route/controller is not a user-facing feature.

#### 1. Authentication and account activation

| Requirement | Current implementation evidence | Status | Gap or risk | Recommended next action |
| --- | --- | --- | --- | --- |
| Google Workspace and authorized credential sign-in | app/Config/Routes.php Google auth/callback and CSRF credential route; app/Controllers/Login.php google, googleCallback, credentials; app/Models/UserAccountModel.php identity/password methods | Partially Implemented | Both paths exist. Readiness requires active account and explicit active role only; module access is not required for /dashboard. Credential access has no code-level restriction to the approved account categories beyond active account/role and a stored password. No browser OAuth flow was verified. | Define eligible credential-login accounts and post-auth module access/landing behavior; test OAuth state, rate limiting, readiness, and session regeneration through isolated integration tests. |
| Pending/inactive/unconfigured denial | UserAccountModel::isLoginReady; AuthFilter::before; Login::googleCallback/credentials; tests/unit/LoginReadinessTest.php | Partially Implemented | Active account plus active role is checked and unit-tested. Activation UI/handler is absent; no module access check. | Preserve fail-closed readiness; decide whether module grant is required before normal use and add HTTP tests. |
| English UI/protected access | app/Views/auth/login.php, dashboard/temporary_landing.php; AuthFilter::before; Dashboard auth and POST CSRF in Routes.php | Partially Implemented | Inspected visible strings are English, but no English-only test exists. Dashboard is temporary. | Add route-level authorization regression and maintained-copy language checks. |

#### 2. Single active session per account

| Requirement | Current implementation evidence | Status | Gap or risk | Recommended next action |
| --- | --- | --- | --- | --- |
| Replacement confirmation, server enforcement, race safety and audit | app/Config/Session.php FileHandler, 7200-second expiry and ID regeneration; Login::credentials/googleCallback regenerate IDs; Login::logout destroys local session | Missing | No account-session registry, replacement confirmation/endpoint, concurrency lock, or login/logout/replacement audit. ID rotation is not per-account enforcement. | Define stale-session detection, atomic replacement, and audit failure behavior. Then propose store/hook and additive schema for approval; test concurrent login, cancel/replace, expiry/offline and old-session denial. |
| Cookie/HTTPS deployment safeguards | app/Config/Cookie.php HttpOnly/SameSite=Lax, secure=false; app/Config/App.php forced HTTPS false; app/Config/Routing.php auto routing disabled | Partially Implemented | Secure-cookie/HTTPS behavior depends on deployment configuration; production settings were not audited. | Review environment-specific HTTPS/cookie config during auth design; preserve TLS. |

#### 3. Module-specific action-level RBAC

| Requirement | Current implementation evidence | Status | Gap or risk | Recommended next action |
| --- | --- | --- | --- | --- |
| Module action availability and role/user grants | Migration 2026-10-23-000003_CreateModuleAuthorization.php; OutletAccessService::allowed; tests/unit/OutletAccessServiceTest.php::testRoleAndUserActionGrantsMustBothExist | Partially Implemented | Helper requires enabled action, active account/role, and both role/user grants, but is unrouted. Schema has action code/flags only, with no display/category/handler-policy metadata. Migration seeds rows disabled. | Define namespaced keys and trusted developer-maintained handler policy registry. Expose exact-action checks server-side before business routes; test grant intersection, unsupported/disabled actions and request-vs-approve separation. |
| Safe handler mapping and Superadmin boundary | mod_module_actions migration; OutletAccessService::allowed returns true for active Superadmin before module/action lookup | Partially Implemented | No trusted mapping exists. The broad helper bypass needs reconciliation with mandatory approval/security checks; currently no route calls it. | Define explicit Superadmin exceptions per operation and prohibit arbitrary executable metadata; add authorization tests. |

#### 4. Company/branch scope enforcement

| Requirement | Current implementation evidence | Status | Gap or risk | Recommended next action |
| --- | --- | --- | --- | --- |
| Module/user scope intersection on reads and writes | Migration 000003 scope tables; OutletAccessService::inScope, scopedOutlets, submit; tests/unit/OutletAccessServiceTest.php::testScopeRequiresModuleAndUserCompanyBranchIntersection | Partially Implemented | Helpers intersect allowlists/check active company and branch, but no business route proves list/detail/export/write enforcement. Live company/branch/grant tables are empty. | Enforce reusable scope filters on lists, details, reports, exports and mutations; test cross-scope and tampered IDs through HTTP. |
| Delegation cannot exceed actor scope | OutletAccessService::canApproveAccessScope | Partially Implemented | Unrouted utility; full effective permission/scope inheritance is not represented or request-tested. | Test grant/scope ceilings before User Management/delegation screens. |

#### 5. Reporting hierarchy and delegation

| Requirement | Current implementation evidence | Status | Gap or risk | Recommended next action |
| --- | --- | --- | --- | --- |
| Configurable reporting tree and handled employees | Migration 000002_CreateOrganizationPrerequisites.php: one unique employee-to-manager relation; OutletAccessService::submit reads immediate manager for hierarchy approval | Partially Implemented | No position/level, active flag, effective dates, handled-user relation, hierarchy UI/service, cycle validation, or hierarchical report access. Approval uses one immediate manager. | Decide manager history and handled-user semantics; add server-side traversal/cycle prevention and scope-limited tests before delegated administration. |

#### 6. Module Registry and Module Settings

| Requirement | Current implementation evidence | Status | Gap or risk | Recommended next action |
| --- | --- | --- | --- | --- |
| Safe registry lifecycle/settings UI | Migration 000001; ModuleRegistryService has search/pagination, create/update/status, parent-cycle checks and audit; tests/unit/ModuleRegistryServiceTest.php covers draft/cycle/activation/duplicate cases | Partially Implemented | No registry/settings controller, view, or route exists. Service has a static route allowlist but is unreachable; no screen persists action/scope/hierarchy/notification settings. | Restore after route/handler registry and auth boundaries are agreed; test CSRF, Superadmin boundaries and absent-handler activation. |
| Action/module live state | mod_module_actions migration; live counts show 2 modules and 8 actions | Partially Implemented | Current module status/action enabled values were not selected. Catalog rows do not prove handlers exist. | Inspect values only when planning activation; keep unmapped actions unavailable. |

#### 7. Approval workflow

| Requirement | Current implementation evidence | Status | Gap or risk | Recommended next action |
| --- | --- | --- | --- | --- |
| Staged approval and delayed apply | Migration 000004; OutletAccessService::submit, decide, apply; OutletAccessServiceTest covers sequential stages, rejection, missing/self approver and delayed apply | Partially Implemented | No Approval Queue route/controller/view. Schema modes are NONE/ADMIN/HIERARCHY; no Combined mode or returned/escalated states. Decision reads are not visibly locked against concurrent decisions; final apply should revalidate requester account/grants/scope. | Agree state/quorum; add locking/idempotency and final revalidation; test every mode, concurrency, self-approval, changed grants/scope and audit rollback. |
| Request and approve as distinct exact actions | OutletAccessService::allowed, submit, decide; approval uses APPROVE convention | Partially Implemented | No trusted action-handler mapping or workflow UI; Combined approval cannot be configured by current enum. | Define stable request/approve keys; enforce exact permissions at submission and decision. |

#### 8. Audit logs

| Requirement | Current implementation evidence | Status | Gap or risk | Recommended next action |
| --- | --- | --- | --- | --- |
| Sensitive event records without secrets | Migration 000001 log_audit_logs; ModuleRegistryService::audit, OutletAccessService::audit, SuperadminFilter::before denial event | Partially Implemented | No login/logout/session events, audit reader/export UI, retention policy, or common event contract. Schema has actor/module/event/result/before/after/time only; live audit row count is zero. | Define shared allowlisted audit service, transaction and retention/access rules; test redaction, denial logging and rollback if audit insertion fails. |

#### 9. UI consistency and responsive design

| Requirement | Current implementation evidence | Status | Gap or risk | Recommended next action |
| --- | --- | --- | --- | --- |
| Emerald primary, green-gradient buttons, cards/icons | public/assets/css/theme.css sets --pw-primary #0866e9; Login uses blue/Bootstrap styles; workspace has rounded cards, shadows and SVG icons; no green-gradient primary button found | Missing | Primary is blue; no shared emerald/green-gradient button system. Only Login and temporary Dashboard exist; no User Management reference to compare. | Confirm visual references, then update shared tokens/components in a separate UI task; test focus and contrast. |
| Responsive viewports | Login breakpoint 576px; workspace breakpoints 620px/1024px and ultrawide adjustments at 1700px/2300px | Partially Implemented | Responsive rules exist but no browser checks were performed. No Dashboard/User Management reference screenshots were found in app assets. | Test phone/tablet/laptop/desktop/ultrawide and record overflow, focus, contrast and touch target results. |
| English-only and consistency | Visible copy in Login, temporary Dashboard and workspace shell; app/Config/App.php supports English locale | Partially Implemented | Inspected strings are English, but no automated scan exists. Future database-driven labels are not constrained; Dashboard is temporary and User Management is absent. | Add maintained-copy language audit and compare reference screens once implemented. |

#### 10. Database readiness and testing

| Requirement | Current implementation evidence | Status | Gap or risk | Recommended next action |
| --- | --- | --- | --- | --- |
| Schema readiness/live definitions | Migrations 000001 through 000005; live migration status, table inventory and column metadata checked 2026-10-25 | Partially Implemented | Five migrations applied; 20 live tables and columns verified. No active-session store, notification queue, action-handler map, or access-preview persistence. Live indexes/FKs not re-queried; no schema writes. | Before migration, inspect live constraints/SHOW CREATE, reconcile approved design, prepare additive migration/backup/recovery plan, obtain approval. |
| Automated/security/regression tests | LoginReadinessTest.php, ModuleRegistryServiceTest.php, OutletAccessServiceTest.php, CI4 health/database/session examples | Partially Implemented | Unit tests cover readiness and isolated SQLite helpers. No HTTP authorization, migration consistency, login/session race, concurrent approval, OAuth E2E, English-only, or browser tests. Tests were not run during this audit. | Add isolated HTTP and concurrency tests by phase; run PHPUnit/routes checks and browser review during implementation. |

### Safe implementation order (recommendation; this audit authorizes no implementation)

| Phase | Dependencies and work | Migration/configuration needs | Required tests |
| --- | --- | --- | --- |
| 0. Confirm foundations | Decide whether an active module grant is required for Dashboard; confirm login methods and production HTTPS settings. | No schema change. | Readiness regression; protected Dashboard and CSRF logout HTTP tests; OAuth state tests. |
| 1. Session security | Design account session lifecycle before business actions; preserve identity-provider checks. | Evaluate registry/lease or equivalent, file-session coupling, expiry cleanup and atomic locking. Owner approval before auth/schema changes. | Concurrent sign-in, cancel/replace, old-session denial, expiry/offline, account recheck, audit failure, no Google global logout. |
| 2. Authorization/action registry | Exact-action checks before screens; separate module availability from role/user grants. | Review whether display/category/handler metadata needs additive migration; values remain non-executable. | Active/inactive account and role; action denial; grant intersection; disabled/unmapped action; Superadmin exception; direct URL/POST; request vs approve. |
| 3. Scope/hierarchy | Reusable scope before User Management/business data; hierarchy informs visibility/approval, never grants module access. | Existing module/user company/branch tables and one Reports To relation exist; add only approved semantic gaps. | Lists/details/reports/exports/writes; inactive parents; delegation ceiling; cycle and report visibility. |
| 4. Approval/audit | Build after action/scope checks; revalidate approvers and atomically apply protected changes with audit. | Current enum lacks Combined and requested return/escalation states; decide state/quorum/audit semantics before additive migration. | Every approval path, self-approval, missing approver, changed grant/scope, concurrent decision, audit-failure rollback. |
| 5. Registry/User Management | Add screens after authorization, scope, approval and audit contracts. | Existing module/action tables are partial; add metadata only after schema review; routes remain developer-maintained. | CSRF/role boundaries, handler validation, lifecycle, parent cycles, access preview and audit. |
| 6. First business slice | Implement one approved module after shared foundations. | out_outlets exists; migrate only for an approved verified gap. | Scope, approval, audit atomicity, ID tampering, archive/restore and regression. |
| 7. UI/acceptance | Apply emerald design consistently and compare with approved references. | No schema change for visual work. | English-only, keyboard/focus/contrast, phone/tablet/laptop/desktop/ultrawide browser review. |

### Files inspected

- Docs read: AGENTS.md, PROJECT_BLUEPRINT.md, DATABASE.md, README.md, PROGRESS.md.
- Config read: app/Config/Routes.php, Filters.php, Routing.php, Session.php, Cookie.php, Security.php, App.php, Database.php, Migrations.php, Services.php, Events.php, Autoload.php.
- Application source read: app/Controllers/Login.php, Dashboard.php, Home.php; app/Models/UserAccountModel.php; app/Filters/AuthFilter.php, SuperadminFilter.php; app/Services/ModuleRegistryService.php, OutletAccessService.php; app/Views/auth/login.php, dashboard/temporary_landing.php, layouts/workspace.php; public/assets/css/theme.css. The remaining controller/model/filter/service/view file paths were inventoried; error templates were string-scanned but not individually opened.
- Database artifacts read: app/Database/Migrations/2026-10-23-000001_CreateModuleRegistry.php, 000002_CreateOrganizationPrerequisites.php, 000003_CreateModuleAuthorization.php, 000004_CreateApprovalWorkflow.php, 000005_CreateOutletList.php; test-only tests/_support/Database/Migrations/2020-02-22-222222_example_migration.php.
- Test files read: tests/unit/LoginReadinessTest.php, ModuleRegistryServiceTest.php, OutletAccessServiceTest.php, HealthTest.php. Other database/session test paths and shared fixture files were inventoried, not individually opened.
- No SETUP.md exists. .env values were not read; no personal/account/business row values were selected.

### Exact next task

Review Phases 0 and 1, especially whether active module access is required for Dashboard and the stale/expiry/concurrency policy for single-session login. Prepare a concrete authentication/session proposal (files, store/schema choice, locking, recovery plan, tests) for owner approval before changing authentication, routes, schema or session behavior.

## Phase 1 authentication implementation checkpoint (2026-10-25)

### Current implementation and live-schema recheck

- Read-only recheck succeeded: `php spark migrate:status` reports the five application migrations applied; `php spark db:table --show` reports the same 20-table inventory, including two accounts and one role. Metadata-only checks confirmed `usr_accounts` has `user_id`, nullable `role_id`, `account_status`, and legacy nullable `password_hash`; `usr_roles` has an active/inactive `status`; and `log_audit_logs` has `actor_user_id`, `event_code`, `result`, before/after payloads, and timestamp. No account identities, hashes, tokens, or other row values were queried.
- Current code still exposes both Google OAuth and username/password login (`Routes.php`, `Login::credentials`, and the login view). Google OAuth uses environment configuration and is fail-closed when required configuration is missing. No account-level active-session registry exists. CI4 uses FileHandler with a 7,200-second expiry; `AuthFilter` rechecks active account and active role but does not verify module access or session ownership.
- Product docs conflict with this task on login policy: `PROJECT_BLUEPRINT.md` still specifies a secondary credential path; this task explicitly directs Google Workspace-only login. Follow the current task if implementation is approved and reconcile the blueprint in the same approved change.
- Access readiness needs to include the blueprint's active module assignment requirement before normal use. Superadmin's documented exception to ordinary module assignments should remain. Current live inventory reports zero `rbac_user_module_actions` rows; do not assume a non-Superadmin account can pass readiness based on existing grants. The dashboard currently has no business-module route.

### Proposed implementation requiring confirmation under AGENTS.md

- Remove the credential login route, controller handler, form, and password-verification methods from the login path while preserving existing account columns and data. Keep Google OAuth fail-closed; do not invent OAuth settings or persist provider credentials/tokens.
- Add an additive `sec_active_sessions` table with `user_id` as primary/foreign key, a fixed-length SHA-256 hash of the application session ID (never the raw ID), and `expires_at`, `last_seen_at`, and creation/update timestamps. Use InnoDB and a foreign key to `usr_accounts.user_id`; no user/account rows would be backfilled. Do not execute this migration against live MySQL without separate migration approval.
- Implement a reusable active-session service. Serialize login/replacement per account using an InnoDB transaction and a locked `usr_accounts` row; recheck activation, role and access readiness while holding that lock; prompt only for a non-expired registry lease; Cancel leaves the registry untouched; replacement atomically swaps the hash; AuthFilter rejects old hashes and expired leases; logout conditionally revokes only the caller's hash. The lease uses the current two-hour idle expiry. The flow does not call Google logout or revoke provider sessions.
- Add a CSRF-protected replacement/cancel flow with the exact approved English prompt, and audit login denial/success, replacement and logout using `log_audit_logs`; never include a session ID, OAuth token, or credential in logs.
- In-flight requests that already passed authentication cannot be retroactively stopped by registry replacement; any future mutating handler must perform authorization/session revalidation at its transaction boundary.

### Planned files and validation after approval

- Likely application files: `app/Config/Routes.php`, `app/Controllers/Login.php`, `app/Filters/AuthFilter.php`, `app/Models/UserAccountModel.php`, `app/Views/auth/login.php`, plus new active-session and audit service code and an additive migration. Update `PROJECT_BLUEPRINT.md` to resolve the Google-only policy conflict; update `DATABASE.md` with the migration definition and live verification boundary. Back up affected files before editing.
- Add tests for active/inactive account and role, module-access readiness including Superadmin exception, cancel/replace, stale/expired lease, old-session rejection, concurrent replacements, audit failures, and protected-route denial. Use isolated test databases/fixtures; do not run migrations or mutate live data.
- Exact next step: confirm the additive session table and transactional replacement contract, then implement incrementally. No application files or database schema/data were changed at this checkpoint; no tests were run.
