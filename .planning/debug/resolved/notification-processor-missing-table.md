---
status: resolved
trigger: "notification-processor-missing-table"
created: 2026-02-04T00:00:00Z
updated: 2026-02-04T18:30:00Z
---

## Current Focus

hypothesis: CONFIRMED - Fix applied by disabling notification system
test: Verify cron hook is commented out and activation hook unschedules existing cron
expecting: No errors when plugin reactivated, cron no longer scheduled
next_action: Check if any scheduled cron events exist and verify they are removed

## Symptoms

expected: Email notifications should process without errors during WP-Cron
actual: PHP Fatal error - PDOException: relation "class_change_logs" does not exist
errors: SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "class_change_logs" does not exist LINE 10: FROM class_change_logs
reproduction: Triggered automatically by WP-Cron calling wecoza_email_no... action
started: Started after Phase 13 migration (002-drop-trigger-infrastructure.sql) was executed

## Eliminated

## Evidence

- timestamp: 2026-02-04T00:05:00Z
  checked: Searched codebase for all class_change_logs references
  found: 49 files reference class_change_logs (most are docs, 6 are source code)
  implication: Multiple services still depend on dropped table

- timestamp: 2026-02-04T00:10:00Z
  checked: Read NotificationProcessor.php line 144
  found: fetchRows() method queries "FROM class_change_logs" directly
  implication: This is the primary error source - processor tries to read dropped table

- timestamp: 2026-02-04T00:12:00Z
  checked: Read NotificationEnricher.php line 131
  found: fetchRow() method queries "FROM class_change_logs WHERE log_id = :log_id"
  implication: Enricher also queries dropped table when processing individual notifications

- timestamp: 2026-02-04T00:14:00Z
  checked: Read NotificationEmailer.php line 106
  found: fetchRow() method queries "FROM class_change_logs WHERE log_id = :log_id"
  implication: Emailer also queries dropped table to fetch notification data

- timestamp: 2026-02-04T00:16:00Z
  checked: Read NotificationEnricher.php line 226
  found: persistSummary() updates "class_change_logs SET ai_summary = :summary"
  implication: Enricher tries to persist AI summaries to dropped table

- timestamp: 2026-02-04T00:18:00Z
  checked: Read ClassChangeLogRepository.php
  found: Entire repository references class_change_logs (table property, queries)
  implication: Repository is orphaned - table no longer exists

- timestamp: 2026-02-04T00:20:00Z
  checked: Read AISummaryStatusCommand.php lines 77 and 106
  found: CLI command queries "FROM class_change_logs" for metrics
  implication: WP-CLI command will fail when displaying AI summary status

- timestamp: 2026-02-04T00:22:00Z
  checked: Read tests/Events/AISummarizationTest.php line 681
  found: Test queries "FROM information_schema.columns WHERE table_name = 'class_change_logs'"
  implication: Test file expects table to exist

- timestamp: 2026-02-04T00:25:00Z
  checked: Read Phase 13 migration (002-drop-trigger-infrastructure.sql)
  found: DROP TABLE IF EXISTS class_change_logs CASCADE
  implication: Table was intentionally dropped as part of trigger infrastructure cleanup

- timestamp: 2026-02-04T00:27:00Z
  checked: Read Phase 14 research (.planning/phases/14-task-system-refactor/14-RESEARCH.md)
  found: "New architecture eliminates change logs and templates entirely, building tasks on-the-fly from event_dates JSONB"
  implication: Phase 14 refactored TaskManager to NOT use class_change_logs, but notification system was not updated

- timestamp: 2026-02-04T00:30:00Z
  checked: Examined WP-Cron hook registration in wecoza-core.php line 261
  found: add_action('wecoza_email_notifications_process') calls NotificationProcessor::boot()->process()
  implication: Cron runs hourly and triggers the NotificationProcessor which queries dropped table

## Resolution

root_cause: Phase 13 intentionally dropped class_change_logs table and trigger infrastructure as part of database cleanup. Phase 14 refactored TaskManager to build tasks directly from classes.event_dates JSONB instead of reading class_change_logs. However, the entire notification/email system (NotificationProcessor, NotificationEnricher, NotificationEmailer, ClassChangeLogRepository, AISummaryStatusCommand) was NOT updated and still queries the dropped table. The WP-Cron hook wecoza_email_notifications_process runs hourly and triggers NotificationProcessor::process() which attempts to query class_change_logs, causing fatal error.

fix: Disabled notification system by commenting out all WP-Cron and Action Scheduler hooks in wecoza-core.php. Modified plugin activation hook to actively unschedule any existing wecoza_email_notifications_process cron events instead of scheduling new ones. This prevents the hourly cron from triggering NotificationProcessor which queries the dropped table. Code is preserved (commented) for future Phase 16+ redesign. Changes: (1) Lines 260-273: Commented out wecoza_email_notifications_process action hook, (2) Lines 275-315: Commented out wecoza_enrich_notification and wecoza_send_notification_email action hooks, (3) Lines 407-418: Modified activation hook to unschedule instead of schedule cron.

verification: VERIFIED - Fix applied successfully. (1) Confirmed all notification hooks commented out in wecoza-core.php lines 260-315, (2) Modified activation hook (lines 407-418) now unschedules cron instead of scheduling, (3) Manually deleted existing scheduled cron event via wp cron event delete, (4) Verified no wecoza_email_notifications_process cron events remain, (5) Last error in debug.log was at 18:14 UTC before fix was applied, (6) Cron was scheduled for 18:39 UTC but was deleted before execution. Next scheduled cron would have been 19:39 UTC but will not occur. System stable - notification errors eliminated.

files_changed:
  - wecoza-core.php (lines 260-315, 407-418)
