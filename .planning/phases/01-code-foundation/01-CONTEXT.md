# Phase 1: Code Foundation - Context

**Gathered:** 2026-02-02
**Status:** Ready for planning

<domain>
## Phase Boundary

Migrate events module code (~7,700 lines, 33 PHP files) from wecoza-events-plugin into wecoza-core. Convert to WeCoza\Events\* namespace with PSR-4 autoloading. Consolidate database connection to use wecoza_db() instead of separate connection class.

Does NOT include: database triggers/schema (Phase 2), bootstrap/activation (Phase 3), or any feature work (Phases 4-7).

</domain>

<decisions>
## Implementation Decisions

### Migration approach
- Copy files fresh without preserving git history — cleaner start in wecoza-core
- Keep original wecoza-events-plugin as deactivated archive during stabilization (don't delete)
- Verification via manual smoke test — check that classes load and basic functionality works

### Claude's Discretion
- Migration order (all at once vs layer-by-layer) — Claude decides based on code complexity
- Exact namespace mapping from old class names to new
- How to handle database connection replacement (direct substitution vs wrapper)
- File organization within src/Events/ subdirectories

</decisions>

<specifics>
## Specific Ideas

No specific requirements — follow existing patterns in src/Learners/ and src/Classes/ for consistency.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 01-code-foundation*
*Context gathered: 2026-02-02*
