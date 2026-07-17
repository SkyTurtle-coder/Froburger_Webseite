# Execution Plan

Last updated: 2026-07-17

## Objective

Migrate the static AV Froburger website into a secure Django application with:

- public site
- members area
- member profiles
- protected document handling
- public and private media handling
- events and news management
- custom CMS for the Web-Aktuar role
- role and permission model
- audit logging
- production-ready operational structure

## Current status summary

- Phase 0: completed
- Phase 1: completed
- Phase 2: completed
- Phase 3: completed
- Phases 4-14: not started
- Current repository baseline: Django now serves the public pages and the authentication foundation; remaining work shifts to structured content, roles, protected data, CMS, and operations

## Consolidated Phase-0 findings

- Shared header, navigation, footer, metadata, and legal links are manually duplicated across the current HTML files.
- Event truth currently exists in four places: event cards, `data-*` attributes, client-side month rendering, and `kalender.ics`.
- Public member information is already duplicated across sections and will need normalized structured models before any private member area is added.
- Legal/privacy content is intentionally incomplete and must remain visibly marked as `TODO` until verified.
- External Google Fonts are a current privacy/compliance issue and should be removed during migration if a lawful alternative can be used.
- `robots.txt` and `noindex` are not security controls and must not be treated as access protection for any future private area.

## Phase 0 - Analysis and architecture

Status: completed

Tasks:

- [x] Create feature branch
- [x] Audit repository documentation
- [x] Audit HTML, CSS, JavaScript, assets, metadata, and URL structure
- [x] Identify SEO, accessibility, privacy, and performance risks
- [x] Run specialized review agents for frontend, architecture, security, and CMS UX
- [x] Create architecture, security, role, data-classification, migration, and ADR documents
- [x] Consolidate subagent findings into final Phase 0 updates
- [x] Commit Phase 0

Acceptance criteria:

- current system documented
- target architecture documented
- risks documented
- no public functionality changed

Validation:

- documentation review
- `git diff --check`

## Phase 1 - Django foundation

Status: completed

Tasks:

- initialize Django project skeleton
- add dependency management
- add split settings
- add `.env.example`
- add PostgreSQL development setup via `compose.yaml`
- configure Ruff and pytest
- verify `uv run python manage.py check`

Completed notes:

- local `.venv` bootstrapped with Python 3.12 via `uv`
- Django 5.2 foundation initialized
- settings split into `base`, `development`, `test`, and `production`
- app namespace and core health endpoint created
- requirements, `pyproject.toml`, `.env.example`, and `compose.yaml` added
- baseline checks passing

Dependencies:

- Phase 0 architecture decisions
- usable Python toolchain via `uv`

Acceptance criteria:

- Django starts locally
- no secrets committed
- baseline checks runnable

Validation:

- `uv run python manage.py check`
- `uv run python manage.py makemigrations --check`
- `uv run pytest`
- `uv run ruff check .`

## Phase 2 - Accounts and authentication

Status: completed

Tasks:

- [x] implement custom user model
- [x] use email-based authentication
- [x] implement login, logout, password reset, password change
- [x] implement invite-only onboarding
- [x] configure Django admin for technical admins
- [x] add auth tests

Dependencies:

- Phase 1 complete before first real app migrations

Risks:

- choosing wrong user model timing would force painful migration later

Completed notes:

- custom `accounts.User` model introduced before further business-data migrations
- authentication now uses email addresses instead of usernames
- login, logout, password reset, password change, and protected account landing page are wired through Django auth views
- invite-only onboarding implemented with hashed one-time tokens, expiry handling, activation on acceptance, and audit logging
- Django admin now supports the custom user model and exposes read-only audit entries
- auth regression tests cover login, logout, inactive users, password reset, password change, and invitation acceptance

Acceptance criteria:

- users can securely sign in and sign out
- password reset works with the development email backend
- no public self-registration exists
- custom user model is in place before later domain migrations
- tests for login, logout, password reset, inactive users, and invitations are passing

