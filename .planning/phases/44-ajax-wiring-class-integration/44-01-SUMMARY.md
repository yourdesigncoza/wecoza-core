---
phase: 44-ajax-wiring-class-integration
plan: 01
subsystem: api
tags: [ajax, wordpress, learner-progression, portfolio-upload, php]

# Dependency graph
requires:
  - phase: 44-context
    provides: "ProgressionService and PortfolioUploadService with correct namespaces"
provides:
  - "Four registered AJAX endpoints for LP progression operations"
  - "Shared validate_portfolio_file() DRY helper"
  - "ProgressionAjaxHandlers.php in WeCoza\\Learners\\Ajax namespace"
affects: [44-02, 44-03, frontend-js-progression]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Namespaced AJAX handlers loaded via require_once in wecoza-core.php + registered via add_action('init', __NAMESPACE__ . '\\register_...')"
    - "Shared DRY file validation helper within same namespace file"

key-files:
  created:
    - src/Learners/Ajax/ProgressionAjaxHandlers.php
  modified:
    - wecoza-core.php

key-decisions:
  - "Portfolio file is required (not optional) to mark LP as complete — enforces data integrity"
  - "validate_portfolio_file() is a shared namespace function (DRY) used by both mark-complete and portfolio-upload handlers"
  - "Collision acknowledgement uses wecoza_class_nonce (class form nonce) not learners_nonce — matches frontend usage context"

patterns-established:
  - "AJAX handler files: namespace WeCoza\\Learners\\Ajax, PSR-4 use statements, no require_once for services"
  - "File validation: wp_check_filetype_and_ext() + sanitize_file_name() as the WordPress-idiomatic approach"

requirements-completed: [AJAX-01, AJAX-02, AJAX-03, AJAX-04]

# Metrics
duration: 2min
completed: 2026-02-18
---

# Phase 44 Plan 01: Progression AJAX Handlers Summary

**Four namespaced AJAX endpoints (mark-complete, portfolio-upload, fetch-progressions, collision-log) wired to existing ProgressionService and PortfolioUploadService via WeCoza\Learners\Ajax namespace**

## Performance

- **Duration:** 2 min
- **Started:** 2026-02-18T19:46:29Z
- **Completed:** 2026-02-18T19:47:41Z
- **Tasks:** 2/2
- **Files modified:** 2

## Accomplishments

- Created `ProgressionAjaxHandlers.php` with four handler functions following the exact pattern of `LearnerAjaxHandlers.php`
- Extracted shared `validate_portfolio_file()` helper (DRY — used by both file-upload handlers, eliminates ~25 lines of duplication)
- Portfolio upload enforced as required for mark-complete per user decision
- All four AJAX actions registered in `wecoza-core.php` alongside the existing learner handlers

## Task Commits

Each task was committed atomically:

1. **Task 1: Create ProgressionAjaxHandlers.php** - `e1ced74` (feat)
2. **Task 2: Register in wecoza-core.php** - `dcc9c38` (feat)

**Plan metadata:** *(this commit)*

## Files Created/Modified

- `src/Learners/Ajax/ProgressionAjaxHandlers.php` — Four AJAX handlers + shared file validator + registration
- `wecoza-core.php` — Added require_once for ProgressionAjaxHandlers.php after LearnerAjaxHandlers.php

## Decisions Made

- Portfolio file is required (not optional) to complete an LP — prevents completion without evidence
- `validate_portfolio_file()` as a shared namespace-scoped function rather than duplicated inline code in two handlers
- `wecoza_class_nonce` used for collision acknowledgement handler since it originates from the class form context

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- All four AJAX endpoints (`mark_progression_complete`, `upload_progression_portfolio`, `get_learner_progressions`, `log_lp_collision_acknowledgement`) are registered and will respond to admin-ajax.php requests
- Frontend JS can now communicate with progression backend without page reload
- Plans 44-02 and 44-03 can proceed with class integration work

---
*Phase: 44-ajax-wiring-class-integration*
*Completed: 2026-02-18*
