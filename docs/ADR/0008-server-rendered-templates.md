# ADR 0008: Prefer server-rendered templates over a SPA

## Status

Accepted

## Context

The current site is content-heavy, design-specific, and already structured around static pages. The project does not need a separate frontend application for the first full version.

## Decision

Use Django templates as the primary rendering strategy and keep JavaScript focused on progressive enhancement.

## Consequences

- easier migration from current HTML
- lower frontend build and deployment complexity
- simpler SEO preservation
