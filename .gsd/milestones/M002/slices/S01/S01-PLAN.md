# S01 — History Data Layer & Audit Service

**Goal:** Build the data-access layer that queries entity relationship history from existing tables, provides audit log write/read/purge primitives, and creates the `agent_class_history` DDL.

**Demo:** PHP integration tests prove HistoryRepository returns correct data for all 4 entities; AuditService writes, reads, and purges audit log entries; `agent_class_history` DDL is syntactically valid.

## Tasks

- [x] T01: HistoryRepository — entity relationship queries
- [x] T02: AuditService — audit log write/read/purge
- [x] T03: HistoryRepository extensions — additional queries for QA visits, portfolios, class notes, events

## Verification

- `php tests/History/HistoryServiceTest.php` — verifies all HistoryRepository methods return expected data shapes
- `php tests/History/AuditServiceTest.php` — verifies audit log write/read/purge with action codes
- Manual SQL verification that `agent_class_history` DDL is syntactically correct

## Observability / Diagnostics

- Runtime signals: `wecoza_log()` on audit log write failures
- Inspection surfaces: `wecoza_events.audit_log` table queryable directly; `AuditService::getEntityLog()` method
- Failure visibility: Exceptions from repository methods propagate with context; audit log failures logged but never block the parent operation
- Redaction constraints: No PII in audit log `message` field — action code + entity type + ID only, no field values
