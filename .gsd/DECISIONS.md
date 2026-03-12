# Decisions

Append-only register of architectural and pattern decisions.

## D001: Single exam results table with step enum (2026-03-11)

**Context:** Need to store mock exam (×3), SBA, and final exam results for exam-track learners.
**Decision:** Single `learner_exam_results` table with `exam_step` VARCHAR column using values: `mock_1`, `mock_2`, `mock_3`, `sba`, `final`. Each row stores one step's result (percentage, optional file upload).
**Rationale:** Simpler than 3 separate tables. The data shape is identical across steps (percentage + optional file). The step enum makes queries straightforward. Mirrors the existing `learner_lp_tracking` + `learner_progression_portfolios` pattern.
**Alternatives rejected:** Separate tables per exam type (unnecessary complexity), JSONB column on `learner_lp_tracking` (harder to query and validate).

## D002: Exam results linked to learner_lp_tracking, not directly to learner (2026-03-11)

**Context:** Exam results need to be associated with a specific LP progression, not just a learner.
**Decision:** `learner_exam_results.tracking_id` FK references `learner_lp_tracking.tracking_id`.
**Rationale:** A learner could retake a programme. Results must be tied to a specific LP attempt, not just the learner. This matches how `learner_progression_portfolios` works.

## D003: Reuse PortfolioUploadService pattern for SBA scans and certificates (2026-03-11)

**Context:** SBA requires scan upload, final exam requires certificate upload.
**Decision:** Create an `ExamUploadService` following the same pattern as `PortfolioUploadService` — separate upload directory (`uploads/exam-documents/`), same validation, same security measures.
**Rationale:** Proven pattern already in use. Keeps exam files organized separately from portfolio files.

## D004: Exam tasks integrated via existing event_dates JSONB pattern (2026-03-11)

**Context:** Mario says steps 3+ should be events/tasks with reminders. The existing system uses `classes.event_dates` JSONB to generate tasks.
**Decision:** Extend the existing event/task pattern. When exam results are recorded, update exam-specific entries in event_dates or a parallel exam_events mechanism that TaskManager can consume.
**Rationale:** Reuses existing infrastructure. Office staff already know the task dashboard. Pending: exact integration approach to be determined in S02 planning.

## D005: Constructor injection with null-coalescing defaults for ExamService (2026-03-11)

**Context:** ExamService depends on ExamRepository and ExamUploadService. Need testability without a DI container.
**Decision:** Constructor accepts optional parameters with null-coalescing defaults: `$this->repository = $repository ?? new ExamRepository()`. Callers can pass mocks; production code passes nothing.
**Rationale:** Enables unit testing without modifying production wiring. Unlike ProgressionService which hardcodes `new` inside methods, this allows substitution at construction time. Lightweight alternative to a full DI container.

## D007: ExamTaskProvider generates virtual tasks instead of storing in event_dates JSONB (2026-03-11)

**Context:** D004 initially suggested extending `event_dates` JSONB for exam tasks. S02 research revealed exam tasks are per-learner (not per-class), making JSONB storage problematic — a class with 30 learners would need 150 exam entries in JSONB. Also, exam task state lives in `learner_exam_results`, so duplicating it in JSONB creates stale-data risk.
**Decision:** Create `ExamTaskProvider` service that generates virtual `Task` objects on-the-fly from `learner_exam_results` + `learner_lp_tracking` data. Tasks are never stored in `event_dates` JSONB. `TaskManager` merges them into `TaskCollection` alongside event_dates tasks. Completion routes through `ExamService`, not JSONB updates.
**Rationale:** Zero changes to existing event_dates structure. No stale-data risk (always reads current DB state). Exam tasks naturally per-learner. Batch-loaded for performance (one query for all displayed class IDs). Supersedes D004.
**Alternatives rejected:** D004's event_dates JSONB approach (per-class, not per-learner; stale data; unbounded JSONB growth).

## D008: Dashboard exam task completion records 100% — actual percentages via S03 UI (2026-03-11)

**Context:** Exam tasks on the task dashboard support "Complete" and "Reopen" actions. The dedicated exam UI (S03) supports recording specific percentages. Need to clarify what happens when an exam task is completed from the dashboard.
**Decision:** Dashboard "Complete" calls `ExamService::recordExamResult()` with percentage=100. Actual percentages are recorded through the S03 exam progress UI. Dashboard completion is a "mark done" action for task tracking purposes.
**Rationale:** The task dashboard has no UI for entering percentages — it's a simple complete/reopen toggle. Office staff will use the dedicated exam UI to enter real scores. Recording 100% ensures the exam result row exists in the DB so task status reflects correctly. The percentage can be corrected via S03.

## D009: Exam task reopen deletes result row rather than soft-clearing (2026-03-11)

**Context:** When office staff reopen an exam task from the dashboard, the corresponding `learner_exam_results` row needs to be invalidated.
**Decision:** `ExamRepository::deleteByTrackingAndStep()` performs a hard DELETE of the result row. `ExamTaskProvider::deleteExamResult()` delegates to this and refreshes the cache.
**Rationale:** Exam results have no intermediate states — either a result exists (task complete) or it doesn't (task open). Soft-clearing (nulling percentage) would leave orphan rows that complicate queries and the virtual task generation logic. Hard delete is clean and idempotent.
**Alternatives rejected:** Soft-clear with null percentage (complicates EXISTS-based task status queries), status column (unnecessary state machine for binary complete/incomplete).

