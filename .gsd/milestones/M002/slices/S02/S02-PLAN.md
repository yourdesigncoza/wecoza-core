# S02 — History Service Facade, AJAX & Audit Wiring

**Goal:** Build HistoryService facade that merges S01 repository data into per-entity timeline arrays. Create AJAX endpoint for frontend consumption. Wire AuditService into class/learner save handlers. Register WP-Cron purge. Create audit log shortcode.

**Demo:** AJAX endpoint returns rich timeline JSON for all 4 entity types. Saving a class fires audit log entry. `[wecoza_audit_log]` shortcode renders a filterable audit log table. WP-Cron event is scheduled for 3-year purge.

## Must-Haves
- HistoryService with getClassTimeline(), getAgentTimeline(), getLearnerTimeline(), getClientTimeline()
- AJAX endpoint `wecoza_get_entity_history` returning entity-specific JSON
- AuditService::log() called from existing class save and learner save AJAX handlers
- `[wecoza_audit_log]` shortcode rendering filterable audit log table
- WP-Cron event registered for audit log 3-year purge

## Tasks

- [x] T01: HistoryService facade — unified timeline methods for all 4 entities
- [x] T02: AJAX endpoint, audit wiring, cron purge, and audit log shortcode

## Verification

- `php tests/History/HistoryServiceTest.php` — verifies HistoryService timeline methods
- `php tests/History/AuditServiceTest.php` — verifies audit wiring and purge
- Manual: AJAX endpoint responds to authenticated requests
- Manual: `[wecoza_audit_log]` shortcode renders on a page

## Observability / Diagnostics

- HistoryService methods return structured timeline arrays; empty for missing entities
- AJAX endpoint returns JSON with success/data/error shape
- Audit wiring uses try/catch — never blocks saves
- WP-Cron purge logs deleted count
