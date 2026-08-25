# AV Froburger Code Quality Audit
## Executive Summary
The main code-quality problems are repository hygiene and operational drift rather than deeply broken application structure. The application code shows deliberate security-focused engineering in several places, but the surrounding workspace has accumulated local artifacts, backup material, and environment-coupled deployment logic that make safe maintenance harder than it needs to be.

## Repository Hygiene
### CQ-001 - Project split across two Git repositories
**Severity:** CODE QUALITY  
**Confidence:** High  
**Component:** Repository structure  
**Files:** `public/.git`, `intern/.git`

**Problem**  
The project is operated as one application workspace but versioned as two independent Git repositories.

**Why relevant**  
This complicates auditing, release coordination, ignore rules, and accidental-data boundaries. It also directly diverges from the audit prompt's expected single repo rooted at `public/`.

**Recommendation**  
Document the split explicitly as an intentional architecture choice or consolidate into one repo if cross-system changes are routinely coupled.

## Dead / Legacy Code
No confidently removable dead application code was confirmed from static review alone.

## Duplicate Code
### CQ-002 - Deployment behavior is duplicated across docs and scripts with conflicting safety assumptions
**Severity:** CODE QUALITY  
**Confidence:** High  
**Component:** Deployment tooling  
**Files:** [deploy-test.ps1](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/intern/tools/wordpress/deploy-test.ps1:41), [TEST-SYSTEM-STATUS.md](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/intern/tools/wordpress/TEST-SYSTEM-STATUS.md:93), [DEPLOYMENT.md](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/public/docs/DEPLOYMENT.md:608)

**Problem**  
The written deployment guidance says the test system should normally receive minimal changed-file copies, while the shipped script still performs a mirror-style sync with deletes.

**Why relevant**  
When operators must choose between scripts and docs, the safer process becomes guesswork. That is how deployment mistakes persist even after the team has already documented the lesson.

**Recommendation**  
Make the default tool implement the documented safe path. Reserve broader sync behavior for an explicitly named recovery script.

## PHP Architecture
### CQ-003 - Workspace-local private media lives alongside source instead of behind a dedicated storage boundary
**Severity:** CODE QUALITY  
**Confidence:** High  
**Component:** Member-media operations  
**Files:** [avf-member-privacy-ops.md](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/public/wp-content/mu-plugins/avf-member-privacy-ops.md:6)

**Problem**  
Private runtime content is managed inside the shared project tree at `app/member-media-private/`.

**Why relevant**  
Even when the serving code is correct, mixing source and privacy-sensitive runtime state increases maintenance risk and makes clean checkout/backup/deploy semantics harder to preserve.

**Recommendation**  
Treat private media like external state and keep it outside the development tree.

## JavaScript
No high-confidence JS-specific quality finding was stronger than the repository and deployment issues above. The audited JS usage reviewed here did not show obvious unsafe dynamic-code patterns.

## CSS
No standalone CSS quality finding rose above the broader operational and repository issues in this pass.

## Elementor Coupling
### CQ-004 - Operational notes show repeated dependence on manual Elementor regeneration behavior
**Severity:** CODE QUALITY  
**Confidence:** Medium  
**Component:** Elementor operational coupling  
**Files:** [TEST-SYSTEM-STATUS.md](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/intern/tools/wordpress/TEST-SYSTEM-STATUS.md:107), [DEPLOYMENT.md](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/public/docs/DEPLOYMENT.md:343)

**Problem**  
Deployment success depends on cache flushes, optional `wp elementor flush-css`, and follow-up frontend requests to regenerate derived assets correctly.

**Why relevant**  
That is strong evidence of operational coupling to generated state rather than deterministic build output. It increases fragility and makes deployments harder to reason about.

**Recommendation**  
Document a narrower, deterministic regeneration contract or add verification tooling that checks the generated assets expected after deploy.

## Hardcoded Values
### CQ-005 - Environment-specific paths and hosts are hardcoded throughout deployment tooling
**Severity:** CODE QUALITY  
**Confidence:** High  
**Component:** Deployment tooling / runbooks  
**Files:** [deploy.ps1](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/intern/deploy/deploy.ps1:1), [deploy-test.ps1](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/intern/tools/wordpress/deploy-test.ps1:1), [TEST-SYSTEM-STATUS.md](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/intern/tools/wordpress/TEST-SYSTEM-STATUS.md:22)

**Problem**  
Hostnames, usernames, paths, and verification URLs are embedded directly into scripts and status documents.

**Why relevant**  
This makes safe reuse harder, increases copy-paste risk, and couples local developer workflows to one environment topology.

**Recommendation**  
Centralize deploy configuration in parameters or environment files with safe defaults and explicit environment selection.

## Tests
### CQ-006 - Security-critical application paths have tests; deployment safety mostly does not
**Severity:** CODE QUALITY  
**Confidence:** High  
**Component:** Test coverage  
**Files:** [test-avf-member-privacy.php](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/public/wp-content/mu-plugins/tests/test-avf-member-privacy.php:1), [events/tests.py](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/intern/events/tests.py:1), [documents/tests.py](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/intern/documents/tests.py:1)

**Problem**  
Application-layer security logic has meaningful automated coverage, but deployment safety remains mostly documented and manual.

**Why relevant**  
The code has evolved in a security-aware direction, yet the riskiest remaining behavior now sits in scripts and operator process where regressions are easiest to reintroduce.

**Recommendation**  
Add lightweight validation around deploy scripts and preflight checks, even if only as dry-run assertions and smoke-test wrappers.

## Documentation Drift
### CQ-007 - Versioned backup and status artifacts are accumulating in source control
**Severity:** CODE QUALITY  
**Confidence:** High  
**Component:** Repo hygiene  
**Files:** `intern/tools/wordpress/backups/`, [TEST-SYSTEM-STATUS.md](/abs/path/C:/Users/phili/Local%20Sites/av-froburger/app/intern/tools/wordpress/TEST-SYSTEM-STATUS.md:1)

**Problem**  
The repo contains backup bundles and long-lived status logs that mix tooling history with current source.

**Why relevant**  
These files age quickly, create audit noise, and make it harder to distinguish durable tooling from one-off operational residue.

**Recommendation**  
Move ephemeral restore bundles and mutable session-status logs out of the main source repo, or at least into an explicitly ignored operational-artifacts area.

## Deployment Tooling
The strongest deployment concerns are already covered in `CQ-002`, `CQ-004`, and `CQ-005`.

## Suggested Cleanup Order
- Move private member media outside the shared workspace.
- Make the default test deploy path minimal and non-destructive.
- Add hard guards to destructive deploy fallbacks.
- Relocate backup bundles and mutable status logs.
- Reduce hardcoded environment coupling in scripts.

## Safe Quick Wins
- Add a dedicated ignore/exclusion policy for `member-media-private/`.
- Rename or split high-risk deploy scripts so their blast radius is explicit.
- Move mutable status notes out of the repo.

## Larger Refactoring Candidates
- Consolidate the workspace into one repo or introduce a documented superproject workflow.
- Parameterize deployment tooling instead of embedding environment identity directly in scripts.
- Reduce dependence on manual Elementor cache/asset regeneration steps.

## Do Not Change Without Regression Tests
- Member-media token routing and file-serving behavior.
- Event-signup request signing and replay protection.
- Document authorization and download flows.
