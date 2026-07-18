# Migration Status

Last updated: 2026-07-18

## Overall status

The repository now contains the Django foundation, authentication baseline, Django-rendered public website, member-profile and role-management basics, plus the first structured CMS and media model foundation on the feature branch. Legacy `.html` paths are redirected permanently to canonical slash URLs.

## Current repository baseline

- branch: `feature/web-x-block-cms`
- public site served through Django templates
- Django project bootstrap present
- custom user model and account onboarding present
- protected account entry point present
- member profiles and internal member routes present
- role bootstrap command present
- audit trail baseline present for invitations
- static assets served from Django `static/`
- legacy `.html` URLs redirected to canonical paths
- structured CMS model foundation present
- media library foundation present
- no Web-X editor UI yet
- public pages still mostly template-static
- Phase-0 review consensus still applies: centralize templates first, then normalize structured content, then expand CMS and private area

## Phase tracking

| Phase | Status | Notes |
| --- | --- | --- |
| 0 - Analysis and architecture | completed | branch created, repo analyzed, architecture/security docs committed |
| 1 - Django foundation | completed | `.venv`, Django project, split settings, requirements, compose, and smoke tests are in place |
| 2 - Accounts/auth | completed | custom user model, email auth, invite-only onboarding, auth templates, admin wiring, migrations, and tests are in place |
| 3 - Public-site migration | completed | public pages now render through Django templates, assets live under `static/`, and legacy `.html` URLs redirect permanently |
| 4 - Members and roles | completed | separate member profiles, self-service profile views, permission-gated member admin views, and role bootstrap are in place |
| 5 - Documents | not started | requires private storage design |
| 6 - Media library | in progress | `MediaAsset` with upload validation, metadata fields, and permissions added; private delivery and editor UI still open |
| 7 - CMS | in progress | content models, layout presets, revisions, and role permissions added; preview, publish, restore, and public rendering still open |
| 8 - News/events | not started | existing static content available as migration source |
| 9 - Security/privacy hardening | not started | threat model drafted, implementation pending |
| 10 - Tests/QA | in progress | pytest, ruff, Django checks, and CMS model tests are present; editor, preview, and file-delivery coverage still open |
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
4. keep public content hard-coded during the first Django template cutover
5. move news/events/pages incrementally to database-backed CMS models
6. introduce deeper private-area features only after robust auth and permission foundations exist
7. move homepage, news, and selected public pages onto the new structured CMS models

## Latest completed checks

- `python manage.py check`
- `python manage.py makemigrations --check` under `config.settings.test`
- `pytest`
- `ruff check .`
- `git diff --check`
