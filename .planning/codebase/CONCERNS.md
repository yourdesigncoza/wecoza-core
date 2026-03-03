# Codebase Concerns

**Analysis Date:** 2026-03-03

## Tech Debt

**Oversized Repositories (1000+ LOC):**
- Issue: Multiple repository classes exceed 900 lines with complex multi-table joins and conditional logic
- Files: `src/Learners/Repositories/LearnerProgressionRepository.php` (951 LOC), `src/Agents/Models/AgentModel.php` (950 LOC), `src/Agents/Repositories/AgentRepository.php` (943 LOC), `src/Classes/Repositories/ClassRepository.php` (900 LOC)
- Impact: Difficult to test, maintain, and modify. Single responsibility principle violated. High cognitive load for future changes.
- Fix approach: Extract complex query builders into QueryBuilder classes. Split by domain (progression queries separate from agent queries). Create specific finder methods for each use case rather than monolithic `findAll()` with conditional filtering.

**Oversized Controllers and Services (600+ LOC):**
- Issue: Multiple service/controller classes exceed 600 lines with mixed business logic
- Files: `src/Classes/Services/ScheduleService.php` (624 LOC), `src/Agents/Services/AgentService.php` (614 LOC), `src/Events/Services/NotificationDashboardService.php` (612 LOC), `src/Classes/Controllers/QAController.php` (598 LOC)
- Impact: Violates single responsibility. Mixing different concerns (formatting, validation, business logic) makes testing and modifications risky.
- Fix approach: Extract formatting into dedicated presenters. Extract validation into separate validator classes. Move query logic to repositories. Each service should handle one domain concept only.

**Shortcode Logic Mixed with View Rendering (600+ LOC):**
- Issue: Shortcode classes contain both handler logic and full HTML rendering
- Files: `src/Learners/Shortcodes/learners-capture-shortcode.php` (533 LOC), `src/Events/Shortcodes/AISummaryShortcode.php` (597 LOC), `src/Events/Shortcodes/EventTasksShortcode.php` (674 LOC)
- Impact: Cannot reuse logic outside shortcode context. Hard to test rendering independently. Template changes require code changes.
- Fix approach: Extract view rendering into separate template files using `wecoza_view()`. Keep shortcode classes focused on data preparation and hook registration only.

**Monolithic Form Data Processor:**
- Issue: `src/Classes/Services/FormDataProcessor.php` (662 LOC) contains all form validation and transformation for class forms
- Files: `src/Classes/Services/FormDataProcessor.php`
- Impact: Single point of failure for form processing. Difficult to extend for new fields. Cannot unit test pieces independently.
- Fix approach: Create domain-specific form validators (ClassValidator, ScheduleValidator, etc.). Use composition instead of a god class. Separate input sanitization from business validation.

## Known Bugs

**LookupTable Capability Restriction (PENDING FIX):**
- Symptoms: Users cannot add new class types — AJAX request to admin-ajax.php returns 403 Forbidden
- Files: `src/LookupTables/Ajax/LookupTableAjaxHandler.php`
- Trigger: Any non-admin logged-in user attempts to create/update/delete lookup table entries (class types, SETA types, etc.)
- Root cause: Write handlers call `AjaxSecurity::requireAuth('lookup_table_nonce', $config['capability'])` where capability is `manage_options`. Non-admin users pass nonce check but fail capability check, receiving HTTP 403. Previous commit `ab3e9b8` removed this check from other modules (Progressions, Attendance, Clients) but missed LookupTables.
- Workaround: Only admins can manage lookup tables currently
- Fix status: Debug file created, fix approved but awaiting verification. Solution: Replace `requireAuth()` with `requireNonce()` only in handleCreate, handleUpdate, handleDelete to match pattern from `ab3e9b8`.

**FullCalendar Not Rendering on Display Single Class (PENDING FIX):**
- Symptoms: Calendar appears blank on single class detail page (class_id=12). No visible errors.
- Files: `assets/js/classes/single-class-display.js`, `views/classes/single-class-display.view.php`
- Trigger: Navigate to single class display page with `show_loading=true`
- Root cause: FullCalendar initialized synchronously at DOMContentLoaded on an element inside a `display:none` container. FullCalendar cannot calculate dimensions on hidden elements, renders with zero dimensions. Content revealed 500ms later via setTimeout, but calendar already rendered incorrectly.
- Workaround: Page refresh (hard refresh) sometimes helps; unclear when it works
- Fix status: Debug file created, fix approved but awaiting verification. Solution: Move `initializeClassCalendar()` call inside the setTimeout callback (after content visibility toggle) in single-class-display.js.

