# Data Classification

## Objective

Define how target-system data should be classified so that storage, access, logging, backup, and retention are designed correctly from the start.

## Classification levels

### Public

Examples:

- published public pages
- public events
- public news posts
- public images
- `robots.txt`
- `sitemap.xml`

Handling:

- may be cached publicly
- may be indexed unless intentionally excluded
- may be served directly through public media/static mechanisms

### Internal

Examples:

- draft content
- unpublished events
- navigation drafts
- internal announcements
- editorial comments

Handling:

- authenticated access only
- audit when publication state changes
- no public indexing

### Confidential

Examples:

- member profile details beyond public biography fields
- membership status history
- internal documents
- invitation records
- audit logs
- privileged operational notes

Handling:

- access limited by role and, where needed, object scope
- protected storage
- no public URLs
- careful logging
- backup encryption expected operationally

### Secret

Examples:

- Django secret key
- email credentials
- database credentials
- TOTP recovery materials
- deployment secrets

Handling:

- never commit to the repository
- store only in environment or external secret storage
- rotate when exposure is suspected

## Field-level guidance

### Member-related data

Minimum likely required:

- account email
- first name
- last name
- membership status

Optional and more sensitive:

- vulgar name
- profile photo
- short biography
- charge history
- commissions
- join/leave dates

Not to invent or seed with real values:

- real home addresses
- personal phone numbers
- birthdays
- real confidential internal notes

## Logging guidance by class

- Public actions: standard app logs are sufficient
- Internal actions: log publishing and visibility changes
- Confidential actions: structured audit events, but do not store the sensitive content itself in logs
- Secret material: never log raw values

## Retention and lifecycle TODOs

The following require future verified policy decisions and must remain TODOs until confirmed:

- retention period for former-member data
- archival policy for private documents
- deletion workflow after resignation or account closure
- data export expectations for members
- backup retention policy
- incident response notification obligations
