# ADR 0004: Role-based visibility with object-aware permission checks

## Status

Accepted

## Context

The target system mixes public content, internal content, and confidential member/document data.

## Decision

Use role-based access control backed by server-side object-aware checks for sensitive records and downloads.

## Consequences

- template visibility alone is not trusted
- direct HTTP access paths require explicit authorization
- permission tests become a core part of the suite
