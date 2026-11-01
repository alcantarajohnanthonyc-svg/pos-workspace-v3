# POS Workspace V3 Setup

This guide describes a safe local CodeIgniter 4 setup. It does not imply that the live database has every pending migration or that OAuth is configured.

## Requirements

- PHP 8.2 or later with `intl`, `mbstring`, `json`, and `mysqlnd` for MySQL/MariaDB.
- Composer and the dependencies pinned by `composer.lock`.
- Apache and MySQL/MariaDB through XAMPP, or an equivalent local development environment.
- A local database restored from an authorized development baseline. The application migration history assumes the existing `usr_accounts` and `usr_roles` base tables; the application migrations do not create those legacy tables.

## Install the application

1. Open a terminal in the project directory and run `composer install` if `vendor/` is not already populated.
2. Copy the tracked `env` template to a private `.env` file. Set a local `app.baseURL` and the MySQLi host, port, database name, username, and password for the isolated development database. Do not copy these values into source files, documentation, screenshots, or logs.
3. Configure Google OAuth privately if sign-in is needed. Set `google.clientId`, `google.clientSecret`, and the exact `google.redirectUri` for the local callback. Set `google.allowedDomains` to the organization-approved Workspace email domains. Missing or invalid domain configuration intentionally fails closed. Do not use a production client secret in an untrusted environment.
4. Configure Apache's document root or virtual host to serve the project's `public/` directory. Do not expose the repository root. Ensure the web-server account can write only to the CodeIgniter `writable/` directory as required.
5. Confirm that the database is an isolated development database and that its base account/role schema is compatible with [DATABASE.md](DATABASE.md). Never point a fresh setup command at an operational database by assumption.
6. Check migration state with `php spark migrate:status`. Review every pending migration and its compatibility requirements before applying anything. Migration `2026-10-25-000008_AddUserManagementLookupsAndAuditTargets` is pending for the configured database; it requires an isolated restore test and verified backup/recovery plan before deployment. Do not use `php spark migrate:refresh`, `rollback`, or destructive setup commands on existing data.
7. Start Apache/MySQL and visit the configured local URL. For a temporary development server, `php spark serve` is available; do not use it as a production deployment server.

## Isolated MariaDB preflight for migration 000008

Do not run the migration until all preconditions below are met:

- Use a dedicated MariaDB server with its own newly initialized, empty data directory, bound only to loopback on a port not used by another server. A separate database name on a shared server is not proof of isolation.
- The currently available XAMPP server uses the shared `D:\XAMPP\mysql\data` directory, which contains unrelated databases. Do not use it for this preflight or connect to it for scratch work.
- The documented 2026-10-23 backup predates later application migrations and is not a verified post-000007 restore. Its SQL explicitly creates and selects the database `pos_workspace_v3` and contains `DROP TABLE` statements for `migrations`, `usr_accounts`, and `usr_roles`. Therefore, **do not import it into the shared XAMPP server**, even if a different database is supplied to the client. Import it only into a dedicated disposable MariaDB server whose data directory contains no operational data.
- Have a verified recovery copy and confirm that the baseline contains the required legacy `usr_accounts` and `usr_roles` tables.
- Use a disposable source copy for migration testing. Never disable or move migration files in the active project directory.

Provision the dedicated server using the operator-approved MariaDB installation procedure. Do not point its data directory at `D:\XAMPP\mysql\data`, copy that shared data directory, or stop/reconfigure the shared XAMPP server. Start only the dedicated instance on its confirmed loopback port. The following checks and import are templates for that already-isolated instance; `-p` prompts for the account password:

This workspace has `mysql_install_db.exe` and `mysqld.exe` under `D:\XAMPP\mysql\bin`. The preflight run recorded in `DATABASE.md` used a new, session-private datadir and loopback port 3310; that server and its disposable application copy were stopped and removed after verification. For a future scratch run, select a new absolute scratch path and an unused port, confirm the path does not exist, initialize only that path, and start the server in a separate terminal:

```powershell
$scratchRoot = 'C:\path\to\private\mariadb-preflight'
if (Test-Path $scratchRoot) { throw 'Refusing to reuse an existing datadir.' }
New-Item -ItemType Directory -Path $scratchRoot | Out-Null
& 'D:\XAMPP\mysql\bin\mysql_install_db.exe' --datadir="$scratchRoot\data" --port=3310
```

```powershell
$scratchData = 'C:\path\to\private\mariadb-preflight\data'
& 'D:\XAMPP\mysql\bin\mysqld.exe' --defaults-file="$scratchData\my.ini" --basedir='D:\XAMPP\mysql' --datadir=$scratchData --port=3310 --bind-address=127.0.0.1 --console
```

Keep this temporary server restricted to loopback and stop it after verification. Do not reuse an existing datadir or leave the scratch server running when the preflight is finished.

```powershell
$mysql = 'D:\XAMPP\mysql\bin\mysql.exe'
$hostName = '127.0.0.1'
$port = 3310
$scratchUser = 'SCRATCH_USER'
$baseline = 'D:\XAMPP\htdocs\pos-workspace-v3\writable\database-backups\pos_workspace_v3_pre_outlet_access_20261023_145930.sql'

& $mysql --host=$hostName --port=$port --user=$scratchUser -p --execute='SELECT @@hostname AS server_host, @@port AS server_port, @@datadir AS server_data_directory, VERSION() AS server_version;'
```

