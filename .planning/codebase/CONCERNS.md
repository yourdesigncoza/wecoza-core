# Codebase Concerns

**Analysis Date:** 2026-02-02

## Tech Debt

**Large View Files and Complexity:**
- Issue: Several view files exceed 2400 lines, creating maintainability challenges and making refactoring difficult
- Files: `views/classes/components/class-capture-partials/update-class.php` (2544 lines), `views/classes/components/class-capture-partials/create-class.php` (958 lines)
- Impact: Difficult to modify forms without risking regressions; hard to reuse components; increased cognitive load for developers
- Fix approach: Break into smaller, focused view components; extract reusable partials for form sections (learner selection, schedule, agent assignments); create component library with clear single-responsibility

**Large Repository Classes:**
- Issue: `ClassRepository.php` (877 lines) and `LearnerRepository.php` (761 lines) contain multiple responsibilities beyond basic CRUD
- Files: `src/Classes/Repositories/ClassRepository.php`, `src/Learners/Repositories/LearnerRepository.php`
- Impact: Difficult to test in isolation; mixing data access with business logic (filtering, mapping); changes ripple across multiple concerns
- Fix approach: Extract specialized query builders for filtering (SearchRepository pattern); move data transformation to dedicated Transformer classes; keep repositories focused on single-table access

**Large Controller Classes:**
- Issue: `ClassController.php` (590 lines), `QAController.php` (584 lines), `ClassAjaxController.php` (641 lines) mix shortcode rendering, AJAX handling, and page creation
- Files: `src/Classes/Controllers/ClassController.php`, `src/Classes/Controllers/QAController.php`, `src/Classes/Controllers/ClassAjaxController.php`
- Impact: Difficult to change request handling logic without affecting shortcodes; harder to test; future feature additions keep expanding these files
- Fix approach: Separate concerns into RequestHandler, ShortcodeRenderer, PageBootstrapper classes; use command/request objects for AJAX handling

**extract() Usage in View Rendering:**
- Issue: `core/Helpers/functions.php` line 93 uses `extract($data, EXTR_SKIP)` to pass data to views
- Files: `core/Helpers/functions.php`, line 93
- Impact: Variable pollution risk; IDE autocomplete doesn't work for view variables; potential for unintended variable overwrites; makes it unclear what variables are available in views
- Current mitigation: Uses `EXTR_SKIP` to prevent overwriting existing variables; documented warning in function
- Fix approach: Consider replacing with explicit variable assignment in view templates or using a ViewData object with typed properties

**Dual Naming Conventions in ClassModel:**
- Issue: `ClassModel` accepts both snake_case and camelCase field names from form data, causing confusion about canonical format
- Files: `src/Classes/Models/ClassModel.php`, lines 72-100 (hydrate method)
- Impact: Harder to predict which format to use; increases likelihood of field mapping bugs; makes API less predictable
- Fix approach: Standardize on single format (recommend camelCase internally, snake_case from DB); use FormDataProcessor to normalize input at entry point

**Manually Constructed SQL in Legacy Recording:**
- Issue: `ProgressionService::recordProgression()` constructs and inserts into legacy `learner_progressions` table manually
- Files: `src/Learners/Services/ProgressionService.php`, lines 206-218
- Impact: Bypasses repository pattern; duplication of effort; harder to test; inconsistent with modern code patterns
- Fix approach: Create `LegacyProgressionRepository` to centralize legacy table access; migrate away from this table in long term

## Known Bugs

**Agent Replacement Loading Mismatch:**
- Symptoms: Agent replacements loaded in `ClassModel::hydrate()` (line 104-105) may retrieve stale data if called multiple times
- Files: `src/Classes/Models/ClassModel.php`, lines 104-105
- Trigger: Instantiating multiple ClassModel instances for the same class ID in a single request
- Impact: Form may display outdated agent replacement history; potential for data consistency issues if replacements are modified
- Workaround: Refresh model from database if agent replacements were recently modified

**Filter Input Does Not Validate Email Addresses:**
- Symptoms: Email inputs in learner forms use `FILTER_VALIDATE_EMAIL` inconsistently
- Files: `src/Learners/Ajax/LearnerAjaxHandlers.php` uses `filter_input()` with type hints but validation not enforced at model layer
- Trigger: Directly posting malformed email to AJAX handlers
- Impact: Database may contain invalid email addresses; validation happens at controller layer but not model layer
- Workaround: Client-side form validation; email uniqueness constraint in database helps catch some issues

