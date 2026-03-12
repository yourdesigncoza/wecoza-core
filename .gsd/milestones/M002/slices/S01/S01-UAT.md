# S01: History Data Layer & Audit Service — UAT

## Quick Checks (Developer)

1. **Run test suites:**
   ```bash
   cd /opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core
   php tests/History/HistoryServiceTest.php   # Expect: 62 passed, 0 failed
   php tests/History/AuditServiceTest.php     # Expect: 43 passed, 0 failed
   ```

2. **Verify DDL file:**
   - Open `schema/agent_class_history.sql`
   - Confirm it has CREATE TABLE, 2 FOREIGN KEYs, CHECK constraint, 4 indexes
   - When ready, deploy via: `psql -U John -d wecoza_db -f schema/agent_class_history.sql`

3. **Spot-check audit log:**
   - After running AuditServiceTest, check `wecoza_events.audit_log` for any leftover test entries (there should be none — test cleans up)

## Notes
- S01 is data-layer only — no UI to visually test yet
- UI verification happens in S03/S04/S05
- The `agent_class_history` table needs manual deployment before S02 wiring can write to it
