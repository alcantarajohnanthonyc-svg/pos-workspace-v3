# Shared AI Instructions — POS Workspace V3

These instructions apply to Codex, GitHub Copilot, Claude, Gemini, and other AI coding assistants working in this repository. The project documents are the shared source of truth across tools; chat history is not automatically shared or remembered.

## Mandatory workflow for every coding task

1. Read `AGENTS.md` and `PROGRESS.md` before planning changes.
2. Read the relevant sections of `PROJECT_BLUEPRINT.md` and `DATABASE.md`.
3. Inspect existing code before modifying it. Reuse existing shared functions and established CodeIgniter 4 patterns where appropriate.
4. Before risky or broad changes, explain the plan and expected files to change.
5. Do not change database schema, authentication behavior, routes, or security-sensitive code without explaining the proposed change and obtaining the project owner's approval.
6. Never replace working features merely to match an unverified design. Preserve user changes and existing behavior unless an approved task requires a change.
7. Run relevant tests when available and report only actual results. Do not imply that unrun tests passed.
8. At the end of each coding session, update `PROGRESS.md`. Update `PROJECT_BLUEPRINT.md` only when requirements or design decisions change, `DATABASE.md` only when schema details or confirmed database decisions change, and `AGENTS.md` only when shared rules change.
9. Record the exact next step in `PROGRESS.md` so another assistant can resume.
10. Never put passwords, API keys, OAuth tokens, database credentials, or other secrets in documentation, source examples, or logs.

## Project conventions and safety

- Technology: CodeIgniter 4, PHP, MySQL, and XAMPP. Preserve the existing CI4 architecture and current working code.
- All POS Workspace V3 system UI text must always be in English. This applies to new and changed screens and text exposed to end users.
- Keep **confirmed project facts**, **confirmed product requirements**, **proposed designs**, and **unresolved decisions** distinct. A requirement is not proof of implementation.
- `DATABASE.md` must describe only schema verifiable from migrations or other authoritative schema artifacts. Source-code queries and a requested database name do not verify tables, columns, keys, or relationships. State clearly when live MySQL was not inspected.
- Make the smallest change that fulfills the approved task. Validate and escape untrusted input and output, use parameterized database operations, and enforce authorization server-side.
- Treat authentication, OAuth, sessions, authorization, routes, and migrations as security-sensitive. Do not weaken TLS or other security controls for convenience.
- Do not run migrations, seeders, destructive database commands, or operations that alter live data unless specifically approved.
- Do not read or disclose secret values. Do not commit `.env`, credentials, or secret-bearing files.
- Inspect whether Git is initialized before making Git changes. Never discard user changes. Review changes and recommend meaningful commits; do not commit unless asked.
- Do not claim a live database schema, feature, or test result is verified without actually verifying it.

## Shared context for Gemini and other tools

No Gemini-specific integration is assumed. When using Gemini, attach or paste `AGENTS.md`, `PROGRESS.md`, and the relevant sections of `PROJECT_BLUEPRINT.md` and `DATABASE.md` as context, then ask it to follow this workflow. Provide current source files as needed; do not assume Gemini can access this workspace or prior chats automatically. The same approach applies to any assistant that does not automatically load repository instructions.
