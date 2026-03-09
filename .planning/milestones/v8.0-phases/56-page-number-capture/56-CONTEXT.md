# Phase 56: Page Number Capture - Context

**Gathered:** 2026-03-09
**Status:** Ready for planning
**Source:** Mario's WEC-184 feedback + confirmed decisions

<domain>
## Phase Boundary

Add a "Last Completed Page" field to the attendance capture modal, one per learner row. Field is required. Persists per learner per session in the database. Displayed when viewing previously captured sessions.

This phase is capture-only — no progression calculation (that's Phase 57).
</domain>

<decisions>
## Implementation Decisions

### Field Semantics
- Field label: "Last Completed Page" (not "Current Page")
- Tracks the last page the learner finished, not the page they're working on
- Integer value (page number)

### Field Behavior
- One field per learner row in the attendance capture modal
- **Required** — attendance cannot be submitted without a page number for every learner
- Starts **blank** each session — no pre-fill, no hint from previous session's value
- No previous page number displayed as reference

### Data Storage
- Page number stored per learner per session alongside hours data
- Uses existing attendance data structure (likely `learner_data` JSONB in `class_events` or similar)

### Display
- Previously captured sessions show the recorded page number in the view modal

### Claude's Discretion
- Exact column/field storage approach (new column vs JSONB field in existing learner_data)
- Validation type (positive integer, min 1, max TBD)
- UI placement within the learner row (after hours fields is logical)
- Error messaging for missing page numbers
</decisions>

<specifics>
## Specific Ideas

- Builds on v6.0/v7.0 attendance infrastructure (AttendanceService, capture modal, AJAX handlers)
- All classes use workbooks — no conditional logic needed for "workbook vs non-workbook" classes
- Field should integrate naturally with existing hours_present/hours_absent inputs in the learner row
</specifics>

<deferred>
## Deferred Ideas

- Total pages per module lookup (`class_type_subjects.total_pages`) — Phase 57
- Page progression percentage calculation — Phase 57
- CSV report extraction — Phase 58
- Target page progression (TPAG-01..03) — future milestone
</deferred>

---

*Phase: 56-page-number-capture*
*Context gathered: 2026-03-09 from WEC-184 confirmed decisions*