**Debug Comment in Learner Detail View:**
- Symptoms: Hardcoded debug comment in production code
- Files: `views/learners/components/learner-detail.php` line 50
- Issue: Comment references "DEBUG Include the raw learner data in the response Then print console log in AJAX function"
- Impact: Indicates incomplete debugging session left in code. May expose PII if enabled.
- Fix approach: Remove comment and investigate whether console logging is needed for normal operation.

## Security Considerations

**API Key Hardcoding Pattern:**
- Risk: OpenAI API key stored in WordPress options (`wecoza_openai_api_key`)
- Files: `src/Feedback/Services/AIFeedbackService.php`, `src/Events/Services/AISummaryService.php`
- Mitigation: Options stored in database, not in code. Accessed via `get_option()` only when needed.
- Recommendations: Implement option encryption layer. Consider using WordPress Secrets API (WP 6.2+) when available. Audit all places where API key is logged (ensure never logged in debug.log).

**Transaction Isolation Without Row Locking in Race Conditions:**
- Risk: Multiple concurrent updates to same database row could cause lost updates
- Files: Several locations use `beginTransaction()` but only `src/Classes/Ajax/ClassStatusAjaxHandler.php` uses `SELECT ... FOR UPDATE`
- Issue: Most transactions lack row-level locking. If two requests update the same record simultaneously, last write wins (lost update).
- Examples: `src/Classes/Services/AttendanceService.php` (beginTransaction without FOR UPDATE), `src/Learners/Repositories/LearnerProgressionRepository.php` (complex multi-table transaction)
- Mitigation: Row locking implemented in ClassStatusAjaxHandler
- Recommendations: Add `FOR UPDATE` to all transaction-protected SELECT queries. Ensure all status-changing operations use pessimistic locking.

**No Input Validation on Array Fields:**
- Risk: Complex array fields (class_notes, learners, schedule) use weak validation
- Files: `src/Classes/Services/FormDataProcessor.php` (lines 83-100), form processing shortcodes
- Issue: Validates individual array elements but no schema validation. Missing fields accepted silently, invalid array structures may cause undefined behavior.
- Impact: Potential for data corruption or application errors if frontend sends unexpected structure
- Fix approach: Implement strict schema validation before processing. Use type declarations or validation framework. Reject invalid structures rather than silently handling missing fields.

**File Upload MIME Type Validation:**
- Risk: Only checks MIME type from client header, not file content
- Files: `src/Classes/Services/UploadService.php` (lines 30-43)
- Issue: MIME types can be spoofed. A PHP file renamed to .pdf will pass MIME check but be executable if served from upload directory.
- Mitigation: `.htaccess` in upload dirs prevents PHP execution. `index.php` prevents directory listing.
- Recommendations: Add file magic number verification (finfo_open). Consider storing uploads outside webroot. Implement virus scanning for sensitive documents.

## Performance Bottlenecks

**Unindexed Query Patterns:**
- Problem: Multiple COUNT(*) queries on large tables without WHERE clause indexes
- Files: `src/Agents/Services/AgentDisplayService.php` (lines 43-58) - multiple COUNT(*) for dashboard
- Cause: Table statistics queried on every page load. Without indexes, counts require full table scan.
- Impact: Dashboard loads slowly as agent/learner databases grow
- Improvement path: Create indexes on status columns. Consider materializing counts in a separate cache/stats table. Cache results with short TTL (5-10 minutes) instead of querying every page load.

**Complex Multi-table LEFT JOINs in Hot Path:**
- Problem: `src/Learners/Repositories/LearnerProgressionRepository.php` baseQuery() (lines 42-60) performs 5-table JOIN on every progression query
- Impact: Joins learner_lp_tracking → class_type_subjects → learners → classes → clients. N+1 queries possible if called in loops.
- Improvement path: Add database indexes on all JOIN columns. Consider denormalizing frequently-accessed fields (learner_name, class_code) into main table. Implement query result caching for read-heavy operations.

**Absence of Pagination in Large List Queries:**
- Problem: Queries like `src/Clients/Repositories/LocationRepository.php` use hard LIMIT 10 with no offset/pagination
- Files: `src/Clients/Repositories/LocationRepository.php` (line 176), `src/Clients/Models/LocationsModel.php` (lines 157, 223)
- Impact: Cannot efficiently navigate large datasets. LIMIT 10 means max 10 results shown, pagination not implemented in UI.
- Improvement path: Implement cursor-based or offset pagination. Store last_offset in session. Add total count query with proper indexes.