Validation:

- `uv run python manage.py check`
- `uv run python manage.py makemigrations --check`
- `uv run pytest`
- `uv run ruff check .`
- `git diff --check`

## Phase 3 - Public site migration to Django templates

Status: completed

Tasks:

- [x] migrate current static pages into templates
- [x] extract reusable head, navigation, messages, and footer components
- [x] move assets to Django staticfiles
- [x] define canonical slash URLs
- [x] redirect old `.html` URLs
- [x] replace external fonts if legally safe

Dependencies:

- Phase 1 complete

Completed notes:

- created a public `templates/base.html` plus shared navigation, footer, and messages components
- moved public HTML pages into Django templates under `templates/public/pages/`
- moved legacy assets into Django `static/` and removed the old root-level public HTML source files
- public routes now use canonical slash URLs with permanent redirects from legacy `.html` paths
- added Django-served `robots.txt`, `sitemap.xml`, and the temporary legacy `kalender.ics` endpoint
- removed external Google Fonts from the public base template and switched to local fallback font stacks
- added public-route smoke tests for anonymous access, redirects, sitemap, robots, and ICS delivery

Acceptance criteria:

- current look and navigation preserved
- no second public source of truth
- slash URLs canonical

Validation:

- `uv run python manage.py check`
- `uv run python manage.py makemigrations --check`
- `uv run pytest`
- `uv run ruff check .`
- `git diff --check`

## Phase 4 - Member profiles and role model

Status: pending

Tasks:

- separate account and member profile
- add statuses and visibility model
- add role/group bootstrap
- restrict access by server-side permissions

## Phase 5 - Protected documents

Status: pending

Tasks:

- protected document model
- private storage path
- secure upload validation
- secure download authorization
- versioning and metadata

## Phase 6 - Media library

Status: pending

Tasks:

- public and private media separation
- safe image handling and metadata
- alt-text workflow
- derivative generation where needed

## Phase 7 - Custom CMS

Status: pending

Tasks:

- custom page model
- structured blocks
- preview and publish workflow
- navigation management
- versioning and restore

## Phase 8 - News and events

Status: pending

Tasks:

- migrate `aktuelles` content into structured news entries
- migrate events from `anlaesse`
- preserve list and month views
- add per-event and feed ICS endpoints

## Phase 9 - Security and privacy hardening

Status: pending

Tasks:

- audit trail
- rate limiting
- 2FA preparation or integration
- secure cookies and headers
- threat model refresh
- data minimization and retention guidance

## Phase 10 - Tests and QA

Status: pending

Tasks:

- model, form, view, template, authorization, upload, download, audit, ICS, and smoke tests
- coverage report
- frontend manual and automated checks where toolchain permits

## Phase 11 - CI

Status: pending

Tasks:

- GitHub Actions workflow
- PostgreSQL-backed test job
- lint, checks, migrations, tests, coverage

## Phase 12 - Production preparation

Status: pending

Tasks:

- Gunicorn example config
- systemd units
- Nginx example config
- backup and restore docs
- healthcheck
- deployment runbook

## Phase 13 - Final documentation

Status: pending

Tasks:

- refresh README and operational docs
- add CMS and member-admin guides
- document setup, incident response, backup and restore

## Phase 14 - Final multi-review and fix loop

Status: pending

Tasks:

- security review
- Django architecture review
- frontend review
- CMS workflow review
- members area review
- fix critical and high findings

## Main risks

- legal content is intentionally incomplete and must stay marked as `TODO`
- no live production/server parameters are available yet
- private documents and member data must never be modeled as public static assets
- public content is still hard-coded and duplicated structurally, so later CMS and data migrations must avoid reintroducing multiple sources of truth

## Open blockers

- no verified legal and hosting facts for final legal/privacy pages
- no production infrastructure details for a real deployment

Tooling note:

- `uv` is available locally
- no system Python interpreter is currently available on PATH outside Windows Store aliasing
