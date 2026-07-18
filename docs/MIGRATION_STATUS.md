# Migration Status

Last updated: 2026-07-18

## Overall status

The branch `feature/web-x-block-cms` now contains the Django foundation, the internal members area, the Web-X CMS interface, protected member profile photos, and the first reusable structured page editor for public CMS pages.

## Current baseline

- Django project, split settings, auth, and role bootstrap are in place
- public pages render through Django templates
- structured CMS exists for posts, homepage, carousels, media, and reusable pages
- `home`, `news`, `about`, and `join` can use CMS-backed content paths
- member profile photos use private storage plus authorized delivery
- production settings are prepared with explicit security env vars

## Phase tracking

| Phase | Status | Notes |
| --- | --- | --- |
| 0 - Analysis and architecture | completed | architecture, ADRs, security model and repo rules established |
| 1 - Django foundation | completed | project bootstrap, split settings, `uv`, tests and linting are in place |
| 2 - Accounts/auth | completed | custom user model, invite onboarding, auth templates and audit baseline are active |
| 3 - Public-site migration | completed | public routes run through Django templates with canonical slash URLs |
| 4 - Members and roles | completed | internal portal, directory, admin views and role bootstrap are present |
| 5 - Documents | not started | private document storage and workflows remain open |
| 6 - Media library | in progress | public CMS media are managed; private member media are now protected; wider private media flows remain open |
| 7 - CMS | in progress | dashboard, post editor, media, carousels, homepage, reusable pages and revisions are active |
| 8 - News/events | in progress | news is CMS-backed; events remain static |
| 9 - Security/privacy hardening | in progress | private profile-photo delivery and production settings are prepared; legal and hosting details remain open |
| 10 - Tests/QA | in progress | 69 tests pass; browser automation is still absent |
| 11 - CI | not started | local quality gates are documented, CI pipeline still missing |
| 12 - Production prep | in progress | `production.py`, deploy docs and `check --deploy` guidance updated |
| 13 - Documentation finalization | in progress | CMS, deployment, security and private-media docs updated |
| 14 - Final review loop | in progress | branch still needs final push/PR and review cycle |

## Known external TODOs

- responsible legal representative
- full postal address
- final hosting details
- production server paths, service users and certificate paths
- final privacy statement details

## Latest verified checks

- `uv run ruff check .`
- `uv run python manage.py check`
- `uv run python manage.py makemigrations --check`
- `uv run python manage.py migrate`
- `uv run pytest -q`
- `uv run coverage run -m pytest`
- `uv run coverage report`
