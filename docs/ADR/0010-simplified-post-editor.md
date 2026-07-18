# ADR 0010: Simplify post editing with local rich text and server-side sanitization

## Status

Accepted

## Context

The first CMS post editor was based on structured `PostBlock` formsets. It preserved strong control,
but it asked the Web-X to understand too many technical concepts:

- layout presets beyond the actually needed choices
- block types and block ordering
- slug and SEO fields
- pin priority
- review workflow terminology

The project still needs the existing post model, revision history, publication permissions,
preview protection, and homepage feature logic.

## Decision

Keep the existing `Post` architecture and simplify the editorial surface instead of building
a second post system.

For posts:

- introduce `Post.body_html` as the canonical simplified rich-text field
- keep legacy `PostBlock` content readable and convert it into rich text when an old post is edited
- expose exactly three post layouts for new posts:
  - `simple_classic`
  - `simple_focus`
  - `simple_magazine`
- use locally hosted Trix assets for the browser editor
- sanitize post HTML on the server with `nh3`
- hide technical fields such as slug, SEO metadata, author, pin priority, and layout key
- keep revisioning, publish/schedule permissions, preview routes, and audit logging

## Consequences

- the Web-X can create a new post in two steps: layout choice, then four content fields
- existing posts remain editable without content loss
- browser-side editing stays lightweight and does not require a new frontend build stack
- server-side sanitization remains mandatory and is covered by tests
- direct inline insertion from the media library is still intentionally limited and can be added later
