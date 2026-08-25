# AV Froburger Security Audit
## Audit Scope
This audit covered the combined project workspace under `app/`, including the WordPress codebase in `public/`, the Django codebase in `intern/`, deployment tooling, and adjacent project-local artifacts that materially affect security posture.

Observed deviation from the requested setup: the project is split across two Git repositories (`public/.git` and `intern/.git`) instead of one repo rooted at `public/`. That matters for secret handling, ignore rules, and bulk workspace operations.

## Executive Summary
The strongest issues are not classic input-validation bugs. The codebase already contains several meaningful controls around CSRF, tokenized media delivery, rate limiting, and document authorization. The main residual risks are operational: privacy-sensitive media living inside the shared project workspace, a still-enabled legacy authentication path for event signups, and destructive deployment flows that rely on path correctness rather than hard safety guards.

## Risk Overview
| ID | Severity | Component | Summary | Confidence |
|---|---|---|---|---|
| SEC-001 | High | Member media / workspace hygiene | Private member-media files are stored inside the shared project workspace but outside both Git roots, so they are not protected by either repo's ignore policy and can be copied or archived accidentally. | High |
| SEC-002 | Medium | Event signup API | Django still accepts the legacy bare shared-secret header, so the HMAC timestamp/replay protection can be bypassed whenever that shared secret is exposed. | High |
| SEC-003 | Medium | Internal deployment | `intern/deploy/deploy.ps1` falls back to a destructive remote wipe before extraction and does not validate the remote target path beyond variable interpolation. | High |
| SEC-004 | Medium | Test deployment | `intern/tools/wordpress/deploy-test.ps1` still performs broad mirror-style deployment with remote `rsync --delete` despite documented guidance to use minimal file copies on the test system. | High |
| SEC-005 | Low | Repository operational docs | The repo contains detailed live-environment topology and operational history that increases the blast radius of repo disclosure. | Medium |

## Critical Findings
No critical findings confirmed from static review.

## High Findings
### SEC-001 (MEM/INV) - Private member media stored inside the shared workspace
**Severity:** High  
**Confidence:** High  
**Component:** Member media / workspace hygiene  
**Files:** `member-media-private/` (workspace artifact), [avf-member-privacy-ops.md](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/public/wp-content/mu-plugins/avf-member-privacy-ops.md:6)

**Problem**  
The member-media operations doc explicitly states that member images are stored privately under `app/member-media-private/`, and that exact directory exists in the workspace with image files present. Because that directory sits outside both Git roots, neither `public/.gitignore` nor `intern/.gitignore` governs it.

**Why relevant**  
This is a privacy-sensitive storage location living inside a developer workspace boundary. It can be swept up by bulk copies, ad-hoc archives, support bundles, editor tooling, or future repo restructuring without any Git-layer warning. The issue is not public HTTP exposure; it is accidental disclosure through local workspace handling.

**Existing controls**  
The WordPress delivery path itself uses opaque 32-hex tokens and serves files through a controlled route with `noindex`/`nosniff` headers instead of exposing direct upload URLs ([avf-member-privacy.php](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/public/wp-content/mu-plugins/avf-member-privacy.php:1717)).

**Recommendation**  
Move private member media fully outside the shared project tree on developer machines as well, or create a dedicated top-level ignore boundary plus explicit archival exclusions for that directory. Treat it like secrets-bearing state, not like source-adjacent content.

**Status:** Partially remediated - Branch `fix/sec-audit-p0-p1`, Commit `d1dd8db`, 2026-08-25. `avf-member-privacy-ops.md` now contains a prominent exclusion requirement and a manual, configuration-based move procedure. Existing member data was deliberately not moved; the current workspace path remains until an operator completes that procedure.

## Medium Findings
### SEC-002 (EVT/PLG) - Legacy bare secret still bypasses signed event-signup authentication
**Severity:** Medium  
**Confidence:** High  
**Component:** Event signup API  
**Files:** [class-avf-events-api-client.php](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/public/wp-content/plugins/avf-events-integration/includes/class-avf-events-api-client.php:157), [public_views.py](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/intern/events/public_views.py:319)

**Problem**  
WordPress now generates signed requests with timestamp and HMAC, but it also still sends the legacy plain shared-secret header during rollout. Django accepts that legacy header whenever no signature is present.

**Why relevant**  
The signed path includes replay resistance. The legacy path does not. Any place that can observe or recover the shared secret can still submit authenticated signup requests without the newer replay checks.

**Existing controls**  
The endpoint is limited to `POST`, validates payload shape, rate-limits by client IP, and rejects duplicate signups.

**Recommendation**  
Remove legacy-secret acceptance on the Django side once rollout is complete, and stop sending the plain secret header from WordPress. Keep only the signed request path.

**Status:** Partially remediated - Intern branch `fix/sec-audit-p0-p1`, Commit `e4485e5`, 2026-08-25. Django now requires signed timestamp plus HMAC. WordPress no longer sends the legacy bare-secret header in the working tree, but that client file has unrelated pre-existing changes and was intentionally not included in a security commit.

### SEC-003 (OPS) - Destructive deploy fallback wipes remote app directory without a hard path guard
**Severity:** Medium  
**Confidence:** High  
**Component:** Internal deployment  
**Files:** [deploy.ps1](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/intern/deploy/deploy.ps1:49)

**Problem**  
If `rsync` is unavailable, the fallback uploads a tarball and then runs a remote command that recursively removes all children of `$RemoteAppDir` before extraction.

