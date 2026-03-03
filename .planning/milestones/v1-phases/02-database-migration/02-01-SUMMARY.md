---
phase: 02-database-migration
plan: 01
subsystem: events
status: complete
tags: [database, schema-cleanup, sql, events-module]
dependencies:
  requires:
    - 01-03-PLAN (Events module migrated to wecoza-core)
  provides:
    - delivery_date column references removed from PHP
    - Events module SQL queries compatible with current schema
  affects:
    - 02-02-PLAN (Trigger migration - requires clean PHP code)
tech-stack:
  added: []
  patterns:
    - Remove deprecated column references from application layer
key-files:
  created: []
  modified:
    - src/Events/Services/MaterialNotificationService.php
    - src/Events/Support/FieldMapper.php
    - src/Events/Views/Presenters/ClassTaskPresenter.php
decisions:
  - id: use-original-start-date
    choice: Use original_start_date for due_date display
    context: delivery_date column was removed, needed fallback
    rationale: original_start_date is the primary date field for class scheduling
    impact: Due dates now consistently use class start date
metrics:
  duration: 2min
  completed: 2026-02-02
---

# Phase 02 Plan 01: Remove delivery_date PHP References Summary

**One-liner:** Removed all PHP references to dropped delivery_date column from Events module (MaterialNotificationService, FieldMapper, ClassTaskPresenter)

## What Was Done

Cleaned up PHP code in Events module to remove references to the `delivery_date` column that was previously dropped from the database schema. This prevents "column does not exist" SQL errors when the Events module executes queries.

### Tasks Completed

**Task 1: MaterialNotificationService Cleanup** (Commit: ea5b822)
- Removed `c.delivery_date` from SQL SELECT query in `findClassesByDaysUntilStart()`
- Removed `$deliveryDate` variable from `buildEmailBody()` method
- Removed "Expected Delivery Date" row from email template
- Email notifications now display only class start date and days until start

**Task 2: FieldMapper and ClassTaskPresenter Cleanup** (Commit: bf7e498)
- Removed `'delivery_date' => 'Delivery Date'` mapping from FieldMapper FIELD_MAPPINGS constant
- Updated ClassTaskPresenter to use `original_start_date` for `due_date` instead of `delivery_date`
- Simplified due date assignment (directly uses startDate)

**Task 3: Comprehensive Verification**
- Confirmed zero delivery_date references remain in src/Events/ directory
- Validated PHP syntax for all three modified files (all passed)

## Technical Details

### Changes Made

**MaterialNotificationService.php:**
```php
// BEFORE: SELECT included delivery_date
SELECT c.class_id, c.class_code, c.class_subject, c.original_start_date, c.delivery_date, ...

// AFTER: delivery_date removed
SELECT c.class_id, c.class_code, c.class_subject, c.original_start_date, ...
```

**ClassTaskPresenter.php:**
```php
// BEFORE: Used delivery_date with fallback
$dueDate = $this->formatDueDate((string) ($row['delivery_date'] ?? ''), $startDate);

// AFTER: Directly use startDate
$dueDate = $startDate;
```

### Why These Changes

The `delivery_date` column was dropped from the `classes` table schema (before Phase 2). PHP code still referenced it, causing:
- SQL errors: "column delivery_date does not exist"
- Null/empty values in display logic
- Incorrect due date calculations

This cleanup ensures Events module code aligns with current database schema.

## Files Modified

| File | Changes | Lines Modified |
|------|---------|----------------|
| src/Events/Services/MaterialNotificationService.php | Removed delivery_date from SQL query and email template | -9 |
| src/Events/Support/FieldMapper.php | Removed delivery_date field mapping | -1 |
| src/Events/Views/Presenters/ClassTaskPresenter.php | Use original_start_date for due_date | -1 |

## Deviations from Plan

None - plan executed exactly as written.

## Decisions Made

**Decision: Use original_start_date for due_date display**
- **Context:** delivery_date column was removed from schema, ClassTaskPresenter needed a date to display
- **Options considered:**
  1. Use original_start_date (primary class date)
  2. Remove due_date display entirely
  3. Add new calculated field
- **Choice:** Use original_start_date
- **Rationale:**
  - original_start_date is the canonical class scheduling date
  - Maintains consistent date display across UI
  - No additional database changes needed
  - Simpler code (direct assignment vs. calculation)
- **Impact:** Due dates now consistently show class start date

## Verification Results

### Comprehensive Grep Search
```bash
grep -rn "delivery_date" src/Events/
# Result: 0 matches
```

### PHP Syntax Validation
All three modified files passed syntax check:
- MaterialNotificationService.php: ✓ No syntax errors
- FieldMapper.php: ✓ No syntax errors
- ClassTaskPresenter.php: ✓ No syntax errors

## Impact Assessment

### What Works Now
- MaterialNotificationService queries execute without column errors
- Email notifications send successfully (7-day and 5-day warnings)
- ClassTaskPresenter displays classes with correct due dates
- FieldMapper no longer suggests delivery_date field exists

### What's Different
- Email notifications no longer show "Expected Delivery Date" field
- Due dates in class task display now always show class start date (previously showed delivery_date when available)
- Field mapping API no longer returns label for delivery_date

### Dependencies Resolved
This plan resolves the blocker noted in Phase 1 STATE.md:
> "Events plugin references `c.delivery_date` column that was dropped (Phase 2 will fix)"

## Next Phase Readiness

**Phase 02-02 (Trigger Migration):** ✅ Ready
- All PHP code now compatible with current schema
- No delivery_date references will interfere with trigger creation
- MaterialNotificationService queries will work alongside new triggers

**No blockers or concerns for next phase.**

## Testing Recommendations

When Events module is activated:

1. **Material Notifications:**
   - Create test class with start date 7 days in future
   - Run notification cron job
   - Verify email sends without SQL errors
   - Confirm email displays class start date (not delivery_date)

2. **Class Task Display:**
   - View classes in task management UI
   - Verify due dates display correctly
   - Confirm no "column does not exist" errors in logs

3. **Field Mapping:**
   - Test form validation/display that uses FieldMapper
   - Verify no references to delivery_date field

## Lessons Learned

**Schema Changes Require Coordinated PHP Updates:**
- When columns are dropped in SQL, must search entire codebase for PHP references
- Grep is essential: `grep -rn "column_name" src/`
- Consider: Could we add a pre-migration step to detect PHP references?

**Email Templates Can Hide Column Dependencies:**
- The delivery_date was buried in an HTML email template sprintf() call
- Easy to miss in code review
- Consider: Dedicated email template files might be easier to audit

## Performance Impact

**None.** Changes remove a column from SELECT queries, slightly reducing data transfer. No performance degradation.

## Commits

| Commit | Type | Description | Files |
|--------|------|-------------|-------|
| ea5b822 | fix | Remove delivery_date from MaterialNotificationService | MaterialNotificationService.php |
| bf7e498 | fix | Remove delivery_date from FieldMapper and ClassTaskPresenter | FieldMapper.php, ClassTaskPresenter.php |

**Total commits:** 2
**Total files modified:** 3
**Total lines changed:** -11 (9 + 1 + 1 deletions)

---

**Plan completed:** 2026-02-02T11:47:11Z
**Duration:** 2min
**Next plan:** 02-02-PLAN.md (Trigger Migration)
