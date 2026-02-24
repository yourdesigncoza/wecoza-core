---
phase: 52-class-activation-logic
plan: "02"
subsystem: events
tags: [task-manager, class-status, auto-activate, event-tasks, badge, dry]
dependency_graph:
  requires: [52-01]
  provides: [auto-activate-on-order-nr, class-status-badge-in-event-tasks, normalise-order-number-public]
  affects: [event-tasks-listing, task-completion-flow]
tech_stack:
  added: []
  patterns: [postgresql-case-expression, public-static-method-for-dry-reuse, phoenix-badge-three-way]
key_files:
  modified:
    - src/Events/Services/TaskManager.php
    - src/Events/Repositories/ClassTaskRepository.php
    - src/Events/Views/Presenters/ClassTaskPresenter.php
    - views/events/event-tasks/main.php
decisions:
  - "normaliseOrderNumber() changed to public static — enables ClassStatusAjaxHandler (Plan 05) to reuse without duplication"
  - "Separate :order_nr_check PDO param used for CASE expression — PDO cannot bind same parameter name twice in one statement"
  - "wecoza_resolve_class_status() used in badge formatter for migration-window NULL safety"
  - "Class status badge appended within existing status cell — avoids adding table column"
metrics:
  duration: "~3 minutes"
  completed: "2026-02-24"
  tasks_completed: 2
  files_modified: 4
---

# Phase 52 Plan 02: TaskManager Auto-Activate + Event Tasks Status Badge Summary

**One-liner:** Auto-activates draft classes when order_nr assigned (CASE SQL), adds three-way Draft/Active/Stopped Phoenix badge to event tasks listing, and exposes `normaliseOrderNumber()` as `public static` for DRY reuse.

## What Was Built

### Task 1: TaskManager — auto-activate on order_nr + public static normaliseOrderNumber

Three changes to `src/Events/Services/TaskManager.php`:

**A. `normaliseOrderNumber()` visibility change:**
- Changed from `private function` to `public static function`
- Updated internal call in `completeAgentOrderTask()` from `$this->normaliseOrderNumber()` to `self::normaliseOrderNumber()`
- Enables `ClassStatusAjaxHandler` (Plan 05) to call `TaskManager::normaliseOrderNumber()` per DRY principle

**B. `updateClassOrderNumber()` SQL update:**
- Added `class_status = CASE WHEN class_status = 'draft' AND :order_nr_check != '' THEN 'active' ELSE class_status END` to the UPDATE statement
- Draft classes receiving a non-empty order_nr auto-transition to `active`
- Clearing order_nr (reopening task) does not revert status — class stays at current status
- Uses separate `:order_nr_check` parameter (PDO cannot bind `:order_nr` twice in same statement)

**C. `fetchClassById()` SELECT expanded:**
- Added `class_status` to SELECT list alongside existing fields
- Required so callers can read class_status after order_nr updates

### Task 2: Event tasks listing — class status badge

**ClassTaskRepository** (`src/Events/Repositories/ClassTaskRepository.php`):
- Added `c.class_status` to `fetchClasses()` SELECT list — makes status available to presenter

**ClassTaskPresenter** (`src/Events/Views/Presenters/ClassTaskPresenter.php`):
- Added `formatClassStatusBadge(array $row): array` private method
- Uses `wecoza_resolve_class_status($row)` for migration-window NULL safety (fallback: non-empty order_nr = active)
- Returns `['class', 'label', 'icon']` shape matching plan spec:
  - `active` → badge-phoenix-success / Active / bi-check-circle
  - `stopped` → badge-phoenix-danger / Stopped / bi-stop-circle
  - default → badge-phoenix-warning / Draft / bi-file-earmark-text
- Added `'class_status_badge'` key to `formatClassRow()` output array

**Event tasks view** (`views/events/event-tasks/main.php`):
- Added class status badge within the existing `data-role="status-cell"` td
- Rendered with `<?php if (!empty($class['class_status_badge'])): ?>` guard
- Uses Phoenix badge system with icon span — matches existing badge patterns
- No changes to task completion logic, manageable flag, or any task interaction

## Decisions Made

1. **`normaliseOrderNumber()` → `public static`** — minimal visibility change to enable DRY reuse in Plan 05's ClassStatusAjaxHandler. Alternative (duplicating 10-line normalization logic) violates DRY principle.

2. **`:order_nr_check` separate PDO parameter** — PDO named parameters cannot be bound twice under the same name in a single prepared statement. The CASE expression's condition needs to reference the same value as `:order_nr`, requiring a duplicate binding under a distinct name.

3. **`wecoza_resolve_class_status()` in badge formatter** — migration-window compatibility: `class_status` column may be NULL on existing classes until the DB backfill from Plan 01 is applied. The helper falls back to `order_nr` presence check, preventing null-related display errors during transition.

4. **Badge appended in existing status cell** — avoids adding a new table column to the event tasks table header/body, keeping the layout unchanged while adding the new visual indicator.

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check

### Created files exist

No new files created — only modifications to existing files.

### Commits exist

- `998b82f` — feat(52-02): TaskManager auto-activate on order_nr + public static normaliseOrderNumber
- `56a1629` — feat(52-02): add class status badge to event tasks listing

## Self-Check: PASSED
