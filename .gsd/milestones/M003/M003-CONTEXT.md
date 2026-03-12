# M003: Excessive Hours Report — Context

**Gathered:** 2026-03-12
**Status:** Ready for execution

## Project Description

Build a live excessive-hours detection dashboard that flags learners whose `hours_trained` exceeds `subject_duration` for applicable programme types, with a resolution workflow for admin staff.

## Why This Milestone

Mario (client) needs visibility into learners training beyond allocated hours — a leading indicator of poor attendance, stuck learners, or facilitator issues. Currently no mechanism exists to surface this. Excessive hours replace the originally-planned non-progression report (WEC-187).

## User-Visible Outcome

### When this milestone is complete, the user can:

- Visit a dashboard page showing all learners with excessive hours, filterable by client/programme/status
- Resolve flagged items by recording an action taken (contacted facilitator, QA visit, other) with notes
- See a count of excessive-hours flags on the System Pulse dashboard
- Resolved items resurface after 30 days if the learner is still over allocated hours

### Entry point / environment

- Entry point: WordPress shortcode `[wecoza_excessive_hours_report]` on admin dashboard
- Environment: Browser, WordPress admin area
- Live dependencies: PostgreSQL database

## Completion Class

- Contract complete means: Repository queries return correct flagged learners, resolution CRUD works
- Integration complete means: Shortcode renders live data, AJAX resolve works end-to-end, SystemPulse shows count
- Operational complete means: N/A (no cron, no background jobs)

## Final Integrated Acceptance

To call this milestone complete, we must prove:

- Dashboard loads with real excessive-hours data from the database
- Resolving a flag persists and shows as resolved in the UI
- A resolved flag resurfaces after 30 days if learner is still over hours
- SystemPulse attention items include the excessive hours count

## Risks and Unknowns

- Data volume: unclear how many in_progress LPs currently exceed hours — low risk, query is bounded

## Existing Codebase / Prior Art

- `src/Learners/Models/LearnerProgressionModel.php` — has `isHoursComplete()`, `getProgressPercentage()`
- `src/Learners/Repositories/LearnerProgressionRepository.php` — 6-table JOIN base query pattern
- `src/Learners/Services/ProgressionService.php` — `getProgressionsForAdmin()` filtered admin query pattern
- `src/Events/Shortcodes/SystemPulseShortcode.php` — `gatherAttentionItems()` attention items
- `src/Learners/Shortcodes/progression-report-shortcode.php` — shortcode + AJAX DataTable pattern
- `core/Helpers/AjaxSecurity.php` — nonce/capability/sanitize pattern

## Relevant Requirements

- WEC-187 — Excessive Hours & Non-Progression Reports (non-progression dropped per Mario)

## Scope

### In Scope

- Excessive hours detection via live query
- Resolution tracking (action + notes + who)
- Dashboard UI with DataTable, filters, inline resolve
- SystemPulse integration (attention item count)

### Out of Scope / Non-Goals

- Non-progression report (dropped by Mario)
- Email/notification pipeline for excessive hours
- WP-Cron scheduled checks
- EventType enum changes

## Technical Constraints

- PostgreSQL (not MySQL)
- No WP-Cron — live query only
- AJAX DataTable for data loading (not server-rendered)
- CSS in ydcoza-styles.css only
- 30-day rolling resolution window (not calendar month)

## Integration Points

- `SystemPulseShortcode` — add attention item count (lightweight COUNT query)
- `wecoza-core.php` — register shortcode, autoload namespace
