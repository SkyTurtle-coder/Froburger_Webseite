# ADR 0005: Deliver private files through controlled application flow

## Status

Accepted

## Context

The application will manage private documents and possibly private profile-related uploads.

## Decision

Private files must not be served as ordinary public static/media URLs. Store them outside the public web root and deliver them only after server-side authorization checks.

## Consequences

- safer document handling
- download logic must be tested explicitly
- Nginx or equivalent internal-redirect patterns can be used later for efficient delivery
