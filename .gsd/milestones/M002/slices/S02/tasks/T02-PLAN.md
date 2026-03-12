# T02 — AJAX Endpoint, Audit Wiring, Cron Purge, Audit Log Shortcode

**Slice:** S02
**Milestone:** M002

## Goal
Create AJAX endpoint for entity history, wire AuditService into existing save handlers, register WP-Cron purge, and build audit log shortcode.

## Must-Haves

### Truths
- AJAX endpoint `wecoza_get_entity_history` accepts entity_type + entity_id, returns JSON timeline
- Endpoint requires nonce verification
- AuditService::log() is called from class status change handler
- WP-Cron event `wecoza_audit_purge` is registered on plugin activation
- `[wecoza_audit_log]` shortcode renders a filterable table of audit entries
- Shortcode supports `entity_type` attribute for filtering

### Artifacts
- `src/Classes/Ajax/HistoryAjaxHandlers.php` — AJAX handler
- `src/Classes/Shortcodes/AuditLogShortcode.php` — Shortcode class
- `views/components/audit-log-table.view.php` — Shortcode view
- Updated `wecoza-core.php` — Registration of AJAX, shortcode, and cron

## Steps
1. Create HistoryAjaxHandlers.php with endpoint
2. Create AuditLogShortcode.php
3. Create audit-log-table view
4. Wire AuditService into ClassStatusAjaxHandler
5. Register WP-Cron purge event
6. Register AJAX handlers and shortcode in wecoza-core.php
7. Verify with tests
