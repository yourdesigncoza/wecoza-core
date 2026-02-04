---
status: resolved
trigger: "ai-summaries-missing-table"
created: 2026-02-04T10:30:00Z
updated: 2026-02-04T10:48:00Z
---

## Current Focus

hypothesis: CONFIRMED - Multiple code paths query class_change_logs table
test: Apply comprehensive fix to disable all entry points
expecting: All AI summary features disabled gracefully
next_action: Disable shortcode, CLI command, and repository methods

## Symptoms

expected: AI summaries should load without errors
actual: "Unable to load AI summaries" error displayed
errors: SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "class_change_logs" does not exist LINE 1: ...class_subject') AS class_subject, ai_summary FROM class_chan...
reproduction: User triggering AI summary load (likely via CLI command or dashboard)
started: After Phase 13 migration dropped class_change_logs table

## Eliminated

## Evidence

- timestamp: 2026-02-04T10:35:00Z
  checked: AISummaryStatusCommand.php lines 76-81, 102-110
  found: CLI command queries class_change_logs directly in fetchStatusCounts() and fetchModelBreakdown()
  implication: WP-CLI command 'wp wecoza ai-summary status' triggers the error

- timestamp: 2026-02-04T10:36:00Z
  checked: ClassChangeLogRepository.php lines 50, 97
  found: Repository methods exportLogs() and getLogsWithAISummary() both query class_change_logs
  implication: Repository is the data layer for the missing table

- timestamp: 2026-02-04T10:37:00Z
  checked: AISummaryShortcode.php line 82, AISummaryDisplayService.php lines 24-26
  found: Shortcode [wecoza_insert_update_ai_summary] -> service.getSummaries() -> repository.getLogsWithAISummary()
  implication: Shortcode is registered and active (wecoza-core.php line 212), will trigger error if used

- timestamp: 2026-02-04T10:38:00Z
  checked: wecoza-core.php lines 211-212, 514-515
  found: Both shortcode and CLI command are actively registered on plugins_loaded
  implication: Two active entry points that will fail with missing table error

## Resolution

root_cause: Phase 13 dropped class_change_logs table but AI summary feature (shortcode + CLI command) still actively queries it via ClassChangeLogRepository. Unlike notification hooks (previously disabled), these entry points are user-facing and need graceful disabling.
fix: Disabled shortcode registration (lines 211-214) and CLI command registration (lines 514-517) in wecoza-core.php with explanatory comments. Commented out registration calls rather than deleting to preserve intent and enable easy re-enabling if needed.
verification: PHP syntax check passed. Shortcode [wecoza_insert_update_ai_summary] will not be registered. CLI command 'wp wecoza ai-summary status' will not be available. No code execution paths remain that can trigger class_change_logs queries.
files_changed:
  - wecoza-core.php (lines 211-214, 514-517)