**Collision Detection Does Not Lock:**
- Symptoms: Race condition possible when two requests simultaneously check for active LP and create new one
- Files: `src/Learners/Services/ProgressionService.php`, lines 80-100 (checkForActiveLPCollision)
- Trigger: Simultaneous class assignment to same learner
- Impact: May create duplicate in-progress LPs violating constraint; integrity check relies on SELECT + INSERT sequence without locking
- Workaround: Database constraint on (learner_id, status='in_progress') helps but doesn't prevent all race conditions
- Fix approach: Use database SELECT FOR UPDATE or implement optimistic locking with version field

## Security Considerations

**extract() in Views Creates Variable Injection Risk:**
- Risk: While `EXTR_SKIP` prevents overwriting, external code could still set unexpected variables in view scope
- Files: `core/Helpers/functions.php`, line 93; `views/learners/components/learner-detail.php` (example consumer)
- Current mitigation: Uses `EXTR_SKIP` to prevent overwriting; documented warning
- Recommendations: Add pre-render variable validation; consider alternative pattern like ViewData object; add PHPStan analysis to detect view variable issues

**Portfolio and QA Report File Storage:**
- Risk: Files stored in uploads directory but access not gated by authentication at serving time
- Files: `src/Learners/Services/PortfolioUploadService.php`, `src/Classes/Services/UploadService.php`
- Current mitigation: `.htaccess` files prevent PHP execution; `index.php` prevents directory listing; stored outside web root (wp-uploads)
- Recommendations: Add download handler that checks permissions before serving; implement access logging for sensitive file downloads; consider storing in non-public directory

**MIME Type Validation Relies on finfo:**
- Risk: File type validation uses `finfo_file()` but could be spoofed by content injection
- Files: `src/Learners/Services/PortfolioUploadService.php`, line 131-133; `src/Classes/Services/UploadService.php` (similar)
- Current mitigation: Double validation - file extension + MIME type check
- Recommendations: Add additional verification for PDF files (magic bytes check); consider using external service for critical files; document MIME type bypass limitations

**Nonce Verification in AJAX Handlers:**
- Risk: Some AJAX handlers check nonce but error handler restores after verification, could be confused
- Files: `src/Classes/Controllers/ClassAjaxController.php`, lines 56-77 (complex error handler stack)
- Current mitigation: Uses `wp_verify_nonce()` correctly before processing
- Recommendations: Simplify error handling stack; create middleware to centralize nonce checks; add rate limiting to AJAX endpoints

**Column Whitelisting Prevents SQL Injection But Whitelist Could Grow Stale:**
- Risk: If new columns added to database but not added to whitelist, legitimate queries silently fail
- Files: `src/Classes/Repositories/ClassRepository.php`, lines 41-84; `src/Learners/Repositories/LearnerRepository.php` (similar)
- Current mitigation: Error logging when filtered data is empty
- Recommendations: Generate whitelist from database schema automatically; add automated test to verify whitelist matches database; document whitelist maintenance process

## Performance Bottlenecks

**Unoptimized Query in Class Learners Fetch:**
- Problem: `getLearnersWithProgression()` likely joins multiple tables without index optimization
- Files: `src/Classes/Repositories/ClassRepository.php`, lines ~400-420 (estimated)
- Cause: No explicit index hints; queries may do full table scans for large learner datasets
- Impact: Page load degrades with class size; slow QA assignment dialogs with 500+ learner classes
- Improvement path: Add database indices on (class_id, learner_id); profile queries with EXPLAIN ANALYZE; consider caching learner lists for classes

**Cache Duration Hardcoded:**
- Problem: `CACHE_DURATION` set to 12 hours in `ClassRepository.php` line 30, may be too long for active classes
- Files: `src/Classes/Repositories/ClassRepository.php`, line 30
- Impact: Class information cached for 12 hours; if learner added to class, old systems won't see it for up to 12 hours
- Improvement path: Make cache duration configurable; implement cache invalidation on update; use shorter cache for frequently-changing data

**Portfolio Upload Uses Direct File I/O:**
- Problem: `move_uploaded_file()` and `file_put_contents()` in `PortfolioUploadService` are synchronous, blocking
- Files: `src/Learners/Services/PortfolioUploadService.php`, lines 84, 99
- Impact: Large file uploads (near 10MB limit) block request handling; form submissions hang if server disk is slow
- Improvement path: Implement async upload queue; use object storage (S3); add upload progress tracking

