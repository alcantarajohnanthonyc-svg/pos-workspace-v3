# GitHub Copilot Instructions — POS Workspace V3

Follow the shared project rules in `AGENTS.md`. Before planning changes, read `PROGRESS.md`, then consult the relevant sections of `PROJECT_BLUEPRINT.md` and `DATABASE.md`.

- Inspect existing source before proposing or making edits; preserve the CodeIgniter 4 architecture and working behavior.
- Keep all POS Workspace V3 system UI text in English.
- Separate source-confirmed facts from owner-confirmed requirements, proposed designs, and unresolved decisions. Do not present planned features as implemented.
- Do not infer database schema from code references or product requirements. `DATABASE.md` states whether live schema has actually been inspected.
- Do not change database schema, authentication behavior, routes, or security-sensitive code without explaining the proposed change and obtaining project owner approval.
- Preserve user changes. Never expose or commit `.env`, credentials, API keys, OAuth tokens, or database secrets.
- Run relevant tests when available and report actual results honestly. Update `PROGRESS.md` at the end of coding tasks and follow the documentation update rules in `AGENTS.md`.
