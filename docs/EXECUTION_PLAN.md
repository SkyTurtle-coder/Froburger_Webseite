# Execution Plan

Last updated: 2026-07-18

## Objective

Migrate the AV Froburger website from the static prototype into a secure Django application with:

- public pages
- members area
- role-based authorization
- structured Web-X CMS workflows
- protected documents and private media
- events, news, and editorial pages
- a simplified editorial workflow for the Web-X

## Current status

- Phase 0: completed
- Phase 1: completed
- Phase 2: completed
- Phase 3: completed
- Phase 4: completed
- Phase 5: completed
- Phase 6: completed
- Phase 7: completed
- Phase 8: completed
- Phase 9: in progress
- Phase 10: completed
- Phase 11: not started
- Phase 12: in progress
- Phase 13: in progress
- Phase 14: in progress

## Completed implementation blocks

- Django foundation, split settings, `uv`, pytest, Ruff, and database configuration
- custom user model, auth flows, invite onboarding, and audit baseline
- public pages on Django templates with canonical slash URLs
- internal dashboard, profile editing, member directory, and role bootstrap
- protected member profile photos via private storage and server-side delivery
- protected document workflows with private storage, CMS editing, versioning, and member downloads
- CMS-backed public and members-visible events including ICS feeds
- structured public imports for `about`, `join`, and `members`
- structured CMS for pages, homepage, media, and carousels
- simplified Web-X post workflow with three layouts, four visible fields, local rich text, and single homepage feature selection
- browser E2E coverage for posts, events, documents, and the public members page

## Remaining phases

### Phase 9 - Security and privacy hardening

- server-side permission checks, private delivery, preview protection, and post sanitization are in place
- verified legal and hosting facts remain `TODO`

### Phase 11 - CI

- local quality gates exist
- a repository CI pipeline is still open

### Phase 12 - Production preparation

- deployment settings and runbooks are prepared
- real server paths, service accounts, reverse proxy rules, and production secrets remain environment-specific

### Phase 13 - Final documentation

- operator, editor, security, and acceptance documentation are updated
- final push and external review notes may still change after review

### Phase 14 - Final review loop

- branch still needs the final selective commit/push cycle and external review

## Open external facts

- responsible legal representative
- complete postal address
- final hosting details
- production filesystem paths and service users
- final privacy statement content
