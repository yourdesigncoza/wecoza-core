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

## D006: Consistent service return format across all ExamService methods (2026-03-11)

**Context:** Need a predictable return format for all ExamService methods so downstream consumers (AJAX handlers, controllers) can handle results uniformly.
**Decision:** All ExamService methods return `['success' => bool, 'data' => array, 'error' => string]` with all three keys always present. Empty string for error on success, empty array for data on failure.
**Rationale:** Consistent structure simplifies AJAX response handling in S03. Avoids the need for callers to check key existence. ExamUploadService follows the same pattern with `['success', 'file_path', 'file_name', 'error']`.