**API Calls Without Caching:**
- Problem: `src/Feedback/Services/AIFeedbackService.php` calls OpenAI API on every feedback submission without caching
- Impact: Slow user experience. Unnecessary API costs for duplicate feedback patterns.
- Improvement path: Implement hash-based caching (fingerprint feedback text, check if similar feedback already processed). Cache AI responses with TTL (24 hours). Implement result deduplication for common patterns.

## Fragile Areas

**Calendar Initialization Logic (BRITTLE):**
- Files: `assets/js/classes/wecoza-calendar.js`, `assets/js/classes/single-class-display.js`
- Why fragile: Multiple initialization paths (single class vs. display all classes). Relies on DOM state timing (d-none visibility). Fallback CDN loading adds runtime uncertainty.
- Safe modification: Never initialize FullCalendar on hidden elements. Always defer to after visibility change. Test with network delays (throttle network to slow 3G in DevTools).
- Test coverage: No unit tests for calendar initialization. Manual testing only. Missing: tests for fallback CDN load, missing container handling, malformed event data handling.

**AI Enrichment with Malformed Responses (ERROR-PRONE):**
- Files: `src/Feedback/Services/AIFeedbackService.php` (lines 212-248)
- Why fragile: Parses JSON from AI response, falls back to defaults if parsing fails. JSON extraction tries to recover from extra text. Defaults to "clear" on parse failure, hiding vague feedback.
- Safe modification: Add comprehensive logging of parse failures. Validate response structure before fallback. Consider stricter response format requirements.
- Test coverage: No tests for malformed response scenarios. Tests exist but limited to happy path.

**Transaction Rollback Without Error Propagation (INCOMPLETE):**
- Files: `src/Classes/Services/AttendanceService.php` (lines 451-466), `src/Classes/Models/ClassModel.php` (lines 137-189)
- Why fragile: Catch blocks rollback but don't always re-throw. Some code paths continue after failed transaction.
- Example: `src/Classes/Services/AttendanceService.php` rolls back then throws, correct. `src/Learners/Repositories/LearnerProgressionRepository.php` rolls back without re-throwing in some paths.
- Safe modification: Always re-throw exceptions after rollback. Never silently continue after failed transaction. Log transaction rollback always.
- Test coverage: No integration tests verifying transaction rollback behavior. No tests for concurrent transaction scenarios.

**External Service Dependencies (CRITICAL):**
- Files: All Event/AI modules depend on: PostgreSQL, OpenAI API, Trello API
- Risk: Service failures cascade without circuit breaker pattern. API timeouts cause page hangs (30s default in `src/Feedback/Services/AIFeedbackService.php`).
- Safe modification: Implement timeout handling. Use async processing queue for API calls. Add circuit breaker for failing services.
- Test coverage: No mocked tests for API failures. Real API calls in tests slow down suite.

## Scaling Limits

**PostgreSQL Connection Per Request:**
- Current capacity: Single PDO connection per request. Lazy-loaded singleton.
- Limit: Connection pool size depends on PostgreSQL max_connections setting. Typical: 100-200. At high traffic, connection pool exhaustion causes "too many connections" errors.
- Scaling path: Implement PgBouncer or pgpool-II for connection pooling. Reduce connection hold time (close as soon as query completes). Migrate to managed database service with built-in pooling (DigitalOcean, AWS RDS, etc.).

**Synchronous API Calls Block Request:**
- Current: `src/Feedback/Services/AIFeedbackService.php` makes synchronous OpenAI API calls (30s timeout)
- Limit: Each request can only process one feedback submission at a time. At 100 concurrent users, response queue backs up.
- Scaling path: Move to async processing. Implement job queue (WordPress cron, Bull, RabbitMQ). Respond to user immediately, process in background. Use transient locks to prevent duplicate processing.

**Single-threaded JavaScript in Event Forms:**
- Current: `src/Events/Shortcodes/EventTasksShortcode.php` renders 100+ task items all at once in DOM
- Limit: Browser becomes unresponsive if 500+ tasks rendered. No pagination/virtualization.
- Scaling path: Implement virtual scrolling. Paginate large lists (50 items per page). Move heavy rendering to Web Workers.

## Dependencies at Risk

**OpenAI API Dependency (CRITICAL):**
- Risk: API key expiration, rate limiting, deprecation of models
- Impact: AI-powered feedback and summaries fail silently (default to no response). Trello integration stops posting issues.
- Current model: `gpt-4.1` hardcoded in `src/Feedback/Services/AIFeedbackService.php` and `src/Events/Services/AISummaryService.php`
- Migration plan: Abstract model choice into configuration. Implement provider abstraction layer (OpenAI, Anthropic, local LLM). Add fallback providers in priority order. Cache responses to reduce API calls.

