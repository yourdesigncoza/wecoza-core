# S02: History Service Facade, AJAX & Audit Wiring — UAT

## Quick Checks (Developer)

1. **Run test suites:**
   ```bash
   cd /opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core
   php tests/History/HistoryServiceTest.php   # Expect: 101 passed, 0 failed
   php tests/History/AuditServiceTest.php     # Expect: 43 passed, 0 failed
   ```

2. **Verify AJAX endpoint (requires WP running):**
   - Open browser console on any WP page
   - POST to `admin-ajax.php` with action=wecoza_get_entity_history, entity_type=class, entity_id=21, nonce=valid
   - Should return JSON with timeline data

3. **Verify audit log shortcode:**
   - Create a WP page with content: `[wecoza_audit_log]`
   - Should show a table with audit log entries and entity type filter buttons
   - Trigger a class status change and verify an audit entry appears

4. **Verify cron registration:**
   - Check `wp cron event list` for `wecoza_audit_log_purge`
   - Should be scheduled weekly
