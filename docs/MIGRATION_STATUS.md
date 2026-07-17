# Migration Status

Last updated: 2026-07-17

## Overall status

The repository now contains the Django foundation and authentication baseline on the feature branch. The public website itself is still served from legacy static source files until Phase 3 migrates those pages into Django templates.

## Current repository baseline

- branch: `feature/django-cms-members-area`
- public static site present
- Django project bootstrap present
- custom user model and account onboarding present
- protected account entry point present
- audit trail baseline present for invitations
- no CMS yet
- Phase-0 review consensus still applies: centralize templates first, then normalize structured content, then expand CMS and private area

## Current public pages

- `index.html`
- `aktuelles.html`
- `anlaesse.html`
- `mitglieder.html`
- `mitglied-werden.html`
- `ueber-uns.html`
- `intern.html`
- `impressum.html`
- `datenschutz.html`

## Phase tracking

| Phase | Status | Notes |
| --- | --- | --- |
| 0 - Analysis and architecture | completed | branch created, repo analyzed, architecture/security docs committed |
| 1 - Django foundation | completed | `.venv`, Django project, split settings, requirements, compose, and smoke tests are in place |
| 2 - Accounts/auth | completed | custom user model, email auth, invite-only onboarding, auth templates, admin wiring, migrations, and tests are in place |
| 3 - Public-site migration | not started | legacy static HTML remains the public source until template extraction and URL redirects are implemented |
| 4 - Members and roles | not started | requires account foundation |
| 5 - Documents | not started | requires private storage design |
| 6 - Media library | not started | requires public/private separation |
| 7 - CMS | not started | depends on content and roles |
| 8 - News/events | not started | existing static content available as migration source |
| 9 - Security/privacy hardening | not started | threat model drafted, implementation pending |
| 10 - Tests/QA | not started | no Python test stack yet |
| 11 - CI | not started | depends on project bootstrap |
| 12 - Production prep | not started | blocked on real server details |
| 13 - Documentation finalization | not started | current docs are planning-level |
| 14 - Final review loop | not started | depends on completed implementation |

## Known open external-information TODOs

- responsible legal representative
- full postal address
- final hosting details
- final privacy statement details
- production server paths, service users, and certificate paths

## Migration strategy summary

1. preserve current design and URL intent
2. bootstrap Django and infrastructure
3. migrate shared layout to templates first
4. keep public content hard-coded during initial template migration
5. move news/events/pages incrementally to database-backed CMS models
6. introduce private area only after robust auth and permission foundations exist

## Latest completed checks

- `python manage.py check`
- `python manage.py makemigrations --check` under `config.settings.test`
- `pytest`
- `ruff check .`
- `git diff --check`