**Why relevant**  
This is operationally brittle. A wrong target path, bad parameterization, or extraction failure can turn a partial deployment issue into a broader outage. A backup is created first, but the destructive action still proceeds without an explicit allowlist check on the final remote path.

**Existing controls**  
The script creates a dated backup directory before synchronization.

**Recommendation**  
Add an explicit remote path assertion before any destructive command and fail closed unless the target equals the expected application directory. Prefer `rsync --delay-updates`-style replacement paths over wipe-and-extract fallback.

**Status:** Partially remediated - Intern branch `fix/sec-audit-p0-p1`, Commit `2d0f0e8`, 2026-08-25. The deployment script now rejects every target except `/srv/avf-intern/app` locally and reasserts that value on the remote host before the fallback wipe. The fallback remains destructive; an atomic replacement strategy is deferred.

### SEC-004 (OPS) - Test deploy script still uses broad mirror + remote delete flow
**Severity:** Medium  
**Confidence:** High  
**Component:** Test deployment  
**Files:** [deploy-test.ps1](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/intern/tools/wordpress/deploy-test.ps1:41), [TEST-SYSTEM-STATUS.md](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/intern/tools/wordpress/TEST-SYSTEM-STATUS.md:93)

**Problem**  
The documented operational rule says `test.avfroburger.ch` should be updated by copying only the changed files, but the script still builds a mirrored staging tree and applies it remotely with `rsync --delete`.

**Why relevant**  
This increases the blast radius of every test deployment, especially because the same operational notes document that `test` and `beta` were previously coupled incorrectly. Static review cannot prove current isolation, so the safer documented workflow and the shipped script should not diverge.

**Existing controls**  
The script excludes uploads, cache, upgrade data, and `wp-config.php`, and it performs a final URL verification request.

**Recommendation**  
Replace the default script path with a minimal changed-files deploy flow, or gate the full mirror behind an explicit high-risk flag and a preflight environment verification step.

## Low Findings
### SEC-005 (INV/OPS) - Operational documentation exposes detailed live-environment topology
**Severity:** Low  
**Confidence:** Medium  
**Component:** Repository operational docs  
**Files:** [TEST-SYSTEM-STATUS.md](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/intern/tools/wordpress/TEST-SYSTEM-STATUS.md:18)

**Problem**  
The repository includes detailed notes about environment layout, historical coupling mistakes, filesystem paths, and operational recovery steps.

**Why relevant**  
This is not a secret leak by itself, but it materially increases the value of repo disclosure and gives an attacker or accidental recipient more context than necessary.

**Recommendation**  
Trim environment-specific operational history from the source repo, or move sensitive runbooks to a narrower-access operational store.

## Privacy Findings
- Private member-media handling is architecturally stronger than direct upload URLs, but the local storage location inside the shared project workspace remains a privacy risk.
- No direct IDOR or public token-pattern bypass was confirmed from static review in the member-media route handler.

## Deployment / Infrastructure Findings
- The highest residual deployment risks are destructive sync behavior and documentation/script drift, not missing backup guidance.
- Live infrastructure hardening, backup validity, and rollback usability require runtime verification outside this static audit.

## Positive Security Controls
- WordPress sends baseline security headers including `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, and deduplicated `X-Frame-Options` across frontend, login, and admin flows ([avf-security-headers.php](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/public/wp-content/mu-plugins/avf-security-headers.php:56)).
- Member-media requests require a strict 32-hex token and respond with `noindex`/`nosniff` headers before serving a file ([avf-member-privacy.php](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/public/wp-content/mu-plugins/avf-member-privacy.php:1717)).
- The public WordPress signup handler enforces POST-only handling and a nonce before forwarding to Django ([class-avf-event-signup-handler.php](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/public/wp-content/plugins/avf-events-integration/includes/class-avf-event-signup-handler.php:50)).
- Django applies cache-backed throttling for login and password-reset flows ([throttling.py](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/intern/accounts/throttling.py:1)).
- Document download and preview paths are gated by explicit authorization helpers rather than filename-based access alone ([views.py](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/intern/documents/views.py:184)).

## Recommended Remediation Order
- P0 - Move private member-media out of the shared workspace boundary or enforce a dedicated exclusion boundary for it on every developer machine.
- P1 - Remove legacy shared-secret signup authentication and ship signed-only requests.
- P1 - Add hard target-path assertions to destructive deployment code and demote full-wipe fallback behavior.
- P2 - Align the test deployment script with the documented minimal-copy workflow.
- P3 - Reduce operational topology detail in committed runbooks.

## Areas Requiring Manual Verification
- Whether any developer tooling or deployment packaging already includes `member-media-private/`.
- Whether the legacy signup secret is logged or visible in any proxy, error, or APM layer.
- Whether current live deploy targets have additional server-side path safeguards not visible in the scripts.
- Whether live header behavior matches the local mu-plugin intent on all entry points.

## Audit Limitations
- Static review only; no server, database, or runtime access.
- No live verification of WordPress, Django, nginx, cache, or filesystem permissions.
- Existing uncommitted changes were present in both repos; application code was not modified during this audit.

P0 - sofort
- Remove the shared-workspace exposure of private member media.

P1 - vor naechstem groesseren Deployment
- Disable the legacy bare-secret signup path.
- Add explicit path guards to destructive deploy logic.

P2 - mittelfristig
- Replace the test full-mirror deploy with minimal changed-file deployment.

P3 - Cleanup / technische Schulden
- Reduce environment-specific runbook detail in the source repo.
