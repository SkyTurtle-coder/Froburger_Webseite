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
- production-ready operational documentation

## Current status

- Phase 0: completed
- Phase 1: completed
- Phase 2: completed
- Phase 3: completed
- Phase 4: completed
- Phase 5: completed
- Phase 6: in progress
- Phase 7: in progress
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
- Web-X CMS dashboard, posts, pages, homepage, media, and carousel workflows
- structured public imports for `about`, `join`, and `members`
- CMS-backed public and members-visible events including ICS feeds
- protected document workflows with private storage, CMS editing, member downloads, and sensitive-area checks
- browser E2E coverage for events, documents, and the public members page

## Remaining phases

### Phase 6 - Media library

- public CMS media are operational
- private member profile photos are protected
- a production reverse-proxy delivery path for private media remains infrastructure-dependent

### Phase 7 - Custom CMS

- editorial workflows are operational for posts, pages, homepage, events, and documents
- broader navigation management is still intentionally not a free-form CMS capability

### Phase 9 - Security and privacy hardening

- private delivery, permission checks, and production security settings are in place
- verified legal and hosting facts remain `TODO`

### Phase 11 - CI

- local quality gates exist
- a repository CI pipeline is still open

### Phase 12 - Production preparation

- deployment settings and runbooks are prepared
- real server paths, service accounts, certificates, and proxy rules remain environment-specific

### Phase 13 - Final documentation

- operator and reviewer documentation now covers CMS, events, documents, members page, deployment, and browser tests
- final README or PR wording may still be refined after review

### Phase 14 - Final review loop

- branch still needs the last commit/push cycle and external review

## Open external facts

- responsible legal representative
- complete postal address
- final hosting details
- production filesystem paths and service users
- final privacy statement content
