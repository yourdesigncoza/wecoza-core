---
id: M001
provides:
  - learner_exam_results PostgreSQL schema with FK to learner_lp_tracking
  - ExamStep PHP 8.1 enum (mock_1, mock_2, mock_3, sba, final)
  - ExamRepository, ExamUploadService, ExamService data layer
  - ExamTaskProvider generating virtual exam tasks from DB state
  - TaskManager exam task routing (complete/reopen)
  - 3 AJAX endpoints (record_exam_result, get_exam_progress, delete_exam_result)
  - Conditional exam/POE progression UI with client-side rendering
  - LP auto-completion trigger on exam pathway completion
  - 223 automated checks across 5 test suites
key_decisions:
  - D001: Single learner_exam_results table with step enum column
  - D002: FK to learner_lp_tracking for per-attempt results
  - D005: Constructor injection with null-coalescing defaults for testability
  - D006: Consistent ['success', 'data', 'error'] return format
  - D007: Virtual task generation from DB state (supersedes D004 JSONB approach)
  - D008: Dashboard completion records 100%, actual percentages via exam UI
  - D009: Exam task reopen hard-deletes result row
  - D011: Client-side exam card rendering from JSON for XSS safety
  - D012: PostgreSQL boolean columns need CASE WHEN for string conversion
  - D013: LP auto-completion failure isolated from exam result save
patterns_established:
  - ExamStep enum with label(), badgeClass(), requiresFile(), tryFromString() — reusable for future enums
  - ExamRepository upsert via INSERT ON CONFLICT DO UPDATE with RETURNING
  - ExamTaskProvider preload/cache pattern for batch dashboard loading (single query for all class IDs)
  - Task ID format exam-{trackingId}-{step} with static parseExamTaskId() decomposition
  - Constructor injection with null-coalescing defaults across all exam services
  - Defensive AJAX response pattern — success includes optional error details for subsystem failures
  - CASE WHEN for PostgreSQL boolean-to-string conversion in SQL queries
observability_surfaces:
  - error_log("WeCoza Exam: ...") with tracking_id, step, and context on all caught exceptions
  - error_log("WeCoza ExamAjax: LP auto-completed/already completed/failed for tracking_id=...") on completion events
  - AJAX response lp_completed (bool) and lp_error (string, conditional) keys
  - ExamService::getExamProgress() returns structured {steps, completion_percentage, completed_count, total_steps}
  - php tests/exam/verify-exam-*.php — 5 test suites, 223 checks total
requirement_outcomes:
  - id: WEC-186
    from_status: active
    to_status: validated
    proof: All 7 success criteria verified — 223 automated checks pass across 5 test suites, browser-verified end-to-end exam UI renders correctly for exam-class learners, non-exam learners unaffected, LP auto-completion wired and tested
duration: ~3h
verification_result: passed
completed_at: 2026-03-11
---

# M001: Exam & Assessment Workflow

**Full exam tracking pipeline — 3 mock exams, SBA with scan upload, final exam with certificate — integrated into task dashboard and learner progression UI, with LP auto-completion on exam pathway.**

## What Happened

Built the exam & assessment workflow in four slices, each verified independently before the next began.

**S01 (Data Layer)** established the foundation: `learner_exam_results` table with FK to `learner_lp_tracking`, ExamStep PHP 8.1 enum (5 cases), ExamRepository with upsert, ExamUploadService for SBA/certificate files (PDF/DOC/DOCX/JPG/PNG, 10MB limit, MIME validation), and ExamService orchestrating all operations. All methods return consistent `['success', 'data', 'error']` arrays. 66 automated checks verified the full data layer.

**S02 (Task Integration)** proved exam tasks work on the dashboard. ExamTaskProvider batch-queries learner tracking data and generates virtual Task objects (up to 5 per learner) without touching the existing `event_dates` JSONB — a key architectural pivot from D004 to D007. TaskManager routes `exam-{trackingId}-{step}` task IDs to ExamService for completion (records 100%) and to hard-delete for reopening. ClassTaskPresenter hides note inputs for exam tasks. 83 checks verified all integration paths.

