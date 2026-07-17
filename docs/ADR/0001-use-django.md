# ADR 0001: Use Django as the application framework

## Status

Accepted

## Context

The target system needs server-rendered public pages, authentication, role-based access, private file delivery, editorial workflows, audit logging, and long-term maintainability without introducing an unnecessarily complex frontend stack.

## Decision

Use Django as the primary application framework.

## Consequences

- mature authentication, admin, ORM, forms, and template ecosystem
- good fit for a modular monolith
- simpler deployment and team onboarding than a separate SPA plus API stack
