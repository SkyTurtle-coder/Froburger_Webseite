# Security Model

## Security objective

The target application must default to deny access unless explicitly permitted. Public content, private member data, private documents, and editorial actions must be clearly separated.

## Current baseline findings

- there is no real authentication or authorization yet
- `intern.html` is intentionally only a public placeholder
- no backend secrets or private data are currently modeled in application code
- public pages still load Google Fonts from external providers
- current client-side behavior is limited to navigation and calendar interactions
- public pages already expose member names, roles, and related profile details, so future import/export and member tooling must be designed with strict minimization and access boundaries

## Protected assets in the target system

- accounts and sessions
- invitations
- member profiles
- private documents
- private media
- internal events and internal news
- audit logs
- editorial drafts and scheduled content

## Threat model

### External attacker

Threats:

- brute-force login attempts
- credential stuffing
- CSRF
- XSS through CMS or file metadata
- IDOR against profiles, documents, and media
- file upload abuse
- direct guessing of private file URLs
- hostile content publication if editorial permissions are weak

Controls:

- rate limiting
- invite-only registration
- strong password handling via Django
- CSRF protection
- CSP and secure headers
- strict server-side object permission checks
- private file indirection
- audit logging

### Authenticated low-privilege user

Threats:

- accessing other members' private data
- downloading documents beyond role scope
- escalating role through tampered requests
- viewing drafts or internal events without permission

Controls:

- object-level authorization checks
- separate public/private visibility flags
- role bootstrap and clear group mapping
- audit trail for sensitive reads and writes where justified

### Malicious or compromised editor

Threats:

- accidental or malicious publication of unsafe content
- injection of HTML, script, or tracking code
- unreviewed changes to legal content
- exposure of private documents by wrong visibility choice

Controls:

- structured block CMS
- no free JavaScript entry
- no raw unsanitized HTML
- preview and publish workflow
- audit logs for publishing and role-sensitive changes

### Compromised administrator

Threats:

- full data exposure
- destructive changes
- secret leakage through logs or settings mishandling

Controls:

- enforce 2FA for privileged roles
- minimize standing admin rights
- separate operational docs from secrets
- explicit production configuration review

## Risk priorities

### Critical

- private file exposure by direct URL or predictable path
- object-level access control failures for member and document data
- allowing script execution through CMS input
- missing protection against login abuse for privileged roles

### High

- insufficient role separation between Web-Aktuar, member admins, and system admins
- audit gaps on invitations, publishing, and sensitive downloads
- public repository accidentally containing secrets or private uploads
- weak production security headers and cookie settings

### Medium

- privacy leakage through externally loaded fonts
- incomplete deletion/retention workflows
- image metadata disclosure
- insufficient reviewer workflow around legal pages

### Low

- UI leakage of unavailable actions if backed by correct server checks
- minor SEO or metadata inconsistencies that do not expose data

## Role security principles

- anonymous users only access public content
- prospects/guests should remain explicitly weaker than confirmed members if that role is introduced
- members access only their permitted private content
- Web-Aktuar edits content but does not automatically gain member-admin power
- member admins manage people-related data but do not gain unrestricted CMS or system control by default
- system admins have technical control and must be protected by 2FA and strict logging

## Authentication and session requirements

- custom user model with unique email
- no public self-registration
- invitation tokens must be time-limited and one-time-use
- password reset must avoid user enumeration
- secure cookie defaults in production
- session invalidation after relevant credential changes
- rate limiting and suspicious-login logging
- privileged roles should re-authenticate or use stronger confirmation flows before especially sensitive actions such as exports, role changes, or invitation management

## File handling requirements

- validate extension, MIME type, and size
- store public and private uploads separately
- never expose private files through direct static serving
- optionally scrub or at least document image metadata handling
- record upload and download actions for sensitive files
- treat `robots.txt`, `noindex`, or hard-to-guess URLs as insufficient for any private file or internal page

## CMS security requirements

- structured blocks only
- sanitized rich text if used
- no inline script, no event handlers, no arbitrary embeds
- draft and preview separation from public pages
- publishing and unpublishing must be audited

## Recommended production defaults

- `DEBUG = False`
- strict `ALLOWED_HOSTS`
- HTTPS redirect
- secure session and CSRF cookies
- HSTS
- `X-Content-Type-Options: nosniff`
- clickjacking protection
- `Referrer-Policy`
- conservative CSP tailored to self-hosted assets
- trusted proxy headers only when documented

## Logging and audit constraints

- never log passwords, reset secrets, invitation tokens, session data, or full private content bodies
- record actor, action, object type, object id, timestamp, result, and minimal safe context
- normal editors must not be able to alter audit records

## Privacy and data minimization notes

- member data fields must be classified and permissioned before implementation
- legal and privacy text must keep explicit TODO placeholders until verified
- external dependencies that transmit visitor data should be removed or explicitly documented
- CSV/Excel exports, guest lists, RSVP states, and backup contents should be treated as at least confidential and often strictly confidential data