**Error Logging to error_log Without Rate Limiting:**
- Problem: Exception handlers call `error_log()` for every database error without throttling
- Files: 30+ locations throughout codebase (grep found 30+); example `src/Classes/Repositories/ClassRepository.php` line 125
- Impact: Single database outage can flood error logs with thousands of entries; makes troubleshooting harder
- Improvement path: Implement error aggregation/sampling; use structured logging with severity levels; add dashboards for error rates

## Fragile Areas

**ClassModel Hydration Is Complex and Brittle:**
- Files: `src/Classes/Models/ClassModel.php`, lines 63-117 (hydrate and constructor)
- Why fragile: Accepts multiple naming conventions for same fields; parses JSON fields conditionally; loads related data on hydrate; over 45 fields with different types
- Safe modification: Add test fixtures for all field name variants before changing; separate JSON parsing into dedicated class; break hydration into smaller steps
- Test coverage: No unit tests visible; integration tests may not cover all field name variations
- Recommendations: Extract hydration logic to dedicated Hydrator class; add comprehensive unit tests for each field variant; create test fixtures for common data shapes

**Form Data Processor Has Multiple Parsing Paths:**
- Files: `src/Classes/Services/FormDataProcessor.php` (633 lines)
- Why fragile: Processes multiple data types (strings, arrays, JSON); has 20+ conditional branches; transforms field names; could fail silently on unexpected data
- Safe modification: Add type hints to all parameters and branches; create test cases for edge cases; log all transformation steps
- Test coverage: Likely not comprehensive; nested conditionals may have untested paths

**Class AJAX Controller Error Handling Manually Manages Output Buffers:**
- Files: `src/Classes/Controllers/ClassAjaxController.php`, lines 56-77
- Why fragile: Uses nested `ob_start()/ob_end_clean()` calls; custom error handler modifying globals; complex restoration logic
- Safe modification: Extract error handling to middleware; use try-finally blocks instead of manual restoration; simplify output buffer management
- Risk: Incorrect buffer handling could expose sensitive data in output; error messages might be duplicated

**Legacy Table Inserts Bypass Repository Pattern:**
- Files: `src/Learners/Services/ProgressionService.php`, lines 206-218 (recordProgression method)
- Why fragile: Direct SQL construction; manual parameter passing; doesn't use repository pattern; error silently logged instead of thrown
- Safe modification: Create LegacyProgressionRepository wrapper; add unit tests for legacy data format; plan migration away from table
- Impact: Changes to legacy table structure could break silently

## Scaling Limits

**PostgreSQL Connection Pool Not Implemented:**
- Current capacity: Single lazy-loaded PDO connection per request
- Limit: With 20+ concurrent users, may hit connection limit (default 100 connections); each request creates new connection if first query
- Files: `core/Database/PostgresConnection.php`, lines 80-160 (connect method)
- Scaling path: Implement connection pooling (PgBouncer); use prepared statements consistently; add connection timeout handling

**Learner History Queries Could Scan Full Table:**
- Current capacity: Works well under 10,000 learners; `getHistoryForLearner()` has no pagination
- Limit: Breaks down around 50,000+ learner histories; memory exhaustion on large datasets
- Files: `src/Learners/Models/LearnerProgressionModel.php`, estimated line ~300 (getHistoryForLearner)
- Scaling path: Add pagination to history queries; implement soft-delete archiving for old progressions; add database indices on (learner_id, created_at)

**Class Notes Stored as JSONB Without Index:**
- Current capacity: Works for <1000 notes per class
- Limit: JSONB queries become slow with 10,000+ notes in single class; no search performance
- Files: `src/Classes/Repositories/ClassRepository.php`, class_notes_data storage
- Scaling path: Create separate notes table with proper indices; implement full-text search; add caching layer for active classes

**File Upload Directory Not Sharded:**
- Current capacity: Flat directory structure fine for <10,000 files
- Limit: File systems may degrade with millions of files in single directory; finding specific file becomes slow
- Files: `src/Learners/Services/PortfolioUploadService.php` (line 36); `src/Classes/Services/UploadService.php`
- Scaling path: Implement year/month/day sharding; migrate to object storage; add file registry table with quick lookups

## Dependencies at Risk

**Direct Dependency on WordPress Internals:**
- Risk: Plugin relies on WordPress functions (get_option, wp_mkdir_p, wp_upload_dir) without abstraction
- Impact: If WordPress updates or removes functions, plugin breaks; harder to test in isolation
- Current mitigation: Uses WordPress hooks properly; checks for function existence where needed
- Migration plan: Create WordPress abstraction layer (FilesystemInterface, ConfigInterface); use interfaces instead of direct function calls

