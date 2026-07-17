# ADR 0009: Migrate legacy static content incrementally

## Status

Accepted

## Context

The existing static pages already embody the desired design, copy structure, and public information architecture.

## Decision

Migrate the current static site incrementally:

1. move pages into Django templates while preserving markup structure
2. keep content template-backed first
3. convert selected content domains to structured CMS/database models later

## Consequences

- lower design regression risk
- faster initial public-site parity
- requires discipline to avoid creating a second long-term source of truth
