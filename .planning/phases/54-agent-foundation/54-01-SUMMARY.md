---
phase: 54-agent-foundation
plan: 01
subsystem: auth
tags: [wordpress, roles, capabilities, wp_agent, plugins_loaded]

# Dependency graph
requires: []
provides:
  - capture_attendance capability registered on wp_agent role via plugins_loaded priority 6
  - capture_attendance capability registered on administrator role via plugins_loaded priority 6
  - Idempotent capability registration that survives plugin updates
affects:
  - 54-02 (AJAX guards will use capture_attendance capability check)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "plugins_loaded priority 6 for post-init capability registration (after core init at priority 5, before dependent plugins at priority 10+)"
    - "add_cap() called without has_cap() guard — idempotent and follows existing codebase convention"

key-files:
  created: []
  modified:
    - wecoza-core.php

key-decisions:
  - "Priority 6 plugins_loaded hook chosen (not activation hook alone) so capabilities survive plugin updates without manual reactivation"
  - "No has_cap() guard — follows existing codebase convention (lines 762-771), add_cap() is idempotent at negligible cost"
  - "administrator role added alongside wp_agent — ensures existing admin capture workflows remain unbroken"

patterns-established:
  - "Post-init capability registration: use plugins_loaded priority 6, after core at priority 5"

requirements-completed: [AGT-01, AGT-02]

# Metrics
duration: 5min
completed: 2026-03-04
---

# Phase 54 Plan 01: Agent Foundation — Capability Registration Summary

**`capture_attendance` capability registered via `plugins_loaded` priority 6 on both `wp_agent` and `administrator` WP roles, surviving plugin updates without manual reactivation**

## Performance

- **Duration:** ~5 min
- **Started:** 2026-03-04T18:17:00Z
- **Completed:** 2026-03-04T18:22:00Z
- **Tasks:** 2 (Task 1 auto, Task 2 human-verify — automated check passed)
- **Files modified:** 1

## Accomplishments

- Added `plugins_loaded` hook at priority 6 registering `capture_attendance` on `wp_agent` role
- Added `plugins_loaded` hook at priority 6 registering `capture_attendance` on `administrator` role
- PHP syntax verified clean; WP-CLI functional check confirmed both roles report capability = YES
- Existing `wp_agent` capabilities (read, edit_posts, upload_files) remain intact

## Task Commits

Each task was committed atomically:

1. **Task 1: Add plugins_loaded capability registration** - `37441c7` (feat)
2. **Task 2: Verify capabilities are active** — automated check PASS, no separate commit needed

**Plan metadata:** _(pending docs commit)_

## Files Created/Modified

- `wecoza-core.php` — Added `plugins_loaded` priority-6 hook block (lines 704-733) registering `capture_attendance` on `wp_agent` and `administrator` roles

## Decisions Made

- Priority 6 chosen so the hook fires after core init (priority 5) but before dependent plugins (priority 10+)
- No `has_cap()` guard used — follows existing codebase convention; `add_cap()` only writes to DB when value changes, so cost is negligible
- `administrator` role included alongside `wp_agent` — ensures admins retain attendance capture access without relying on the activation hook

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- `capture_attendance` capability is live on both roles after any page load
- Plan 54-02 can now implement AJAX guards using `current_user_can('capture_attendance')`
- No blockers for Phase 54-02 continuation

---
*Phase: 54-agent-foundation*
*Completed: 2026-03-04*
