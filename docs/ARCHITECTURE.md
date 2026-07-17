# Architecture

## Current baseline

The repository currently contains a static website prototype with:

- 9 public-facing HTML documents plus `intern.html`
- one shared stylesheet: `styles.css`
- one shared JavaScript file: `script.js`
- a shared asset set under `Bilder/`
- static `robots.txt`, `sitemap.xml`, `kalender.ics`, and `Zirkel.svg`
- repository documentation for content and deployment

There is no backend, database, CMS, authentication system, audit trail, or protected file handling yet.

## Consolidated audit findings

- Shared layout and metadata are duplicated across the HTML files and should first be centralized into Django templates before deeper CMS work begins.
- `aktuelles`, `anlaesse`, `mitglieder`, and parts of the home page repeat the same business facts in multiple places, which argues for structured domain models instead of long-term page-by-page HTML editing.
- Calendar data is currently duplicated across event markup, button data attributes, client-side month rendering, and `kalender.ics`; the target architecture must reduce this to a single event truth.
- The current internal area is only a public placeholder page and must not be treated as a starting secure area.
- The stylesheet already behaves like a page design system; early migration should preserve its DOM contracts rather than replacing them with a generic framework theme.

## Existing public URL structure

Current static URLs:

- `/`
- `/aktuelles.html`
- `/anlaesse.html`
- `/mitglieder.html`
- `/mitglied-werden.html`
- `/ueber-uns.html`
- `/intern.html`
- `/impressum.html`
- `/datenschutz.html`

Target canonical Django URLs:

- `/`
- `/aktuelles/`
- `/anlaesse/`
- `/mitglieder/`
- `/mitglied-werden/`
- `/ueber-uns/`
- `/intern/`
- `/impressum/`
- `/datenschutz/`

Legacy `.html` paths must redirect permanently to canonical slash URLs.

## Current frontend structure

Repeated shared elements already exist across the HTML pages:

- shared `<head>` pattern
- sticky site header
- main navigation with one dropdown
- repeated footer with legal links and contact channels
- page-hero section pattern
- reusable content card patterns

`script.js` currently implements:

- mobile menu open/close behavior
- dropdown state management and keyboard handling
- client-generated ICS downloads from `data-*` attributes
- event month-view rendering from DOM event cards
- mobile list fallback for the calendar

`styles.css` already acts as a design system and must be preserved during migration. Major component groups include:

- navigation and header
- hero sections
- buttons and section framing
- event cards and calendar month grid
- blog/report cards and article sections
- member cards and committee layouts
- about/timeline content
- footer
- responsive breakpoints at 1080px, 768px, 700px, and 560px

## Architecture target

The target system is a modular Django monolith with a strong separation between public, private, and editorial responsibilities.

Recommended high-level structure:

```text
.
|-- manage.py
|-- config/
|   |-- settings/
|   |   |-- base.py
|   |   |-- development.py
|   |   |-- test.py
|   |   `-- production.py
|   |-- urls.py
|   |-- wsgi.py
|   `-- asgi.py
|-- apps/
|   |-- core/
|   |-- accounts/
|   |-- members/
|   |-- content/
|   |-- events/
|   |-- documents/
|   |-- media_library/
|   `-- audit/
|-- templates/
|-- static/
|-- media/
|-- private_media/
|-- tests/
|-- docs/
|-- requirements/
|-- compose.yaml
|-- pyproject.toml
`-- .env.example
```

## App responsibilities

### `apps.core`

- shared utilities
- site configuration
- healthcheck
- shared template context
- canonical URL helpers
- robots/sitemap integration

### `apps.accounts`

- custom user model
- email-based login
- invitations
- password reset/change
- optional 2FA integration point
- session and account security helpers

### `apps.members`

- member profile separated from account
- statuses, charges, committees, and profile metadata
- member directory and privacy controls

### `apps.content`

- custom page model
- structured page blocks
- draft/review/published workflow
- page revision history
- navigation management

This app should not become a free-form generic page builder. Structured domains such as events, editorial content, and members should keep their own models and use content blocks only where page composition is genuinely needed.

### `apps.events`

- public and internal events
- calendar views
- ICS generation and feeds
- news posts if kept together, otherwise split into a `news` app later

### `apps.documents`

- protected document model
- private file metadata
- download authorization
- versioning and audit hooks

### `apps.media_library`

- public and private media assets
- image metadata
- alt text
- controlled reuse by pages, posts, events, and members

### `apps.audit`

- audit event model
- helpers for structured, immutable-ish security and editorial logging

## Core domain model outline

### Accounts

- `User`
- `Invitation`
- optional `UserMfaDevice` if not fully delegated to a vetted package

### Members

- `MemberProfile`
- `Charge`
- `Committee`
- through models for historical assignments

### Content

- `Page`
- `PageRevision`
- `PageBlock`
- `NavigationItem`

### News and events

- `NewsPost`
- `NewsCategory`
- `Event`
- optional `EventCategory`

These models should become the canonical source for:

- public event listings
- month view rendering
- per-event ICS downloads
- feed generation
- homepage featured event snippets derived from structured content

### Documents and media

- `Document`
- `DocumentVersion`
- `MediaAsset`
- optional linking tables for placement and reuse

### Audit

- `AuditLogEntry`

## Template migration strategy

The migration should happen in two steps instead of one risky rewrite.

### Step 1: static-to-template migration

- move repeated head/navigation/footer into reusable templates
- keep page bodies very close to the current HTML
- move CSS, JS, SVG, and images into Django staticfiles
- replace raw asset paths with `{% static %}`
- keep public content hard-coded in templates first

This step is intentionally conservative because the current prototype is art-directed and already stable in markup and CSS behavior.

### Step 2: data-backed migration

- introduce page/content models
- migrate `aktuelles` and `anlaesse` to structured database content
- replace remaining template-hardcoded public content incrementally
- normalize repeated member and committee data so the same people are not maintained in several sections manually

This two-step path reduces design regression risk.

## Storage boundaries

Public and private files must be separated from day one.

- `static/`: versioned code assets
- `media/` or public media root: public uploaded media only
- `private_media/`: protected documents and private member-related uploads

No private file may be served by direct guessable URL without authorization.

## Current risk summary that affects architecture

- external Google Fonts create a privacy and compliance problem
- legal pages intentionally contain unresolved TODOs and must remain visibly incomplete until verified
- current client-side ICS generation and DOM-derived month view are useful for UX, but event truth must move to the database
- current static repository contains design-specific CSS and DOM contracts that templates must preserve during the first migration
- `intern.html` is currently a public notice page, not a secure area
- future private documents, exports, RSVP data, and member records raise the system from brochure-site risk to confidentiality and authorization risk

## Decisions deferred until external information exists

- exact production hostnames beyond the current public domain
- real email provider
- final private file retention and deletion policy by role
- legally verified imprint and privacy details
- whether TOTP will use a specific vetted Django package or a minimal internal integration around a maintained library
