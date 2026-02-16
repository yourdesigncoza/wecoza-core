---
phase: 40-return-type-hints-constants
plan: 01
subsystem: core
tags: [constants, magic-numbers, refactoring, maintainability]
requires: []
provides: [AppConstants, pagination-constants, timeout-constants]
affects: [core, learners, events, classes, clients, agents]
tech_stack:
  added: [AppConstants]
  patterns: [shared-constants, screaming-snake-case]
key_files:
  created: [core/Abstract/AppConstants.php]
  modified:
    - core/Abstract/BaseRepository.php
    - core/Abstract/BaseModel.php
    - src/Learners/Controllers/LearnerController.php
    - src/Learners/Services/LearnerService.php
    - src/Learners/Services/ProgressionService.php
    - src/Learners/Repositories/LearnerProgressionRepository.php
    - src/Learners/Models/LearnerModel.php
    - src/Events/Repositories/ClassEventRepository.php
    - src/Events/Repositories/MaterialTrackingRepository.php
    - src/Events/Services/NotificationDashboardService.php
    - src/Events/Services/MaterialTrackingDashboardService.php
    - src/Classes/Repositories/ClassRepository.php
    - src/Classes/Controllers/ClassController.php
    - src/Clients/Repositories/ClientRepository.php
    - src/Clients/Repositories/LocationRepository.php
    - src/Clients/Models/LocationsModel.php
    - src/Clients/Ajax/ClientAjaxHandlers.php
    - src/Clients/Services/ClientService.php
    - src/Clients/Controllers/ClientsController.php
    - src/Clients/Controllers/LocationsController.php
    - src/Agents/Repositories/AgentRepository.php
    - src/Agents/Ajax/AgentsAjaxHandlers.php
    - src/Agents/Services/AgentService.php
    - src/Agents/Controllers/AgentsController.php
decisions:
  - "Use SCREAMING_SNAKE_CASE for all constant names (PHP convention)"
  - "Do not touch Events module existing private const values (already correctly scoped)"
  - "Leave raw SQL LIMIT 10 in query strings unchanged (parameterization out of scope)"
  - "Leave validation length limits unchanged (domain rules, not magic numbers)"
metrics:
  duration_seconds: 550
  tasks_completed: 2
  files_modified: 25
  files_created: 1
  constants_defined: 9
  constants_references: 31
  completed_at: "2026-02-16T16:38:44Z"
---

# Phase 40 Plan 01: AppConstants Class & Magic Number Extraction Summary

**One-liner:** Created shared AppConstants class with SCREAMING_SNAKE_CASE constants for pagination (10/20/50/100) and replaced all magic number defaults across 25 files with named constant references.

## Objective

Eliminate magic numbers from business logic (CONST-01 through CONST-04) by creating a centralized AppConstants class and replacing all bare numeric pagination limits, timeouts, and bounds with named constants across all modules.

## Execution Summary

### Tasks Completed

#### Task 1: Create AppConstants Class
**Status:** Complete
**Commit:** 1d3c877

Created `core/Abstract/AppConstants.php` in namespace `WeCoza\Core\Abstract` with:
- **Pagination defaults:** DEFAULT_PAGE_SIZE (50), SEARCH_RESULT_LIMIT (10), SHORTCODE_DEFAULT_LIMIT (20), MAX_PAGE_SIZE (100)
- **Timeout values:** API_TIMEOUT_SECONDS (30), LOCK_TTL_SECONDS (120)
- **Progress limits:** PROGRESS_MAX_PERCENT (100)
- **Pagination bounds:** MIN_PAGE (1), MIN_PAGE_SIZE (1)

All constants use SCREAMING_SNAKE_CASE naming per PHP conventions.

**Verification:**
- `php -l` passed
- All 9 constants defined in SCREAMING_SNAKE_CASE
- Proper namespace and file guards

#### Task 2: Replace Magic Numbers Across All Modules
**Status:** Complete
**Commit:** 9d8f68f

Replaced magic numbers in 24 files across 6 modules:

**Core layer (2 files):**
- `BaseRepository::findAll()`, `findBy()`, `paginate()` defaults
- `BaseModel::getAll()` default

**Learners module (5 files):**
- LearnerController: line 105 (50 → DEFAULT_PAGE_SIZE), line 271 (10 → SEARCH_RESULT_LIMIT)
- LearnerService, ProgressionService: method signature defaults
- LearnerProgressionRepository, LearnerModel: pagination defaults

**Events module (4 files):**
- ClassEventRepository: 3 methods updated
- MaterialTrackingRepository: getTrackingDashboardData() default
- NotificationDashboardService: 2 methods updated
- MaterialTrackingDashboardService: fallback default

**Classes module (2 files):**
- ClassRepository: 2 locations updated (lines 496, 727)
- ClassController: shortcode default (line 375)

**Clients module (7 files):**
- ClientRepository, LocationRepository: method signature defaults
- LocationsModel: getAll() fallback
- ClientAjaxHandlers: fallback default (line 158)
- ClientService: searchClients() default
- ClientsController, LocationsController: shortcode defaults

**Agents module (4 files):**
- AgentRepository: query defaults array (line 274: 100 → MAX_PAGE_SIZE)
- AgentsController: shortcode default (line 159)
- AgentsAjaxHandlers: POST parameter fallback (line 72)
- AgentService: max bounds clamp (line 573: min(100) → min(MAX_PAGE_SIZE))