Do not continue unless the returned data directory and port match the dedicated scratch instance and are distinct from the shared XAMPP server. The dump selects its own fixed `pos_workspace_v3` database, so restore without a client-side database argument. Run this only after the empty dedicated instance is verified:

```powershell
& $env:ComSpec /c "`"$mysql`" --host=$hostName --port=$port --user=$scratchUser -p < `"$baseline`""
```

In the disposable application copy, point the CodeIgniter `database.default` settings to that same dedicated server, port, and `pos_workspace_v3` database using process-scoped environment variables. Enter credentials through a secure prompt; do not copy them into this guide:

```powershell
$credential = Get-Credential -UserName $scratchUser -Message 'Scratch database credentials'
${env:database.default.hostname} = $hostName
${env:database.default.port} = "$port"
${env:database.default.database} = 'pos_workspace_v3'
${env:database.default.username} = $credential.UserName
${env:database.default.password} = $credential.GetNetworkCredential().Password
```

Run the read-only status and schema checks. First verify the configured connection resolves to the dedicated server and `pos_workspace_v3`; confirm which migrations the restored baseline actually records rather than assuming it is post-000007. Record these aggregate compatibility values without selecting account rows:

```powershell
php spark migrate:status
php spark db:table usr_accounts --metadata
php spark db:table usr_roles --metadata
& $mysql --host=$hostName --port=$port --user=$scratchUser -p --database=pos_workspace_v3 --execute='SELECT COUNT(*) AS accounts_with_position FROM usr_accounts WHERE position_id IS NOT NULL;'
```

Verify the server identity again and ensure that `accounts_with_position` is zero or that every existing reference is compatible with the proposed lookup FK. If the restored schema does not have `position_id`, inspect the authoritative source schema and migration plan before deciding whether this baseline can validate migration 000008. Never create a synthetic post-000007 state by changing migration records.

Use a disposable application copy with its own migrations directory and private process-scoped database configuration. In that copy only, temporarily exclude migration 000008 while testing the earlier migrations; do not move or edit migration files in the active project. Run pending migrations 000001–000007, as indicated by the restored migration table, and verify migration status and schema. If any earlier migration is unexpectedly missing or inconsistent, stop and record the blocker rather than inventing migration history.

```powershell
php spark migrate
php spark migrate:status
php spark db:table log_audit_logs --metadata
& $mysql --host=$hostName --port=$port --user=$scratchUser -p --database=pos_workspace_v3 --execute='SELECT COUNT(*) AS audit_rows_before_000008 FROM log_audit_logs;'
```

After 000001–000007 are verified in the scratch copy, restore migration 000008 to that disposable copy and apply it there:

```powershell
php spark migrate
php spark migrate:status
php spark db:table org_positions --metadata
php spark db:table org_departments --metadata
php spark db:table usr_accounts --metadata
php spark db:table log_audit_logs --metadata
& $mysql --host=$hostName --port=$port --user=$scratchUser -p --database=pos_workspace_v3 --execute='SELECT COUNT(*) AS orphan_position_references FROM usr_accounts a LEFT JOIN org_positions p ON p.position_id = a.position_id WHERE a.position_id IS NOT NULL AND p.position_id IS NULL;'
& $mysql --host=$hostName --port=$port --user=$scratchUser -p --database=pos_workspace_v3 --execute='SELECT COUNT(*) AS audit_rows_after_000008 FROM log_audit_logs;'
```

Compare before/after aggregate counts and verify the new columns, indexes, and foreign keys with schema-only metadata or `information_schema`; the orphan-position count must be zero and the audit row count must be unchanged. Do not select account identities, password hashes, audit payloads, or session values. Record the actual target identity, migration batches, compatibility results, and restore/recovery outcome in `PROGRESS.md` and `DATABASE.md`. The 2026-10-25 isolated scratch test completed successfully as documented in `DATABASE.md`; it does not replace current operational schema/backup verification and does not authorize migration 000008 on the configured database.

## Accounts and sign-in

Google Workspace is the primary sign-in path. Credential sign-in is available only to an already provisioned account with an administrator-managed username and a compatible password hash; the application does not provide password enrollment or reset. Never use a Google password in the credential form. An account also needs an active role and usable application access before protected routes are available.

Do not add sample accounts or seed data to a shared or operational database. Use isolated fixtures or a disposable restored database for development and automated tests.

## Automated checks

Run the isolated test suite from the repository root:

```powershell
vendor/bin/phpunit --no-coverage --no-logging
```

The current tests use isolated SQLite fixtures for application logic. They do not verify live MariaDB migration behavior, configured OAuth, authenticated browser flows, or responsive rendering.

Comprehensive live browser testing is deferred until all project phases are implemented. Track outcomes and limitations in [PROGRESS.md](PROGRESS.md).

## Project references

- [PROJECT_BLUEPRINT.md](PROJECT_BLUEPRINT.md) — confirmed requirements, proposals, and roadmap.
- [DATABASE.md](DATABASE.md) — migration-defined schema and dated live-schema evidence.
- [PROGRESS.md](PROGRESS.md) — implementation status, verification, limitations, and exact next step.
- [AGENTS.md](AGENTS.md) — shared development workflow and safety rules.
