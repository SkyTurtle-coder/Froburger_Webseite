# Migration Status

Last updated: 2026-07-18

## Overall status

The branch `feature/simplify-web-x-cms` now includes the Django foundation, the internal members
area, the protected document and media flows, CMS-backed public pages and events, and the
simplified Web-X post workflow.

## Current baseline

- public routes render through Django templates
- `home`, `news`, `about`, `join`, and `members` have CMS-backed paths
- posts use a simplified Web-X editor with three layouts and server-side rich-text sanitization
- existing block-based posts remain editable and migrate toward `body_html`
- the homepage uses exactly one current featured post plus at most one queued future featured post
- events are CMS-managed and expose public plus members-only ICS feeds
- private documents and profile photos use protected storage with server-side authorization
- browser E2E coverage exists for posts, events, documents, members page, and CMS permissions

## Phase tracking

| Phase | Status | Notes |
| --- | --- | --- |
| 0 - Analysis and architecture | completed | architecture, ADRs, repo rules, and security model documented |
| 1 - Django foundation | completed | project bootstrap, split settings, `uv`, pytest, and Ruff are in place |
| 2 - Accounts/auth | completed | custom user model, invite onboarding, auth views, and audit baseline are active |
| 3 - Public-site migration | completed | public routes run through Django templates with canonical slash URLs |
| 4 - Members and roles | completed | internal portal, directory, admin workflows, and role bootstrap are active |
| 5 - Documents | completed | protected storage, CMS editing, versioning, and member download authorization are implemented |
| 6 - Media library | completed | public CMS media and protected member profile photos are operational |
| 7 - CMS | completed | dashboard, posts, pages, homepage, media, carousels, events, documents, revisions, and simplified post UX are active |
| 8 - News/events | completed | news and events are CMS-backed; public and internal calendar feeds exist |
| 9 - Security/privacy hardening | in progress | protected delivery, permission checks, and deploy settings are active; legal and hosting facts remain open |
| 10 - Tests/QA | completed | local checks, focused auth tests, and browser E2E coverage are in place |
| 11 - CI | not started | local quality gates exist, CI pipeline still open |
| 12 - Production prep | in progress | deployment docs and production settings are prepared, real infrastructure values remain open |
| 13 - Documentation finalization | in progress | editor, acceptance, PR, and status docs are updated; review output may still refine them |
| 14 - Final review loop | in progress | branch still needs final selective commit/push and external review |

## Latest verified checks

- `git diff --check`
- `uv run ruff check .`
- `uv run python manage.py check`
- `uv run python manage.py makemigrations --check`
- `uv run python manage.py migrate`
- `uv run pytest -q` -> `110 passed`
- `uv run coverage run -m pytest`
- `uv run coverage report` -> `69%`
- `uv run pytest tests/e2e -q` -> `5 passed`
- `uv run python manage.py check --deploy --settings=config.settings.production`

## Current local deploy warnings

- `security.W005`
- `security.W009`
- `security.W021`

`security.W009` reflects the local, non-productive Secret-Key setup. Production still needs a
real strong secret.

## Known external TODOs

- responsible legal representative
- full postal address
- final hosting details
- production server paths, service users, and certificate paths
- final privacy statement details
