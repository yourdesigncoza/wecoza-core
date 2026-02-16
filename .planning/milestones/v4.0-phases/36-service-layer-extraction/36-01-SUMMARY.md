---
phase: 36-service-layer-extraction
plan: 01
subsystem: learners
tags: [service-layer, mvc-refactor, architecture]
dependency-graph:
  requires: []
  provides: [LearnerService, service-layer-pattern]
  affects: [LearnerController, LearnerAjaxHandlers]
tech-stack:
  added: [LearnerService]
  patterns: [service-layer, thin-controller, validate-delegate-respond]
key-files:
  created:
    - src/Learners/Services/LearnerService.php
  modified:
    - src/Learners/Controllers/LearnerController.php
    - src/Learners/Ajax/LearnerAjaxHandlers.php
decisions:
  - "LearnerService follows ProgressionService pattern with lazy-loaded repository"
  - "Table row HTML generation belongs in service (business logic for field mapping and URL generation)"
  - "AJAX handlers in separate file maintain backward compatibility - no action name changes"
  - "Controller keeps AJAX handlers but delegates all business logic to service"
metrics:
  duration: 226
  tasks-completed: 2
  files-created: 1
  files-modified: 2
  lines-added: 330
  lines-removed: 243
  completed-date: 2026-02-16
---

# Phase 36 Plan 01: Service Layer Extraction - Learners Module

**One-liner:** Extracted all learner business logic into LearnerService with 13 methods, establishing service layer pattern with thin validate-delegate-respond controllers.

## Objective

Extract learner business logic from LearnerController and LearnerAjaxHandlers into a dedicated LearnerService class. This establishes the service layer pattern for the Learners module - controllers become thin routing layers, all CRUD orchestration, dropdown assembly, and data transformation logic moves to the service.

## Execution Summary

### Tasks Completed

| # | Task | Status | Commit |
|---|------|--------|--------|
| 1 | Create LearnerService with extracted business logic | ✅ Complete | 8895804 |
| 2 | Refactor LearnerController and LearnerAjaxHandlers to use LearnerService | ✅ Complete | 7c6355d |

### What Was Built

**LearnerService (298 lines, 13 public methods):**

**CRUD Operations:**
- `getLearner(int $id): ?LearnerModel` - Fetch single learner
- `getLearners(int $limit, int $offset): array` - Paginated learner list
- `getLearnersWithMappings(): array` - Full learner data with JOINs
- `getLearnerCount(): int` - Total count
- `createLearner(array $data): ?LearnerModel` - Create new
- `updateLearner(int $id, array $data): bool` - Update existing
- `deleteLearner(int $id): bool` - Delete learner

**Dropdown Data:**
- `getDropdownData(): array` - Assembles cities, provinces, qualifications, employers, placement levels (split N*/C*)

**Portfolio/Sponsor:**
- `savePortfolios(int $learnerId, array $files): array`
- `deletePortfolio(int $portfolioId): bool`
- `getSponsors(int $learnerId): array`
- `saveSponsors(int $learnerId, array $employerIds): bool`

**Presentation Logic:**
- `generateTableRowsHtml(array $learners): string` - Table row HTML generation (moved from LearnerAjaxHandlers)

**LearnerController refactor:**
- Removed `$repository` property → replaced with `$learnerService`
- Removed all 12 business logic methods
- AJAX handlers now validate-delegate-respond pattern (all under 50 lines)
- Shortcode renderers delegate to service
- Total reduction: 211 lines removed

**LearnerAjaxHandlers refactor:**
- Replaced `get_learner_controller()` with `get_learner_service()`
- Removed `generate_learner_table_rows()` function (moved to service)
- All 5 handlers delegate to service
- AJAX action names unchanged (backward compatible)
- Total reduction: 32 lines removed

### Backward Compatibility

All AJAX endpoints maintain identical signatures and response formats:
- `wp_ajax_update_learner`
- `wp_ajax_delete_learner`
- `wp_ajax_fetch_learners_data`
- `wp_ajax_fetch_learners_dropdown_data`
- `wp_ajax_delete_learner_portfolio`

No breaking changes to JavaScript frontend.

## Deviations from Plan

None - plan executed exactly as written.

## Key Decisions Made

1. **Service pattern consistency:** Followed ProgressionService pattern with lazy-loaded repository instantiation in constructor. Ensures consistency across all future services.

2. **Table row generation placement:** Moved `generate_learner_table_rows()` into LearnerService as `generateTableRowsHtml()`. While this is presentation-adjacent, it contains business logic (field mapping, URL generation, button HTML structure). Keeping it in the service maintains single responsibility - the AJAX handler just passes data.

3. **Separate AJAX handlers file:** Kept LearnerAjaxHandlers.php separate from LearnerController.php (not consolidated). This maintains backward compatibility and clear separation - the handler file uses legacy function-based approach for WordPress hooks.

4. **Import cleanup:** Removed unused `LearnerModel` and `LearnerRepository` imports from LearnerController. Controller now only imports `BaseController` and `LearnerService`.

## Testing Notes

**Manual verification required:**
1. Navigate to learner list page - verify table loads
2. Click "Edit" on a learner - verify form populates with dropdown data
3. Update learner details - verify save succeeds
4. Delete a learner - verify confirmation and deletion
5. Upload portfolio file - verify file saves
6. Check browser console - no JavaScript errors

**Success criteria verified:**
- All PHP syntax valid
- LearnerService exists with 13 methods
- LearnerController has no direct LearnerRepository usage
- No AJAX action names changed
- All handlers under 100 lines

## Technical Debt Created

None. This is a pure refactor with no new functionality.

## Next Steps

1. **Phase 36 Plan 02:** Extract Agent service (SVC-02)
2. **Phase 36 Plan 03:** Extract Client service (SVC-03)
3. **Phase 36 Plan 04:** Extract shared service utilities

## Files Changed

```
src/Learners/Services/LearnerService.php (created)
  + 298 lines
  + 13 public methods
  + CRUD operations, dropdown data, portfolio/sponsor, table HTML generation

src/Learners/Controllers/LearnerController.php (modified)
  - 211 lines removed (business logic extracted)
  + LearnerService dependency
  + Thin AJAX handlers (validate-delegate-respond)
  + Service delegation in shortcode renderers

src/Learners/Ajax/LearnerAjaxHandlers.php (modified)
  - 32 lines removed
  + get_learner_service() helper
  + All handlers delegate to LearnerService
  - generate_learner_table_rows() removed (moved to service)
```

## Commits

```
8895804 feat(36-01): create LearnerService with extracted business logic
7c6355d refactor(36-01): delegate business logic to LearnerService
```

## Self-Check: PASSED

Verified all claims:

**Files exist:**
```bash
✓ src/Learners/Services/LearnerService.php exists
✓ src/Learners/Controllers/LearnerController.php exists
✓ src/Learners/Ajax/LearnerAjaxHandlers.php exists
```

**Commits exist:**
```bash
✓ 8895804 found in git log
✓ 7c6355d found in git log
```

**Verification checks passed:**
```bash
✓ PHP syntax valid for all 3 files
✓ LearnerService class exists with 13 public methods
✓ LearnerController has no LearnerRepository usage
✓ LearnerController has no direct LearnerModel:: static calls
✓ All 5 AJAX action names unchanged
```

All claims verified successfully.
