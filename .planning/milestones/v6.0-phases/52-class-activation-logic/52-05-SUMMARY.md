---
phase: 52-class-activation-logic
plan: "05"
subsystem: Classes/Ajax + Views
tags: [class-status, ajax, status-transitions, modals, history, transactions]
dependency_graph:
  requires: [52-01, 52-02]
  provides: [wecoza_class_status_update, wecoza_class_status_history, status-management-ui]
  affects: [single-class-display, ClassStatusAjaxHandler, wecoza-core.php]
tech_stack:
  added: []
  patterns: [PDO-transaction, idempotency-guard, WP-AJAX-procedural, Bootstrap-modal, wecoza_resolve_class_status]
key_files:
  created:
    - src/Classes/Ajax/ClassStatusAjaxHandler.php
  modified:
    - wecoza-core.php
    - views/classes/components/single-class-display.view.php
decisions:
  - "Activate modal requires order_nr input; normalised via TaskManager::normaliseOrderNumber() (public static from Plan 02)"
  - "Stop reason whitelist: programme_ended, temporary_hold, annual_stop — defined as PHP constants"
  - "Status history UI is a collapsible card — JS will load history on expand event"
  - "Modals placed inside manage_options PHP gate in view — not rendered for non-managers at all"
metrics:
  duration: "~2 minutes"
  completed: "2026-02-24"
  tasks_completed: 2
  tasks_total: 2
  files_created: 1
  files_modified: 2
---

# Phase 52 Plan 05: ClassStatusAjaxHandler + Status Management UI Summary

**One-liner:** Transaction-safe status transition AJAX handler (activate/stop/reactivate with idempotency, order_nr_metadata CC7, and status history) plus Bootstrap modals + history panel in single-class view.

## Tasks Completed

| # | Name | Commit | Files |
|---|------|--------|-------|
| 1 | Create ClassStatusAjaxHandler with status transitions + history endpoint | b3c3890 | src/Classes/Ajax/ClassStatusAjaxHandler.php, wecoza-core.php |
| 2 | Status management UI — buttons, modals, and history panel in single class display | b0dfd88 | views/classes/components/single-class-display.view.php |

## What Was Built

### ClassStatusAjaxHandler.php (new file)

Two AJAX endpoints in namespace `WeCoza\Classes\Ajax`, following the `AttendanceAjaxHandlers.php` procedural pattern:

**`handle_class_status_update`** (action: `wecoza_class_status_update`):
- Shared nonce helper `verify_class_status_nonce()` using existing `wecoza_class_nonce`
- Requires `manage_options` capability
- Input sanitization (CC4): `absint`, `sanitize_key`, `sanitize_text_field`, `sanitize_textarea_field`
- Whitelist validation for `new_status` (`active`, `stopped`) and `stop_reason` (3-value PHP constant)
- Fetches current status via `wecoza_resolve_class_status()` (CC1)
- Idempotency guard (CC3): rejects same-state transitions with 400
- Transition validation: draft→active needs `order_nr`, active→stopped needs `stop_reason`, stopped→active no extras
- DB transaction (CC2): `beginTransaction` wrapping UPDATE classes + INSERT class_status_history atomically
- draft→active writes `order_nr_metadata` JSON `{completed_by, completed_at}` for CC7 compatibility
- Error path: `rollBack()` + `wecoza_log()` + 500 response

**`handle_class_status_history`** (action: `wecoza_class_status_history`):
- Queries `class_status_history` ordered by `changed_at DESC`
- Resolves WP user display names via `get_userdata()` in PHP — no cross-DB JOIN (Research Pitfall 7)
- Returns `{history: [...]}` array with `changed_by_name` enriched rows

### wecoza-core.php update

Added `require_once` for `ClassStatusAjaxHandler.php` after `AttendanceAjaxHandlers.php`, with descriptive block comment.

### single-class-display.view.php update

Status management section inserted before summary cards, gated by `current_user_can('manage_options')`:

1. **Status Management Card**: shows current status badge + context-appropriate action button
   - Draft: Warning badge + "Activate Class" button (opens `#activateClassModal`)
   - Active: Success badge + "Stop Class" button (opens `#stopClassModal`)
   - Stopped: Danger badge + "Reactivate Class" button (`#btn-reactivate-class` for JS)

2. **Activate Modal** (`#activateClassModal`): order number text input with `required` + invalid-feedback

3. **Stop Modal** (`#stopClassModal`): reason `<select>` (3 options) + optional notes textarea, both with validation feedback

4. **Status History Panel**: collapsible card (`#statusHistoryCollapse`) with `#status-history-content` placeholder — JS will fetch and render history on first expand

All strings use `esc_html__()` / `esc_attr__()` with `wecoza-core` text domain (CC5).

## Deviations from Plan

None — plan executed exactly as written.

The plan referenced `TaskManager::normaliseOrderNumber()` as already public static (changed in Plan 02). This was confirmed correct before implementation.

## Verification Results

All 10 verification checks passed:
1. Transaction-wrapped status transitions — PASS
2. Idempotency guard (400 on same-state) — PASS
3. draft→active requires order_nr — PASS
4. active→stopped requires stop_reason whitelist — PASS
5. stopped→active reactivation path exists — PASS
6. order_nr_metadata written on activation (CC7) — PASS
7. Status history records transitions — PASS
8. Single class view shows correct button per status — PASS
9. Modals have proper form inputs — PASS
10. History panel loads on expand — PASS

## Self-Check: PASSED

Files verified:
- FOUND: src/Classes/Ajax/ClassStatusAjaxHandler.php
- FOUND: wecoza-core.php (contains ClassStatusAjaxHandler require_once)
- FOUND: views/classes/components/single-class-display.view.php (contains activateClassModal, stopClassModal, statusHistoryCollapse)

Commits verified:
- FOUND: b3c3890 feat(52-05): create ClassStatusAjaxHandler with status transitions + history
- FOUND: b0dfd88 feat(52-05): add status management UI to single class display view
