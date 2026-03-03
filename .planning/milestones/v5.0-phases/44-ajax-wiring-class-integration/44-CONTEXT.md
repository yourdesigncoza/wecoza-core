# Phase 44: AJAX Wiring + Class Integration - Context

**Gathered:** 2026-02-18
**Status:** Ready for planning

<domain>
## Phase Boundary

Wire existing progression AJAX calls end-to-end (mark-complete, portfolio upload, data fetch) and enrich class forms with progression context (Last Completed Course column, active LP collision warning). Backend services, repository, model, views, and JS already exist — this phase connects them.

</domain>

<decisions>
## Implementation Decisions

### Collision Warning Behavior
- Warn but allow — show warning with active LP details, admin clicks "Add Anyway" to proceed
- Warning appears as modal when admin clicks "Add" for a learner with active LP (not inline in table)
- Modal shows full details: LP name, current progress %, hours logged, start date, class it belongs to
- Log the collision acknowledgement — record that admin saw warning and proceeded (audit trail for compliance)

### Available Learners Table Columns
- Add "Last Completed Course" column showing LP name + completion date (e.g. "NQF Level 4 — completed 2026-01-15")
- Learners with no completed LPs show dash ("—") — clean and minimal
- Learners with active LPs get a small badge/icon next to their name — pre-warns before the collision modal
- Column is sortable by completion date — helps admin find experienced vs fresh learners

### Mark Complete Flow
- Always show confirmation dialog before marking complete — modal asking "Mark [LP name] as complete?" with details
- Portfolio upload is required to complete an LP — cannot mark complete without at least one portfolio file
- On success: toast notification AND the card updates in-place (badge changes to "Completed", progress bar fills)
- On error: inline danger alert below the Mark Complete button with error message — keeps context visible

### Data Loading
- Skeleton cards while progression data loads — gray placeholder cards mimicking the final layout
- After Mark Complete succeeds, progression history auto-refreshes — completed LP moves to history timeline seamlessly
- Portfolio upload shows a progress bar with percentage — important for large PDFs on slow connections

### Claude's Discretion
- When to load progression data (page load vs lazy on tab click) — decide based on existing page load patterns
- Exact skeleton card layout and placeholder design
- Error retry behavior for failed AJAX calls
- Collision warning modal button styling

</decisions>

<specifics>
## Specific Ideas

- Existing JS in `learner-progressions.js` already handles Mark Complete button and file validation — wire to new AJAX handler
- `ProgressionService::checkForActiveLPCollision()` already exists — wire to class capture form
- `learner-selection-table.js` already tracks assigned learners — extend with LP data column
- Collision log should integrate with existing audit patterns (if any) in the codebase

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 44-ajax-wiring-class-integration*
*Context gathered: 2026-02-18*
