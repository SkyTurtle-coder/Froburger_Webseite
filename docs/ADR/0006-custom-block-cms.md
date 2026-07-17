# ADR 0006: Implement a custom structured block CMS

## Status

Accepted

## Context

The Web-Aktuar needs manageable editorial tooling without unrestricted HTML or script injection.

## Decision

Implement a custom CMS with structured blocks, controlled variants, and workflow states instead of embedding a full third-party CMS.

## Consequences

- design consistency is easier to preserve
- security posture is stronger than free-form HTML editing
- more project-specific implementation work is required
