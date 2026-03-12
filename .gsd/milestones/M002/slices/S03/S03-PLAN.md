# S03: Audit Trail Integration & Retention

**Goal:** Surface audit log entries in the UI (per-entity and admin-wide) and implement 3-year retention cleanup via WP-Cron.
**Demo:** Update a class or learner → see audit entry on the entity's History tab and in admin audit log view. WP-Cron job purges entries older than 3 years.

## Must-Haves

- Audit log section on each entity's History tab showing recent changes
- Admin audit log page listing all audit entries with filtering (entity type, date range, user)
- WP-Cron scheduled event for 3-year retention cleanup
- Manual purge capability via WP-CLI command

## Proof Level

- This slice proves: operational
- Real runtime required: yes
- Human/UAT required: yes (visual review of audit log display)

## Verification

- Browser-verify: update a class → History tab shows audit entry
- Browser-verify: admin audit log page lists entries with filtering
- `wp cron event run wecoza_audit_cleanup` — verify old entries purged
- `wp wecoza audit-purge --dry-run` — verify WP-CLI command works
- End-to-end: update learner → navigate to learner History → see "Record updated by [user] on [date]"

## Observability / Diagnostics

- Runtime signals: WP-Cron event logs count of purged entries via `wecoza_log()`
- Inspection surfaces: Admin audit log page; WP-CLI `audit-purge` command with `--dry-run`
- Failure visibility: Cron failure logged; WP-CLI command exits with error code on failure
- Redaction constraints: Audit entries contain entity type + ID only, no field values or PII

## Integration Closure

- Upstream surfaces consumed: `AuditService` (S01) for read/write/purge; History tab UI (S02) for displaying audit section
- New wiring introduced: WP-Cron event registration; WP-CLI command; admin menu page; audit section in history tabs
- What remains: nothing — this completes the milestone

## Tasks

- [ ] **T01: Audit log section in entity History tabs** `est:1h`
  - Why: Users need to see change history alongside relationship history on entity detail pages. Audit log entries are a section within the existing History tab.
  - Files: `views/components/history-audit-section.php`, `src/History/Ajax/HistoryAjaxHandler.php`, `src/History/Services/HistoryService.php`
  - Do: Add an "Activity Log" section to each entity's History tab data. `HistoryService` methods should include audit log entries for the entity (via `AuditService::getEntityLog()`). Create a view component for rendering audit entries (timestamp, user, action description). Display most recent 20 entries with "load more" pagination.
  - Verify: Update a class → navigate to class History tab → see audit entry in Activity Log section
  - Done when: All 4 entity History tabs include an Activity Log section showing audit entries

- [ ] **T02: Admin audit log page** `est:1.5h`
  - Why: Admin needs a central view of all audit activity across entities. Supports Mario's "high level" tracking requirement.
  - Files: `src/History/Admin/AuditLogPage.php`, `views/history/admin-audit-log.php`, `wecoza-core.php`
  - Do: Register a WordPress admin menu page under WeCoza settings. Display paginated audit log entries in a table (date, user, entity type, entity ID, action, message). Add filters: entity type dropdown, date range picker, user dropdown. Use `AuditService::getAll()` with filter/pagination params. Follow existing admin page patterns (e.g., `src/Events/Admin/SettingsPage.php`). Link entity IDs to their detail pages.
  - Verify: Navigate to WP Admin → WeCoza → Audit Log → see entries with working filters
  - Done when: Admin page renders audit entries with functional filtering and pagination

- [ ] **T03: WP-Cron retention cleanup & WP-CLI command** `est:1h`
  - Why: Mario specified 3-year retention. Need automated cleanup to prevent unbounded table growth. WP-CLI provides manual control.
  - Files: `src/History/Services/AuditService.php`, `src/History/CLI/AuditCommands.php`, `wecoza-core.php`
  - Do: Register WP-Cron event `wecoza_audit_cleanup` on plugin activation, scheduled daily. Handler calls `AuditService::purgeOlderThan(3)`. Log count of purged entries. Create WP-CLI command `wp wecoza audit-purge` with `--years=3` (default) and `--dry-run` flags. Register CLI command in plugin bootstrap. Unschedule cron on plugin deactivation.
  - Verify: `wp cron event list | grep wecoza_audit_cleanup` — event exists. `wp wecoza audit-purge --dry-run` — runs without error.
  - Done when: Cron event is registered and functional; WP-CLI command works with dry-run

- [ ] **T04: Final integration verification** `est:1h`
  - Why: Prove the complete pipeline works end-to-end: change → audit entry → visible in UI → retention works.
  - Files: (no new files — verification only)
  - Do: Browser test the full flow: (1) Update a class field via the class edit form. (2) Navigate to class detail → History tab → verify Activity Log shows the change. (3) Navigate to admin Audit Log page → verify the entry appears with correct filters. (4) Navigate to learner History tab → verify all history sections render correctly. (5) Run `wp wecoza audit-purge --dry-run` → verify output. (6) Check browser console for JS errors on all History tabs.
  - Verify: All 6 verification steps pass; `browser_assert` confirms no console errors
  - Done when: Complete audit trail pipeline verified end-to-end in browser

## Files Likely Touched

- `views/components/history-audit-section.php` (new)
- `src/History/Admin/AuditLogPage.php` (new)
- `views/history/admin-audit-log.php` (new)
- `src/History/CLI/AuditCommands.php` (new)
- `src/History/Ajax/HistoryAjaxHandler.php` (modified — add audit data)
- `src/History/Services/HistoryService.php` (modified — include audit entries)
- `src/History/Services/AuditService.php` (modified — add getAll with filters)
- `wecoza-core.php` (cron registration, CLI command registration, admin menu)
