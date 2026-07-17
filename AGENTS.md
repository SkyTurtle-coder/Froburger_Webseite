# AGENTS.md

This repository is being migrated from a static prototype to a secure Django application with a custom CMS and members area.

## Non-negotiable rules

- Do not commit secrets, `.env` files, private keys, production backups, private uploads, or real member data.
- Use Swiss German orthography for German UI and German documentation.
- Keep public and private files strictly separated.
- Enforce permissions server-side. Hidden buttons are never sufficient protection.
- Preserve the existing visual design and information architecture unless there is a documented reason to change them.
- Do not invent legal facts, addresses, responsible persons, hosting details, or privacy disclosures. Mark missing items as `TODO`.
- Model changes require Django migrations.
- New features require tests.
- Security-sensitive changes require focused authorization tests.
- Before declaring work complete, run the full documented quality checks.

## Target architecture

- Backend: Django modular monolith
- Database: PostgreSQL
- Templating: Django templates
- Styling: existing CSS design system, migrated and preserved
- Frontend behavior: existing vanilla JavaScript, migrated and reduced only when server-side behavior clearly replaces it
- Public static assets: Django staticfiles
- Private files: protected delivery flow, never directly public

## Working conventions

- Work on feature branches only. Never commit directly to `main`.
- Keep the project root as the single source of truth during migration.
- Prefer small, reviewable commits per completed phase.
- Document every substantial architectural decision under `docs/ADR/`.
- Update `docs/EXECUTION_PLAN.md` and `docs/MIGRATION_STATUS.md` when a phase materially changes state.

## Preferred local commands

Use `uv` for local Python bootstrapping where possible.

Expected validation commands during the Django phases:

```text
uv run python manage.py check
uv run python manage.py check --deploy
uv run python manage.py makemigrations --check
uv run pytest
uv run ruff check .
git diff --check
```

## Repository safety notes

- The repository is public.
- Only use clearly fictional seed data.
- Keep legal placeholders visible until verified information is available.
- Remove external Google Fonts during migration if a lawful local-hosted or system-font replacement can be used.