## D010: hide_note flag on exam task presenter output for template conditionals (2026-03-11)

**Context:** Exam tasks don't require note input (unlike event tasks with agent-order notes). The presenter needs to signal this to the PHP template.
**Decision:** Exam open tasks include `hide_note: true` and `note_required: false` in their presenter output. The template wraps the note input column in `<?php if (empty($task['hide_note'])): ?>`.
**Rationale:** Keeps the task row layout intact while hiding the note column. Avoids undefined index warnings from template code that directly accesses `$task['note_label']`. Non-exam tasks are completely unaffected since they never have `hide_note` set.

## D011: Client-side exam card rendering from JSON for XSS safety (2026-03-11)

**Context:** After recording/deleting exam results, the exam progress section needs to refresh in-place without a full page reload.
**Decision:** The `get_exam_progress` AJAX endpoint returns enriched JSON (including `recorded_by_name` and `file_url`), and JavaScript rebuilds the entire exam section from JSON using jQuery DOM construction methods (no `innerHTML`). The JS renderer mirrors the PHP component's output exactly.
**Rationale:** jQuery DOM methods (`$('<div>')`, `.text()`, `.attr()`) prevent XSS by design — user-supplied values are never interpolated into HTML strings. Enriching the AJAX response avoids extra round-trips to resolve usernames and file URLs client-side. Full section rebuild is simpler and less error-prone than surgical DOM patching of individual step cards.
**Alternatives rejected:** innerHTML-based templating (XSS risk), server-side HTML fragment return (harder to test, mixes concerns), partial DOM updates per card (complex state tracking).

## D013: LP auto-completion failure isolated from exam result save (2026-03-11)

**Context:** After recording an exam result, the AJAX handler checks `isExamComplete()` and may call `markComplete()`. If `markComplete()` throws (e.g., DB error), the exam result itself was already saved successfully.
**Decision:** Wrap `markComplete()` in try/catch. On failure, log the exception and set `lp_completed: false` + `lp_error: <message>` in the AJAX response. The exam result save is never rolled back.
**Rationale:** Exam result recording is the primary action; LP completion is a side effect. Losing a successfully recorded exam result because of an LP subsystem failure would be worse than logging the completion failure and letting the user retry. The `lp_error` field gives the frontend diagnostic visibility.

## D014: Conditional lp_error in AJAX response — only on failure (2026-03-11)

**Context:** The `lp_error` field in the record_exam_result response is only relevant when LP completion fails.
**Decision:** Only include `lp_error` key in the AJAX response when non-null. Clean success responses omit it entirely.
**Rationale:** Keeps the normal success response clean. Frontend can check `response.data.lp_error` to surface LP completion problems without false positives.

## D006: Consistent service return format across all ExamService methods (2026-03-11)

**Context:** Need a predictable return format for all ExamService methods so downstream consumers (AJAX handlers, controllers) can handle results uniformly.
**Decision:** All ExamService methods return `['success' => bool, 'data' => array, 'error' => string]` with all three keys always present. Empty string for error on success, empty array for data on failure.
**Rationale:** Consistent structure simplifies AJAX response handling in S03. Avoids the need for callers to check key existence. ExamUploadService follows the same pattern with `['success', 'file_path', 'file_name', 'error']`.

## D012: PostgreSQL boolean columns need CASE WHEN for string conversion (2026-03-11)

**Context:** `LearnerProgressionModel::$examClass` is typed as `?string` expecting 'Yes'/'No'. The `classes.exam_class` column is PostgreSQL boolean. Using `COALESCE(c.exam_class, 'No')` still returns a PG boolean, causing PHP `Cannot assign bool to property` fatal error.
**Decision:** Use `CASE WHEN c.exam_class = true THEN 'Yes' ELSE 'No' END AS exam_class` in SQL queries instead of COALESCE with string fallback on boolean columns.
**Rationale:** PostgreSQL preserves the column type through COALESCE when the first non-null value is boolean. CASE WHEN explicitly returns text type. This pattern should be used anywhere a PG boolean needs to become a PHP string.

## D015: Entity history derived from existing relations, not a separate history table per entity (2026-03-12)

**Context:** M002 (WEC-189) needs entity relationship history for classes, learners, agents, and clients. Could either create dedicated history tables or query existing relational data.
**Decision:** Derive history from existing tables (classes, learner_lp_tracking, learner_hours_log, class_status_history, agent_notes, agent_absences, sites, locations). Only create `agent_class_history` as a new table for tracking agent-class assignment changes over time (not derivable from current schema).
**Rationale:** Most relationship data already exists in the DB — learner-class via learner_lp_tracking, agent-class via classes.class_agent, client-class via classes.client_id. Creating redundant history tables would mean double-writing and data staleness risk. The one exception is agent assignments, which only store current state (not when changes happened), hence the new `agent_class_history` table.

