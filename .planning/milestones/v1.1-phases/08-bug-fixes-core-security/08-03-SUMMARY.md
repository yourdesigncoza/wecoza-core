---
phase: 08-bug-fixes-core-security
plan: 03
subsystem: database
tags: [postgresql, security, logging, sanitization, sql-injection]

# Dependency graph
requires:
  - phase: 01-code-foundation
    provides: BaseRepository abstract class
provides:
  - quoteIdentifier() helper for PostgreSQL reserved word safety
  - wecoza_sanitize_exception() for secure error logging
  - wecoza_admin_exception_details() for admin-safe debugging
  - Pattern for sanitized exception logging across all repositories
affects: [all-repositories, logging, error-handling, 08-04, 08-05]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Exception message sanitization before logging (SEC-05)"
    - "PostgreSQL identifier quoting for reserved words (SEC-01)"

key-files:
  created: []
  modified:
    - "core/Abstract/BaseRepository.php"
    - "core/Helpers/functions.php"

key-decisions:
  - "Sanitize all exception messages before logging to prevent schema exposure"
  - "Use regex patterns to redact table/column names, SQL fragments, constraints"
  - "Provide separate admin-safe exception details for debugging"
  - "Truncate sanitized messages at 200 chars to prevent log flooding"

patterns-established:
  - "Always use wecoza_sanitize_exception() for exception logging"
  - "Use context parameter to identify source method/class"
  - "Check current_user_can('manage_options') before showing admin exception details"

# Metrics
duration: 2min
completed: 2026-02-02
---

# Phase 08 Plan 03: Security Helpers Summary

**PostgreSQL reserved word quoting and exception sanitization to prevent SQL errors and schema leakage in logs**

## Performance

- **Duration:** 2 minutes
- **Started:** 2026-02-02T15:12:33Z
- **Completed:** 2026-02-02T15:15:01Z
- **Tasks:** 3
- **Files modified:** 2

## Accomplishments
- BaseRepository provides quoteIdentifier() helper for safely quoting PostgreSQL identifiers
- Exception messages are sanitized before logging to prevent database schema exposure
- All error_log calls in BaseRepository updated to use sanitization pattern
- Admin-safe exception details available for debugging without exposing sensitive data

## Task Commits

Each task was committed atomically:

1. **Task 1: Add quoteIdentifier() helper to BaseRepository** - `75ebc5a` (feat)
2. **Task 2: Add exception sanitization helper functions** - `5e69262` (feat)
3. **Task 3: Update BaseRepository to use sanitized logging** - `891996f` (refactor)

## Files Created/Modified
- `core/Abstract/BaseRepository.php` - Added quoteIdentifier() method, updated all error_log calls to use wecoza_sanitize_exception()
- `core/Helpers/functions.php` - Added wecoza_sanitize_exception() and wecoza_admin_exception_details() helper functions

## Decisions Made

**1. Exception sanitization patterns**
- Decided to redact table.column references, column/table names, SQL fragments, constraints, indexes
- Rationale: These patterns catch most schema-exposing error messages while keeping logs useful

**2. Message truncation at 200 characters**
- Decided to cap sanitized messages at 200 chars
- Rationale: Prevents log flooding from verbose exception messages

**3. Context parameter for logging**
- Decided to require context string identifying the source method
- Rationale: Makes logs more useful for debugging without exposing schema details

**4. Separate admin exception details**
- Decided to provide wecoza_admin_exception_details() for administrator debugging
- Rationale: Admins need more detail than logs provide, but still sanitized

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - all tasks completed without issues.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

**Ready for:**
- Plans 08-04 and 08-05 can use these helpers immediately
- All repositories should adopt wecoza_sanitize_exception() pattern
- quoteIdentifier() available for repositories dealing with dynamic column names

**Pattern to follow:**
```php
catch (Exception $e) {
    error_log(wecoza_sanitize_exception($e->getMessage(), 'ClassName::methodName'));
    // handle error
}
```

**For admin debugging:**
```php
if (current_user_can('manage_options')) {
    $details = wecoza_admin_exception_details($e, 'ClassName::methodName');
    // show to admin
}
```

---
*Phase: 08-bug-fixes-core-security*
*Completed: 2026-02-02*
