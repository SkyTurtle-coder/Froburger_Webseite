# Migration Status

Last updated: 2026-07-18

## Overall status

The branch `feature/web-x-block-cms` now includes the Django foundation, the internal members area, the Web-X CMS, protected member media, private documents, CMS-backed events, and the structured public members page.

## Current baseline

- public routes render through Django templates
- `home`, `news`, `about`, `join`, and `members` have CMS-backed paths
- events are CMS-managed and expose public plus members-only ICS feeds
- private documents use protected storage and server-side authorization
- profile photos use protected storage and server-side authorization
- browser E2E coverage exists for the most critical editorial workflows

## Phase tracking

| Phase | Status | Notes |
| --- | --- | --- |
| 0 - Analysis and architecture | completed | architecture, ADRs, repo rules, and security model documented |
| 1 - Django foundation | completed | project bootstrap, split settings, `uv`, pytest, and Ruff are in place |
| 2 - Accounts/auth | completed | custom user model, invite onboarding, auth views, and audit baseline are active |
| 3 - Public-site migration | completed | public routes run through Django templates with canonical slash URLs |
| 4 - Members and roles | completed | internal portal, directory, admin workflows, and role bootstrap are active |
| 5 - Documents | completed | protected storage, CMS editing, versioning, and member download authorization are implemented |
| 6 - Media library | in progress | public CMS media and private profile photos are covered; final proxy delivery remains environment-specific |
| 7 - CMS | in progress | dashboard, posts, pages, homepage, media, carousels, events, documents, and revisions are active |
| 8 - News/events | completed | news and events are CMS-backed; public and internal calendar feeds exist |
| 9 - Security/privacy hardening | in progress | protected delivery, permission checks, and deploy settings are active; legal and hosting facts remain open |
| 10 - Tests/QA | completed | local checks, focused auth tests, and browser E2E coverage are in place |
| 11 - CI | not started | local quality gates exist, CI pipeline still open |
| 12 - Production prep | in progress | deployment docs and production settings are prepared, real infrastructure values remain open |
| 13 - Documentation finalization | in progress | operations and reviewer docs are substantially updated |
| 14 - Final review loop | in progress | branch still needs final commit/push and external review |

## Known external TODOs

- responsible legal representative
- full postal address
- final hosting details
- production server paths, service users, and certificate paths
- final privacy statement details

## Latest verified checks

- `git diff --check`
- `uv run ruff check .`
- `uv run python manage.py check`
- `uv run python manage.py makemigrations --check`
- `uv run python manage.py migrate`
- `uv run pytest -q` -> `97 passed`
- `uv run coverage run -m pytest`
- `uv run coverage report` -> `82%`
- `uv run python manage.py check --deploy --settings=config.settings.production`

## Expected deploy warnings

- `security.W005` for `SECURE_HSTS_INCLUDE_SUBDOMAINS`
- `security.W021` for `SECURE_HSTS_PRELOAD`
