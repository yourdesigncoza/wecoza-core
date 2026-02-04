# TODO

## Future Cleanup

### Clean up inactive class_change_logs references

**Added:** 2026-02-04
**Context:** Phase 13 dropped `class_change_logs` table. Features disabled in commits `1471589` and `3ededea`.

Remove or archive files that still reference the dropped table but have no active callers:

- [ ] `src/Events/Services/NotificationEnricher.php` (lines 131, 226)
- [ ] `src/Events/Services/NotificationEmailer.php` (line 106)
- [ ] `src/Events/Repositories/ClassChangeLogRepository.php` (entire class)
- [ ] `src/Events/CLI/AISummaryStatusCommand.php` (lines 77, 106)
- [ ] `src/Events/Shortcodes/AISummaryShortcode.php` (entire class)
- [ ] `tests/Events/AISummarizationTest.php` (line 681)

**Disabled Features:**
- `[wecoza_insert_update_ai_summary]` shortcode - registration disabled in `wecoza-core.php`
- `wp wecoza ai-summary status` CLI command - registration disabled in `wecoza-core.php`
- WP-Cron notification processing - hooks disabled in `wecoza-core.php`

**When:** Phase 16+ (when notification system is redesigned or removed entirely)