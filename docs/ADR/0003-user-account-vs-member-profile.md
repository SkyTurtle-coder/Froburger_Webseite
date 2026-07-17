# ADR 0003: Separate user account from member profile

## Status

Accepted

## Context

Authentication data and membership/domain data change for different reasons and have different privacy and lifecycle concerns.

## Decision

Use a custom Django user model for authentication and a separate member profile model for membership-specific information.

## Consequences

- cleaner lifecycle management
- less duplication risk in account logic
- more flexible visibility and archival handling for member data