**Verification:**
- All files pass `php -l` syntax check
- `grep` verification confirms zero remaining magic numbers in method signatures
- 31 AppConstants references added across all modules
- Events module's existing `private const` values left untouched as intended
- Raw SQL `LIMIT 10` in query strings left unchanged (out of scope)

## Deviations from Plan

### Auto-fixed Issues

**None - plan executed exactly as written.**

## Outcomes

### What Was Built

1. **AppConstants class:** Single source of truth for pagination, timeouts, and bounds across entire codebase
2. **31 constant references:** Replaced all magic number pagination defaults with discoverable, named constants
3. **Zero bare numeric defaults:** All method signature defaults now reference AppConstants

### What Works Now

- **Discoverability:** Developers can find all pagination limits in one location (`core/Abstract/AppConstants.php`)
- **Maintainability:** Changing default page sizes requires updating only the constant definition
- **Consistency:** All modules use the same default values (50 for list views, 10 for search, 20 for shortcodes)
- **Type safety:** Constants are properly typed and cannot be accidentally reassigned
- **Backward compatibility:** No behavior changes—constants use the same values as previous magic numbers

### Integration Points

- **Core layer:** BaseRepository and BaseModel automatically provide constants to all extending classes
- **All modules:** Import `use WeCoza\Core\Abstract\AppConstants;` to access constants
- **Pattern established:** New code should use constants instead of bare numbers

## Verification

All success criteria met:

- [x] AppConstants class exists with pagination, timeout, and bounds constants
- [x] All method signature defaults reference AppConstants instead of bare numbers
- [x] Events module's existing class-level constants left untouched
- [x] Zero magic pagination numbers in method signatures (grep verification passes)
- [x] `php -l` passes on all modified files
- [x] 31+ AppConstants references confirmed
- [x] All constants in SCREAMING_SNAKE_CASE

## Technical Decisions

1. **SCREAMING_SNAKE_CASE:** Follows PHP constant naming conventions (unlike JavaScript/TypeScript)
2. **Public const:** Constants are stateless and should be accessible across all modules
3. **Preserved Events constants:** Existing `private const` values (LOCK_TTL, TIMEOUT_SECONDS) correctly scoped at class level—not moved to AppConstants
4. **Out of scope:**
   - Raw SQL `LIMIT 10` in query strings (would require parameterization)
   - Validation length limits (e.g., `max_length:50`—these are domain rules, not magic numbers)
   - Percentage calculations like `* 100` (mathematical identity, not a magic number)

## Files Modified

**Created (1):**
- `core/Abstract/AppConstants.php`

**Modified (24):**
- `core/Abstract/BaseRepository.php`
- `core/Abstract/BaseModel.php`
- `src/Learners/Controllers/LearnerController.php`
- `src/Learners/Services/LearnerService.php`
- `src/Learners/Services/ProgressionService.php`
- `src/Learners/Repositories/LearnerProgressionRepository.php`
- `src/Learners/Models/LearnerModel.php`
- `src/Events/Repositories/ClassEventRepository.php`
- `src/Events/Repositories/MaterialTrackingRepository.php`
- `src/Events/Services/NotificationDashboardService.php`
- `src/Events/Services/MaterialTrackingDashboardService.php`
- `src/Classes/Repositories/ClassRepository.php`
- `src/Classes/Controllers/ClassController.php`
- `src/Clients/Repositories/ClientRepository.php`
- `src/Clients/Repositories/LocationRepository.php`
- `src/Clients/Models/LocationsModel.php`
- `src/Clients/Ajax/ClientAjaxHandlers.php`
- `src/Clients/Services/ClientService.php`
- `src/Clients/Controllers/ClientsController.php`
- `src/Clients/Controllers/LocationsController.php`
- `src/Agents/Repositories/AgentRepository.php`
- `src/Agents/Ajax/AgentsAjaxHandlers.php`
- `src/Agents/Services/AgentService.php`
- `src/Agents/Controllers/AgentsController.php`

## Impact on Roadmap

**Phase 40 progress:** 1/2 plans complete
**Next:** 40-02 (return type hints for repositories and services)

This plan establishes the pattern for extracting magic numbers to named constants. The AppConstants class can be extended with additional constants as needed (e.g., MAX_FILE_SIZE, DEFAULT_CACHE_TTL).

## Self-Check

Verifying all claimed artifacts exist:

**Files created:**
- [x] `core/Abstract/AppConstants.php` exists and defines 9 constants

**Commits:**
- [x] 1d3c877: Create AppConstants class (confirmed)
- [x] 9d8f68f: Replace magic numbers (confirmed)

**Verification commands:**
```bash
# Confirm no magic numbers remain
grep -rn 'limit = 50\|limit = 20\|limit = 10' src/ core/ --include="*.php" | grep -v 'const \|LIMIT 10'
# Returns: empty (✓)

# Count AppConstants references
grep -rn 'AppConstants::' src/ core/ --include="*.php" | wc -l
# Returns: 31 (✓)

# Verify all constants in SCREAMING_SNAKE_CASE
grep -n 'public const' core/Abstract/AppConstants.php
# Returns: 9 constants, all SCREAMING_SNAKE_CASE (✓)
```

## Self-Check: PASSED

All files created, all commits exist, all verification checks pass.