## D016: JSONB columns contain object arrays, not flat IDs (2026-03-12)

**Context:** `classes.learner_ids` and `classes.backup_agent_ids` are JSONB columns. Initial assumption was flat ID arrays.
**Decision:** Parse `learner_ids` as `[{id, name, level, status}]` and `backup_agent_ids` as `[{agent_id, date}]`. HistoryRepository handles both object and flat-ID formats with fallback parsing.
**Rationale:** Discovered during S01/T01 live data testing. The JSONB shapes include metadata beyond just IDs. Fallback support ensures backward compatibility if any rows use the older flat format.

## D017: Audit log: high-level only, entity type + ID, no PII field values, 3-year retention (2026-03-12)

**Context:** Mario (WEC-189) said audit trail is "nice to have" and explicitly warned against "everyone playing policeman." Detail level: high level only. Entities: class changes & learner changes. Retention: 3 years.
**Decision:** Audit log entries record action, entity_type, entity_id, user_id, and timestamp. No field-level change tracking. No PII in message text. 3-year retention with purge mechanism. Writes to existing `wecoza_events.audit_log` table. Audit failures are logged but never block the parent operation.
**Rationale:** Keeps audit trail lightweight and non-intrusive per Mario's guidance. Prevents staff from micro-auditing each other. Uses existing table infrastructure.

## D018: Audit log uses action codes, not field diffs (2026-03-12)

**Context:** Gemini audit flagged that "what changed" could be interpreted as JSON diffs of entity state, which would bloat the DB and risk leaking PII. Mario said "high level only."
**Decision:** Audit log entries use predefined action codes (e.g. `CLASS_STATUS_CHANGED`, `LEARNER_ADDED`, `AGENT_ASSIGNED`) in the `action` column. No field names, no old/new values, no JSON diffs. The `context` JSONB column stores only entity_type and entity_id.
**Rationale:** Matches Mario's "high level only" requirement. Prevents scope creep into granular change tracking. Keeps DB size minimal. No PII risk.

## D019: Audit log shortcode gatekept to admin pages, not user-facing (2026-03-12)

**Context:** Mario warned about "everyone playing policeman." Gemini flagged SC6's "can be queried" as potentially enabling user-facing audit browsing.
**Decision:** Audit log is exposed via `[wecoza_audit_log]` shortcode only. The shortcode itself renders for any authenticated user, but it will be placed exclusively on admin-only WordPress pages via page-level access control (existing WP capability checks). No user-facing audit query interface.
**Rationale:** Mario's intent: audit data exists for admin oversight, not for staff to monitor each other. Shortcode approach matches existing WeCoza pattern and allows gatekeeping via WP page permissions.

## D020: History UI uses clean tables/lists, not interactive timelines (2026-03-12)

**Context:** Original roadmap specified "Phoenix-style interactive timeline components." Gemini flagged this as over-engineering for a visibility requirement.
**Decision:** History sections on entity pages use clean, paginated tables and definition lists matching the existing page design language. No animated timelines, no interactive drag/filter UI. AJAX-loaded with pagination for performance.
**Rationale:** Mario asked for visibility ("what happened with this entity"), not an interactive timeline product. Tables are simpler to build, maintain, and match the existing Bootstrap-based admin UI.

## D021: HistoryService facade lives in S02, not S01 (2026-03-12)

**Context:** Gemini flagged AuditService boundary being split between S01 and S02. Original plan had HistoryService in S01.
**Decision:** S01 provides raw data-access (HistoryRepository + AuditService primitives). S02 builds HistoryService facade that merges repository data, adds additional queries (QA visits, portfolios, events, class notes), and wires audit logging into save handlers. Clean separation: S01 = data access, S02 = business logic + wiring.
**Rationale:** Cleaner slice boundaries. S01 is pure data layer. S02 is the integration layer that composes S01 primitives into user-facing data shapes.

## D022: Phoenix Card + Table Pattern for All List/Table Views (2026-03-12)

**Context:** Audit log shortcode initially used plain Bootstrap tables that looked inconsistent with the rest of the app. Mario's feedback — all tables must match the Phoenix card pattern used by `wecoza_display_classes`.
**Decision:** All WeCoza list/table views MUST follow this exact Phoenix card pattern:
1. Outer wrapper: `<div class="card shadow-none border my-3" data-component-card="data-component-card">`
2. Card header (`card-header p-3 border-bottom`): title with icon + search/filter buttons + action buttons
3. Summary strip: `scrollbar > row g-0 flex-nowrap` with `border-end` column dividers
4. Card body (`card-body p-4 py-2`): wrapping `table-responsive`
5. Table: `table table-hover table-sm fs-9 mb-0 overflow-hidden`
6. Thead: `border-bottom` with `border-0` th cells, each with a trailing Bootstrap icon
7. Status/action badges: `badge-phoenix badge-phoenix-{color}` with `badge-label` span + inline SVG icon
8. Pagination in card footer with `border-top`
**Reference:** `views/classes/components/classes-display.view.php`
**Rationale:** Consistent UI across all shortcode table views. Non-negotiable — every new table view must follow this pattern.
