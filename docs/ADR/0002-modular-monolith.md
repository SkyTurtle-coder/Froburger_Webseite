# ADR 0002: Use a modular monolith

## Status

Accepted

## Context

The project scope is broad, but the team and deployment model do not justify microservices.

## Decision

Build a modular Django monolith with bounded apps for accounts, members, content, events, documents, media, audit, and shared core behavior.

## Consequences

- lower operational complexity
- easier cross-domain transactions and permission checks
- clear boundaries without distributed-system overhead