**No Version Constraint on WordPress:**
- Risk: Claims compatibility with "6.0+" but may have breaking changes with future releases
- Files: `wecoza-core.php`, line 11 (`Requires at least: 6.0`)
- Impact: Plugin untested on WordPress 6.5+; could break silently during updates
- Recommendations: Add integration tests against multiple WP versions; document minimum WP version for each feature; test nightly against WordPress trunk

**PostgreSQL Extension Not Gracefully Degraded:**
- Risk: Plugin requires `pdo_pgsql` extension but no fallback for missing extension
- Files: `wecoza-core.php`, lines 247-254 (activation check)
- Impact: Plugin won't activate if extension missing; no helpful guidance on installation
- Recommendations: Add admin notice with installation instructions; consider bundling helper script for extension setup

## Missing Critical Features

**No Transaction Rollback on Partial Failure:**
- Problem: ClassModel save creates transaction but doesn't properly rollback if agent replacement insert fails
- Impact: Database left in inconsistent state (class created, replacements missing)
- Files: `src/Classes/Models/ClassModel.php`, lines 136-160 (save method)
- Blocks: Multi-step operations that need all-or-nothing semantics

**No Audit Trail for Learner Data Changes:**
- Problem: Updates to learner PII not logged; hard to track who changed what and when
- Impact: Compliance issues; impossible to investigate data quality problems; can't restore previous values
- Blocks: Regulatory requirements (GDPR); troubleshooting data anomalies
- Recommendation: Implement AuditLog table and observer pattern; store before/after values

**No Bulk Import/Export for Learners and Classes:**
- Problem: All data entry through web forms; no CSV import for initial class setup
- Impact: Setting up 500-learner class takes hours via forms; no export for data migration
- Blocks: Bulk operations; integration with other systems; migrations

**No Rate Limiting on AJAX Handlers:**
- Problem: Endpoints accessible without throttling
- Impact: Potential for abuse; DDoS vulnerability; spam registration
- Files: All AJAX handlers in ClassAjaxController, LearnerAjaxHandlers
- Blocks: Public-facing endpoints; API integrations

## Test Coverage Gaps

**No Unit Tests for Core Services:**
- What's not tested: ProgressionService collision detection, PortfolioUploadService validation, FormDataProcessor transformations
- Files: `src/Learners/Services/ProgressionService.php`, `src/Learners/Services/PortfolioUploadService.php`, `src/Classes/Services/FormDataProcessor.php`
- Risk: Changes to business logic could break without detection; edge cases with malformed input untested
- Priority: High - these services control critical workflows
- Recommendations: Create test suite with mocked database; add tests for all error paths; use factories for test data

**No Integration Tests for AJAX Handlers:**
- What's not tested: Full request lifecycle through AJAX handlers; nonce verification; error handling; response format
- Files: `src/Classes/Controllers/ClassAjaxController.php`, `src/Learners/Ajax/LearnerAjaxHandlers.php`
- Risk: Regression in request handling; changes to WordPress APIs could break handlers silently
- Priority: High - these handlers are main API entry points
- Recommendations: Create integration test framework with WordPress test suite; test all AJAX endpoints; verify response formats

**No Tests for View Rendering:**
- What's not tested: Large view files (2500+ lines); form generation; conditional rendering paths
- Files: `views/classes/components/class-capture-partials/update-class.php`, `views/classes/components/class-capture-partials/create-class.php`
- Risk: Form layout bugs; missing fields; broken conditional rendering
- Priority: Medium - visual bugs are less critical than logic bugs
- Recommendations: Add visual regression testing; create simple render tests to verify variables are available

**No Tests for Database Queries:**
- What's not tested: Complex JOIN queries; filtering logic; pagination edge cases
- Files: `src/Classes/Repositories/ClassRepository.php`, `src/Learners/Repositories/LearnerRepository.php`
- Risk: Silent failures with empty results; pagination off-by-one errors; incorrect filtering logic
- Priority: Medium - query logic directly impacts feature behavior
- Recommendations: Create query test suite against test database; use EXPLAIN ANALYZE to verify index usage

**Security Test Exists But Limited:**
- What's tested: Basic SQL injection prevention in column whitelisting
- Files: `tests/security-test.php` (267 lines, basic test runner)
- Gaps: No tests for CSRF (nonce verification), XSS (output escaping), authentication bypass, file upload bypasses
- Priority: High - security regressions are critical
- Recommendations: Expand test suite; add fuzzing for input validation; implement GitHub Actions CI with security scanning

---

*Concerns audit: 2026-02-02*
