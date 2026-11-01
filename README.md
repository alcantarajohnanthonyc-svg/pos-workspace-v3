# POS Workspace V3

POS Workspace V3 is an existing CodeIgniter 4 application built with PHP and MySQL/MariaDB for POS support administration and masterfile/report workflows. Product requirements, confirmed implementation facts, proposals, and open decisions are maintained separately in [PROJECT_BLUEPRINT.md](PROJECT_BLUEPRINT.md), [DATABASE.md](DATABASE.md), and [PROGRESS.md](PROGRESS.md).

## Requirements

- PHP 8.2 or later with `intl`, `mbstring`, `json`, and `mysqlnd` when using MySQL.
- Composer dependencies installed from `composer.lock`.
- MySQL or MariaDB configured through the local environment file.

## Local setup

1. Install dependencies with `composer install`.
2. Copy `env` to `.env` and set the local base URL, database connection, and any required OAuth configuration. Keep `.env` private; never commit it or copy secret values into documentation or logs.
3. Configure Google OAuth using private environment values for `google.clientId`, `google.clientSecret`, and `google.redirectUri`. The redirect URI must exactly match the deployed `/login/google/callback` URL.
4. Set `google.allowedDomains` in `.env` to a comma-separated list of owner-approved Workspace email domains. Replace the safe placeholder copied from `env` with the actual domain(s) approved by the organization; the placeholder is not a configured project domain:

   ```ini
   google.allowedDomains = "your-workspace-domain.example"
   ```

   Add a subdomain as a separate entry only if it is independently approved. Entries use exact matching; wildcards are invalid. Missing, empty, or malformed configuration blocks Google sign-in, credential sign-in, and protected workspace access. Keep `.env` private and do not put OAuth secrets in documentation, source control, or logs.
5. Configure Apache/XAMPP to serve the project `public/` directory, not the project root.
6. Review `DATABASE.md` and the migration files before any database operation. Do not run migrations, seeders, or destructive database commands against live data without explicit approval.
7. Visit the configured local application URL. Google Workspace is the primary sign-in option. POS Workspace Credentials is secondary and only works for an account with an administrator-provisioned unique username and a valid PHP password hash. This project does not provide password enrollment or reset; never enter a Google password here. Credentials sign-in also requires an approved email domain, active account and role, application access readiness, and an available POS session.

Existing account rows are not modified or deleted when the allowlist changes. Accounts whose stored email domain is not approved are denied at Google sign-in and on their next protected request; their POS session is revoked when that request reaches the application. Review the allowlist before deployment to avoid unintentionally blocking existing users.

See [SETUP.md](SETUP.md) for local installation and database-safety guidance. Do not assume that documented requirements are implemented screens or workflows. The current application routes and exact next task are recorded in `PROGRESS.md`.

## Project references

- [Project blueprint](PROJECT_BLUEPRINT.md): product requirements, proposed design, screen specifications, and roadmap.
- [Database documentation](DATABASE.md): migration-defined schema and dated live verification evidence.
- [Progress log](PROGRESS.md): completed work, checks performed, limitations, and exact next step.
- [Shared coding instructions](AGENTS.md): mandatory workflow and safety constraints for coding assistants.
- [Local setup guide](SETUP.md): local environment, database, and automated test setup guidance.

## Tests

Run the existing automated suite with `vendor/bin/phpunit --no-coverage` when test execution is part of the authorized task. Record the actual result; a test command being available is not evidence that it has passed.
