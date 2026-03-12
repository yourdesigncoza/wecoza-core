---
id: T02
parent: S02
milestone: M002
provides:
  - AJAX endpoint wecoza_get_entity_history for all 4 entity types
  - AuditLogShortcode [wecoza_audit_log] with entity type filtering and pagination
  - Audit log view template views/components/audit-log-table.view.php
  - Audit wiring in ClassStatusAjaxHandler (CLASS_STATUS_CHANGED)
  - WP-Cron wecoza_audit_log_purge scheduled weekly for 3-year retention
key_files:
  - src/Classes/Ajax/HistoryAjaxHandlers.php
  - src/Classes/Shortcodes/AuditLogShortcode.php
  - views/components/audit-log-table.view.php
  - src/Classes/Ajax/ClassStatusAjaxHandler.php
  - wecoza-core.php
key_decisions:
  - AJAX endpoint uses match() expression for entity type dispatch
  - Shortcode does not enforce access control — relies on WP page-level gatekeeping (D019)
  - Cron scheduled weekly with 36-month retention (D017)
  - Audit wiring is fire-and-forget after successful commit
patterns_established:
  - History AJAX endpoint pattern — nonce + entity_type + entity_id → timeline JSON
  - Audit log shortcode with URL-based pagination and entity type filter buttons
observability_surfaces:
  - AJAX endpoint returns structured {entity_type, entity_id, timeline} on success
  - Cron handler logs deleted count via wecoza_log()
  - Audit wiring wrapped in try/catch — never blocks class status updates
duration: 20m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T02: AJAX Endpoint, Audit Wiring, Cron Purge, Audit Log Shortcode

**Created AJAX endpoint for entity history, wired audit logging into class status changes, registered WP-Cron purge, and built audit log shortcode — 144 checks passing.**

## What Happened

1. **AJAX endpoint** (`src/Classes/Ajax/HistoryAjaxHandlers.php`): `wp_ajax_wecoza_get_entity_history` accepts entity_type + entity_id, verifies nonce, dispatches to HistoryService via `match()`, returns JSON timeline.

2. **Audit wiring** (`src/Classes/Ajax/ClassStatusAjaxHandler.php`): After successful class status transition commit, calls `AuditService::log('CLASS_STATUS_CHANGED', ...)`. Fire-and-forget — wrapped in try/catch, never blocks the response.

3. **WP-Cron purge** (`wecoza-core.php`): Scheduled `wecoza_audit_log_purge` event weekly on activation. Handler calls `AuditService::purgeOlderThan(36)`. Unscheduled on deactivation.

4. **Audit log shortcode** (`src/Classes/Shortcodes/AuditLogShortcode.php`): `[wecoza_audit_log]` renders a Bootstrap table with entity type filter buttons, pagination, user name resolution, and action code badges. View template at `views/components/audit-log-table.view.php`.

5. **Plugin registration** (`wecoza-core.php`): HistoryAjaxHandlers loaded via require_once, AuditLogShortcode registered via static register().

## Verification

- `php tests/History/HistoryServiceTest.php` — **101 passed, 0 failed**
- `php tests/History/AuditServiceTest.php` — **43 passed, 0 failed**
- All PHP files pass `php -l` syntax check
- `wecoza-core.php` parses without errors

## Files Created/Modified

- `src/Classes/Ajax/HistoryAjaxHandlers.php` — AJAX endpoint for entity history
- `src/Classes/Shortcodes/AuditLogShortcode.php` — Audit log shortcode
- `views/components/audit-log-table.view.php` — Audit log table view template
- `src/Classes/Ajax/ClassStatusAjaxHandler.php` — Added audit wiring after status change
- `wecoza-core.php` — Registration of AJAX, shortcode, cron event/handler
