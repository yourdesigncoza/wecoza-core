# T02 — AuditService: Audit Log Write/Read/Purge

**Slice:** S01
**Milestone:** M002

## Goal
Create AuditService that writes high-level action-code entries to the existing `wecoza_events.audit_log` table, reads entries by entity, and purges entries beyond 3-year retention.

## Must-Haves

### Truths
- AuditService can write an audit log entry with action code, entity type, entity ID, and user ID
- AuditService can read audit entries filtered by entity type and entity ID, paginated
- AuditService can purge entries older than a given number of months
- Write failures are logged via wecoza_log() but never throw — audit must not block parent operations
- No PII in any audit log field — action codes only (e.g. CLASS_STATUS_CHANGED, LEARNER_ADDED)
- context JSONB stores entity_type and entity_id only, no field values

### Artifacts
- `src/Classes/Services/AuditService.php` — Service class with log(), getEntityLog(), purgeOlderThan()
- Updated `tests/History/AuditServiceTest.php` — Full test coverage

## Steps
1. Create AuditService class with constructor injection for PostgresConnection
2. Implement log(action, entityType, entityId, userId, context) — INSERT into wecoza_events.audit_log
3. Implement getEntityLog(entityType, entityId, limit, offset) — SELECT with pagination
4. Implement purgeOlderThan(months) — DELETE entries older than threshold
5. Wrap all writes in try/catch — log failures, never throw
6. Update AuditServiceTest.php with full verification
7. Run tests and verify

## Context
- Existing table: wecoza_events.audit_log (id, level, action, message, context JSONB, user_id, ip_address, user_agent, request_uri, created_at)
- D017: High-level only, entity+ID, no PII, 3-year retention
- D018: Action codes only, no field diffs
- D019: Admin-only access (shortcode gatekeeping, not enforced in service layer)
