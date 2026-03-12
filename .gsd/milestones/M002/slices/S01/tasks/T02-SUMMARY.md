---
id: T02
parent: S01
milestone: M002
provides:
  - AuditService with log(), getEntityLog(), getEntityLogCount(), getRecentLog(), purgeOlderThan()
  - 14 predefined action codes (D018)
  - Failure-suppressed writes (never throws)
  - Paginated reads with entity type filtering
  - Retention purge with configurable months
key_files:
  - src/Classes/Services/AuditService.php
  - tests/History/AuditServiceTest.php
key_decisions:
  - Action codes are string constants on the class, validated via isValidAction()
  - context JSONB stores entity_type + entity_id + safe extras — no PII
  - message format is "ACTION: entity_type #entity_id" for human readability
  - purgeOlderThan() uses validated integer in INTERVAL (PDO can't parameterize INTERVAL)
  - Request metadata (IP, user agent, URI) captured when WP functions available
patterns_established:
  - Constructor injection with null-coalescing for PostgresConnection (matches D005)
  - Try/catch with wecoza_log() fallback to error_log() for non-WP environments
  - Test cleanup with unique marker in context JSONB to isolate test entries
observability_surfaces:
  - wecoza_log() on write failures
  - getEntityLog() and getRecentLog() for inspecting audit state
  - purgeOlderThan() returns deleted count and logs it
duration: 20m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T02: AuditService — Audit Log Write/Read/Purge

**Created AuditService with action-code writes, paginated reads with entity filtering, and retention purge — 43 integration checks passing against live DB.**

## What Happened

Built `AuditService` in `src/Classes/Services/` with:

1. **`log(action, entityType, entityId, userId, extra)`** — Writes to `wecoza_events.audit_log`. Captures request metadata when available. Never throws — failures logged and suppressed per D017.

2. **`getEntityLog(entityType, entityId, limit, offset)`** — Reads audit entries for a specific entity, paginated, using JSONB context queries.

3. **`getEntityLogCount(entityType, entityId)`** — Count for pagination UI.

4. **`getRecentLog(limit, offset, filterEntityType)`** — Cross-entity recent log with optional type filter. Designed for the `[wecoza_audit_log]` shortcode (S02).

5. **`purgeOlderThan(months)`** — Deletes entries older than threshold. Returns deleted count. Defaults to 36 months (3 years per D017).

6. **`isValidAction(action)`** — Static validation against 14 predefined action codes.

Updated `tests/History/AuditServiceTest.php` with full lifecycle coverage: instantiation, method existence, action code validation, write/read/count/filter/pagination/purge, and test entry cleanup.

## Verification

- `php tests/History/AuditServiceTest.php` — **43 passed, 0 failed**
- `php tests/History/HistoryServiceTest.php` — **41 passed, 0 failed** (regression clean)

## Diagnostics

- `AuditService::log()` returns `false` on failure, never throws
- Write failures logged via `wecoza_log()` or `error_log()`
- `purgeOlderThan()` logs deleted count on success, logs error on failure

## Deviations

None.

## Known Issues

- `purgeOlderThan()` uses string interpolation for INTERVAL (PDO can't parameterize it) — integer is validated with `max(1, abs())` to prevent injection
- Request metadata (IP, user agent) only captured when WP `sanitize_text_field()` is available

## Files Created/Modified

- `src/Classes/Services/AuditService.php` — Audit log service with write/read/purge
- `tests/History/AuditServiceTest.php` — 43-check verification script (full rewrite from scaffold)