**Tight Coupling to WordPress Specific Functions:**
- Risk: All code uses `get_option()`, `wp_ajax_*`, `wp_send_json_*` directly
- Impact: Cannot use code outside WordPress context. Difficult to migrate to headless CMS or different framework.
- Example: Every AJAX handler uses WordPress-specific hooks and functions
- Migration plan: Create adapter layer for WordPress functions. Allow dependency injection of config/event dispatcher. Isolate business logic from WordPress integration layer.

**PHP 8.0+ Only Features:**
- Risk: Code uses `match()` expressions, typed properties, named arguments
- Impact: Cannot run on PHP 7.4 or earlier. Tied to modern PHP versions.
- Files: Throughout codebase, especially `core/Database/PostgresConnection.php`, all models use typed properties
- Migration plan: Document PHP 8.0+ requirement clearly. Plan for PHP 8.3 upgrade when old versions EOL (PHP 8.0 EOL Nov 2023).

## Missing Critical Features

**No Rate Limiting on AJAX Handlers:**
- Problem: AJAX handlers lack rate limiting. No throttling on expensive operations.
- Blocks: Cannot prevent abuse. DDoS-vulnerable endpoints (class creation, learner upload, etc.)
- Recommendation: Implement rate limiting by user/IP. Use transient-based counters with WordPress functions.

**No Audit Trail / Change Log:**
- Problem: Critical data mutations (class status changes, learner progression state) not logged
- Blocks: Cannot answer "who changed this and when?" Cannot reverse accidental changes.
- Blocks: Compliance issues for training record accuracy. Cannot detect unauthorized modifications.
- Recommendation: Implement audit log table (actor, action, timestamp, old_value, new_value). Log to `audit_logs` table in PostgreSQL.

**No Bulk Operation Support:**
- Problem: All data import/export done one record at a time. Batch operations not supported.
- Blocks: Cannot efficiently import 1000+ learners. Progress tracking not visible during bulk operations.
- Blocks: User has no feedback that long operation is proceeding.
- Recommendation: Implement async bulk job processing with progress tracking. Store job state in transient. Use WordPress admin notices to show progress.

**No Data Validation Rules Engine:**
- Problem: Validation scattered across multiple helpers and validators
- Blocks: Cannot apply consistent validation rules across all modules.
- Blocks: Cannot dynamically change validation without code changes.
- Blocks: Difficult to test validation logic independently.
- Recommendation: Create centralized validation rules engine. Define rules in configuration. Allow extensions to add custom rules.

## Test Coverage Gaps

**Repositories: No Transaction Rollback Tests:**
- What's not tested: Behavior when database constraint violation occurs during transaction. Recovery from deadlock.
- Files: `src/Classes/Repositories/ClassRepository.php`, `src/Learners/Repositories/LearnerProgressionRepository.php`, `src/Classes/Services/AttendanceService.php`
- Risk: Rollback logic may not work correctly. Silent failures possible.
- Priority: High - Data integrity depends on correct transaction behavior

**AJAX Handlers: No Race Condition Tests:**
- What's not tested: Concurrent requests updating same record. Class status transitions under concurrent load.
- Files: `src/Classes/Ajax/ClassStatusAjaxHandler.php`, `src/Classes/Ajax/AttendanceAjaxHandlers.php`
- Risk: Race conditions in production only visible under load. Lost updates possible.
- Priority: High - Data accuracy depends on concurrent safety

**Form Processing: No Edge Case Tests:**
- What's not tested: Empty arrays, null values, missing required fields, invalid JSON in class_learners_data
- Files: `src/Classes/Services/FormDataProcessor.php`
- Risk: Malformed data causes undefined behavior. No clear error messages.
- Priority: Medium - Rare but impactful when occurs

**API Integration: No Failure Scenario Tests:**
- What's not tested: OpenAI API timeout, 429 rate limit response, malformed JSON responses
- Files: `src/Feedback/Services/AIFeedbackService.php`, `src/Events/Services/AISummaryService.php`
- Risk: API failures cause silent failures or exceptions. User experience undefined.
- Priority: High - External dependencies fail frequently

**Calendar Widget: No DOM/Timing Tests:**
- What's not tested: FullCalendar initialization on hidden element, network delays in fallback CDN load, missing container element
- Files: `assets/js/classes/wecoza-calendar.js`
- Risk: Blank calendar under certain timing conditions (as seen in BUG-37).
- Priority: High - User-facing feature frequently fails

---

*Concerns audit: 2026-03-03*