**S03 (UI & AJAX)** built the user-facing layer. Three AJAX endpoints handle exam result CRUD. The progression data pipeline was extended — `exam_class` flows from SQL through model to service, returning `is_exam_class` and `exam_progress` in `getCurrentLPDetails()`. The PHP component renders 5 step cards (completed details or pending form). A jQuery IIFE module handles recording with FormData file upload, progress bar, delete/re-record with confirmation, and full in-place refresh via client-side DOM construction (XSS-safe). Conditional rendering ensures non-exam learners see unchanged POE flow. 22 checks verified AJAX registration and integration.

**S04 (Polish & Verification)** wired LP auto-completion into the AJAX handler — when all 5 steps are recorded and the final has a certificate, `markComplete()` fires with defensive error isolation (D013). Edge cases hardened: partial progress (4/5 doesn't trigger), certificate requirement, double-completion guard, delete/re-record cycle. A PostgreSQL boolean-to-string bug was discovered and fixed during browser verification (D012: `COALESCE` preserves PG boolean type, `CASE WHEN` required). 52 new checks plus 171 regression checks = 223 total passing.

## Cross-Slice Verification

| Success Criterion | Evidence |
|---|---|
| Record mock exam percentages (1, 2, 3) | S03 AJAX `record_exam_result` endpoint + UI step cards with score inputs. S04 browser-verified all 3 mock cards render with correct `data-exam-step` attributes. |
| Record SBA marks and upload scanned SBA documents | S01 `ExamUploadService` validates PDF/DOC/DOCX/JPG/PNG up to 10MB. S03 AJAX handles FormData file upload. `ExamStep::requiresFile()` returns true for `sba`. Browser-verified file upload field present on SBA card. |
| Record final exam marks and upload certificates | Same upload pipeline as SBA. `requiresFile()` true for `final`. Browser-verified file upload field present on final card. |
| Learner progression view shows exam progress | S03 `learner-exam-progress.php` renders 5 step cards. S04 browser assertions: all 5 cards visible with correct data attributes, score inputs, and Record buttons. |
| Each exam step as completable task on dashboard | S02 `ExamTaskProvider` generates virtual tasks. `TaskManager` routes complete/reopen. 83 checks verify task generation, completion routing, and presenter output. |
| Non-exam learners use POE flow unchanged | S03 conditional branch in `learner-progressions.php` (`is_exam_class`). S04 browser-verified: no exam UI elements for non-exam learner progressions. |
| Exam LP completion on final exam + certificate | S04 `isExamComplete()` → `markComplete()` in AJAX handler. 52 checks verify: partial doesn't trigger, certificate required, double-completion guarded, `lp_completed` in response. |

**Automated test suite (223 total):**
- `verify-exam-schema.php`: 20/20
- `verify-exam-service.php`: 46/46
- `verify-exam-task-integration.php`: 83/83
- `verify-exam-ajax.php`: 22/22
- `verify-exam-completion.php`: 52/52

## Requirement Changes

- WEC-186: active → validated — All 7 success criteria met with 223 automated checks and browser verification. Exam-track learners can have mock 1-3, SBA (marks + scan), and final (mark + certificate) recorded. LP auto-completes via exam pathway. Non-exam flows unchanged.

## Forward Intelligence

### What the next milestone should know
- The exam workflow is fully self-contained within `src/Learners/` (data layer, AJAX, UI) and `src/Events/Services/` (task integration). No cross-module dependencies were introduced.
- ExamTaskProvider uses a preload/cache pattern — it's efficient for dashboards showing many classes but has no persistent cache across requests.
- The `exam-{trackingId}-{step}` task ID format is stable and parsed by multiple consumers (TaskManager, ExamTaskProvider, ClassTaskPresenter). Changing it requires updating all three.

### What's fragile
- `LearnerProgressionRepository` SQL queries — any PostgreSQL boolean column used in a PHP `?string` property needs `CASE WHEN` casting (D012). Grep for `COALESCE` on boolean columns before adding new queries.
- `ExamRepository::upsert()` depends on the UNIQUE constraint `(tracking_id, exam_step)`. If missing from deployed schema, upserts create duplicates.
- `isExamComplete()` checks `file_path` is non-empty for the final step. If upload logic changes, this check may break silently.
- JS client-side renderer must exactly mirror the PHP component's HTML structure for CSS to work. Changes to one require changes to the other.

### Authoritative diagnostics
- `for f in tests/exam/verify-exam-*.php; do php "$f"; done` — full exam regression suite (223 checks, ~5 seconds). If this passes, the entire exam workflow is healthy.
- PHP error log grep for `"WeCoza Exam:"` — all service/repository/AJAX errors logged with method name, tracking_id, step, and error context.
- Browser dev tools Network tab filtering for `record_exam_result`, `get_exam_progress`, `delete_exam_result` — all 3 should return 200.

### What assumptions changed
- D004 assumed exam tasks would extend `event_dates` JSONB — D007 replaced this with virtual task generation because exam tasks are per-learner not per-class, making JSONB impractical.
- Assumed `COALESCE` would work for PostgreSQL boolean→string conversion — it doesn't; PG preserves boolean type through COALESCE. Must use `CASE WHEN` (D012).
- Assumed ExamService from S01 would have `deleteExamResult()` — it didn't; added in S03 when the AJAX delete endpoint needed it.

## Files Created/Modified

- `schema/learner_exam_results.sql` — DDL for exam results table with constraints
- `src/Learners/Enums/ExamStep.php` — String-backed enum (5 cases + helper methods)
- `src/Learners/Repositories/ExamRepository.php` — CRUD, upsert, progress query, deleteByTrackingAndStep
- `src/Learners/Services/ExamUploadService.php` — File upload with MIME validation and security
- `src/Learners/Services/ExamService.php` — Business logic orchestrating repo, upload, and delete
- `src/Events/Services/ExamTaskProvider.php` — Batch virtual exam task generation with preload/cache
- `src/Events/Services/TaskManager.php` — Extended with exam task routing for complete/reopen
- `src/Events/Services/ClassTaskService.php` — Extended with exam class ID pre-filtering and batch preload
- `src/Events/Views/Presenters/ClassTaskPresenter.php` — Extended with exam task detection and hide_note flag
- `views/events/event-tasks/main.php` — Conditional wrapper around note input column
- `src/Learners/Ajax/ExamAjaxHandlers.php` — 3 AJAX endpoints with LP auto-completion
- `src/Learners/Repositories/LearnerProgressionRepository.php` — Added exam_class to baseQuery, fixed bool-to-string
- `src/Learners/Services/ProgressionService.php` — getCurrentLPDetails returns is_exam_class + exam_progress
- `src/Learners/Models/LearnerProgressionModel.php` — Added examClass property and isExamClass()
- `views/learners/components/learner-exam-progress.php` — 5-step exam progress card component
- `views/learners/components/learner-progressions.php` — Conditional exam/POE branch
- `assets/js/learners/learner-exam-progress.js` — jQuery IIFE for exam recording with file upload
- `src/Learners/Shortcodes/learner-single-display-shortcode.php` — Enqueue exam JS
- `wecoza-core.php` — require_once for ExamAjaxHandlers
- `/opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css` — Exam step card CSS
- `tests/exam/verify-exam-schema.php` — 20-check schema verification
- `tests/exam/verify-exam-service.php` — 46-check service verification
- `tests/exam/verify-exam-task-integration.php` — 83-check task integration verification
- `tests/exam/verify-exam-ajax.php` — 22-check AJAX verification
- `tests/exam/verify-exam-completion.php` — 52-check LP completion verification
