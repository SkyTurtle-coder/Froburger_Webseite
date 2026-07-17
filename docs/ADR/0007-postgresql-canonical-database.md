# ADR 0007: Use PostgreSQL as the canonical database

## Status

Accepted

## Context

The target system will require relational integrity, workflow data, permissions, audit entries, and reliable production behavior.

## Decision

Use PostgreSQL as the canonical database in development, test, and production-oriented documentation.

## Consequences

- better alignment between development and production
- avoids SQLite-specific drift for permissions, constraints, and concurrency behavior
