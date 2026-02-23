---
phase: 49-backend-logic
plan: 02
subsystem: service
tags: [attendance, service, schedule, hours-reversal, progression, crud]

# Dependency graph
requires:
  - phase: 49-01
    provides: AttendanceRepository with CRUD for class_attendance_sessions
  - phase: 48-foundation
    provides: class_attendance_sessions schema, learner_hours_log session_id/created_by columns
provides:
  - AttendanceService with five public methods: generateSessionList, validateSessionDate, captureAttendance, markException, deleteAndReverseHours
  - LearnerProgressionRepository::deleteHoursLogBySessionId for session-based hours log cleanup
affects: [49-03, attendance AJAX handlers, all downstream attendance UI]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Service class injects three repositories/services via constructor
    - Format-mapping pattern: perDayTimes (DB format) -> perDay + mode='per-day' (ScheduleService format)
    - Delegate pattern: captureAttendance/markException delegate to AttendanceRepository CRUD
    - Transaction pattern: deleteAndReverseHours uses $pdo->beginTransaction() for atomic delete
    - Error-tolerant loop: captureAttendance continues on per-learner logHours failures, collecting errors

key-files:
  created:
    - src/Classes/Services/AttendanceService.php
  modified:
    - src/Learners/Repositories/LearnerProgressionRepository.php

key-decisions:
  - "perDayTimes->perDay format mapping + mode='per-day' in generateSessionList before calling ScheduleService::generateScheduleEntries — handles both old start_time and new startTime key formats"
  - "captureAttendance uses try/catch inside foreach loop — per-learner logHours failures don't abort the whole capture (learner may have no in-progress LP)"
  - "deleteAndReverseHours wraps log deletion + session deletion in PDO transaction; recalculateHours is outside transaction (read-only recalc)"
  - "deleteHoursLogBySessionId added to LearnerProgressionRepository (not raw SQL in service) — all hours-log DB access stays in repository layer"
  - "endDate defaults to today if schedule has no end date — generates sessions up to now for attendance use-case"

patterns-established:
  - "Service accesses three collaborators via constructor injection: AttendanceRepository, ProgressionService, LearnerProgressionRepository"
  - "generateSessionList is the canonical schedule source — validateSessionDate, captureAttendance, markException all delegate to it"
  - "Exception types validated against whitelist array before any DB work"

requirements-completed: [BACK-05, BACK-06, SESS-01, SESS-02, SESS-04, SESS-05]

# Metrics
duration: ~10min
completed: 2026-02-23
---

# Phase 49 Plan 02: Backend Logic Summary

**AttendanceService with five public methods orchestrating ScheduleService, AttendanceRepository, ProgressionService, and LearnerProgressionRepository for complete attendance business logic**

## Performance

- **Duration:** ~10 min
- **Started:** 2026-02-23T12:50:15Z
- **Completed:** 2026-02-23T12:55:00Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- Created `AttendanceService` in namespace `WeCoza\Classes\Services` with constructor injection of three dependencies
- Implemented three private helpers: `getClassData`, `parseScheduleData`, `calculateHoursFromTimes`
- `generateSessionList`: reads schedule_data from DB, performs critical perDayTimes->perDay format mapping with mode='per-day' flag, delegates to `ScheduleService::generateScheduleEntries`, merges existing session records by date
- `validateSessionDate`: checks date membership in the generated schedule (delegates to `generateSessionList`)
- `captureAttendance`: validates date, handles existing pending sessions, creates/updates session record with status=captured, logs per-learner hours via `ProgressionService::logHours` with error-tolerant loop
- `markException`: validates exception type + date, creates/updates session with status=client_cancelled or agent_absent — zero learner hours logged (key business rule)
- `deleteAndReverseHours`: fetches session, branches on status, uses PDO transaction to atomically delete hours log + session, then recalculates LP accumulators for all affected tracking IDs
- Added `deleteHoursLogBySessionId` to `LearnerProgressionRepository` — SELECT DISTINCT tracking_ids before DELETE for clean reversal

## Task Commits

Each task was committed atomically:

1. **Task 1: Create AttendanceService with session generation and date validation** - `43fc1e5` (feat)
2. **Task 2: Add captureAttendance, markException, deleteAndReverseHours and repository helper** - `5814650` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified

- `src/Classes/Services/AttendanceService.php` — Business logic for attendance capture, exception marking, and hours reversal: 5 public methods, 3 private helpers, 476 lines
- `src/Learners/Repositories/LearnerProgressionRepository.php` — Added `deleteHoursLogBySessionId()` for session-based hours log cleanup (33 lines added)

## Decisions Made

- perDayTimes->perDay format mapping with mode='per-day' set explicitly — handles both old `start_time` and new `startTime` DB key formats AND ensures `getTimesForDay()` enters the per-day branch
- captureAttendance uses try/catch inside `foreach ($learnerHours as $learner)` — individual logHours failures (e.g., learner has no in-progress LP) are collected in `$errors` array but don't abort the capture
- deleteAndReverseHours wraps log deletion + session deletion in a PDO transaction for atomicity; recalculateHours calls are outside the transaction (they are read-only recalculations)
- `deleteHoursLogBySessionId` lives in `LearnerProgressionRepository` (not raw SQL in the service) — keeps all hours-log DB access in the repository layer, consistent with existing `logHours` and `getHoursLog` methods

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration or schema changes required.

## Next Phase Readiness

- `AttendanceService` is complete and provides all contracts needed by downstream AJAX handlers (Phase 49 Plan 03+)
- All five public methods implement the business rules from BACK-05, BACK-06, SESS-01, SESS-02, SESS-04, SESS-05
- `deleteHoursLogBySessionId` relies on `learner_hours_log.session_id` column added in Phase 48-02

## Self-Check: PASSED

- src/Classes/Services/AttendanceService.php: FOUND
- src/Learners/Repositories/LearnerProgressionRepository.php: FOUND
- 49-02-SUMMARY.md: FOUND
- Commit 43fc1e5 (Task 1): FOUND
- Commit 5814650 (Task 2): FOUND

---
*Phase: 49-backend-logic*
*Completed: 2026-02-23*
