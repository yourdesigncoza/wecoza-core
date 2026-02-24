---
phase: 52-class-activation-logic
plan: "01"
subsystem: Classes
tags: [database, migration, class-status, model, repository]
dependency_graph:
  requires: []
  provides: [class_status-column-DDL, wecoza_resolve_class_status, ClassModel-classStatus, ClassRepository-class_status]
  affects: [ClassModel, ClassRepository, functions.php]
tech_stack:
  added: []
  patterns: [centralized-status-fallback-helper, column-whitelisting]
key_files:
  created:
    - schema/class_status_migration.sql
  modified:
    - core/Helpers/functions.php
    - src/Classes/Models/ClassModel.php
    - src/Classes/Repositories/ClassRepository.php
decisions:
  - "wecoza_resolve_class_status() uses null-coalescing fallback: class_status ?? (empty(order_nr) ? draft : active) — ensures backward compatibility before DB migration runs"
  - "classStatus property defaults to 'draft' in ClassModel to match DB column default"
  - "getAllowedUpdateColumns() derives from getAllowedInsertColumns() — adding class_status to insert automatically propagates to update"
  - "getSingleClass() result array manually built from model getters — class_status added explicitly"
metrics:
  duration_seconds: 190
  tasks_completed: 2
  files_modified: 4
  completed_date: "2026-02-24"
---

# Phase 52 Plan 01: Class Status Foundation Summary

**One-liner:** DB migration SQL + `wecoza_resolve_class_status()` helper + ClassModel `classStatus` property + ClassRepository column whitelists — foundational layer for class activation lifecycle.

## What Was Built

The foundational layer for the class activation status system (WEC-179/WEC-180):

1. **`schema/class_status_migration.sql`** — Manual-execute DDL file that:
   - Adds `class_status VARCHAR(20) NOT NULL DEFAULT 'draft'` to `classes`
   - Backfills: `order_nr` present → `'active'`, empty/NULL → `'draft'`
   - Adds `CHECK (class_status IN ('draft', 'active', 'stopped'))` constraint
   - Creates `class_status_history` audit table with `class_id` FK and index
   - Includes reminder to also run `class_attendance_sessions.sql` if Phase 48 not yet applied

2. **`core/Helpers/functions.php`** — Added `wecoza_resolve_class_status(array $class): string`:
   - Returns `$class['class_status'] ?? (empty($class['order_nr']) ? 'draft' : 'active')`
   - Provides safe fallback during migration window when `class_status` column may not exist yet

3. **`src/Classes/Models/ClassModel.php`** — Updated:
   - Added `private string $classStatus = 'draft'` property
   - `hydrate()` calls `wecoza_resolve_class_status($data)` for fallback-safe reading
   - Rewrote `isDraft()`, `isActive()` to compare `$this->classStatus`
   - Added `isStopped()`, `isAttendanceAllowed()`, `getClassStatus()`, `setClassStatus()`
   - Added `class_status` to `save()`, `update()`, and `toArray()` data arrays
   - Code comment distinguishes `class_status` (lifecycle) from `stop_restart_dates` (schedule gaps)

4. **`src/Classes/Repositories/ClassRepository.php`** — Updated:
   - Added `class_status` to `getAllowedFilterColumns()`
   - Added `class_status` to `getAllowedInsertColumns()` (propagates to `getAllowedUpdateColumns()` automatically)
   - Added `c.class_status` to `getAllClasses()` SELECT query
   - Added `'class_status' => $classModel->getClassStatus()` to `getSingleClass()` result array

## Decisions Made

- **wecoza_resolve_class_status fallback pattern:** The null-coalescing `??` operator handles the migration window where `class_status` may be absent from data arrays fetched before the DB migration runs. This is CC1 from the research plan.
- **classStatus default = 'draft':** Matches the DB column default. New ClassModel instances not hydrated from DB start as draft, which is safe and correct.
- **No `getAllowedUpdateColumns()` change needed:** The method calls `getAllowedInsertColumns()` and diffs out `created_at` — adding to insert automatically propagates.
- **getSingleClass() explicit update:** This method manually builds the result array from model getters (not `toArray()`), so `class_status` had to be added explicitly.

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check

### Files Created/Modified

- [x] `schema/class_status_migration.sql` — FOUND
- [x] `core/Helpers/functions.php` — FOUND (modified)
- [x] `src/Classes/Models/ClassModel.php` — FOUND (modified)
- [x] `src/Classes/Repositories/ClassRepository.php` — FOUND (modified)

### Commits

- [x] c196260 — feat(52-01): create class_status DB migration SQL
- [x] 7817d9f — feat(52-01): add wecoza_resolve_class_status and update model/repository

## Self-Check: PASSED
