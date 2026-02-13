# Phase 31: Learners Module Fixes - Context

**Gathered:** 2026-02-13
**Status:** Ready for planning

<domain>
## Phase Boundary

Fix all critical data loss bugs, security warnings, and dead code in Learners module forms. Source audit: `docs/formfieldanalysis/learners-audit.md` provides exact file paths, line numbers, and recommended fixes.

</domain>

<decisions>
## Implementation Decisions

### All decisions locked per audit

Every requirement has a prescribed fix path from the audit. No ambiguity on approach — follow audit recommendations exactly.

### Sponsors resolution (LRNR-02)
- **Needs user input at implementation time** — either implement sponsor storage (new table + POST processing) or remove the UI from both forms
- Implementer should ask user before proceeding

### Claude's Discretion
- Order of fixes within groups
- Exact date validation regex/approach for LRNR-08
- How much of the legacy view file to remove in LRNR-10

</decisions>

<specifics>
## Specific Ideas

All fixes have exact file paths and line numbers in the audit. Follow audit recommendations.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 31-learners-module-fixes*
*Context gathered: 2026-02-13*
