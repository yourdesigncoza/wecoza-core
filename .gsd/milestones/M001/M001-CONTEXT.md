# M001: Exam & Assessment Workflow — Context

**Gathered:** 2026-03-11
**Status:** Ready for planning

## Project Description

Build the exam/assessment workflow for WeCoza learners. Currently, LP progression tracks hours and portfolio uploads (POE). Exam-track learners follow a different path: 3 mock exams → SBA (Site-Based Assessment) → final exam. This applies to AET (CLIB–CL4, NL1–NL4), GETC AET, and REALLL programmes. GETC is compulsory; others are optional per client.

## Why This Milestone

Mario confirmed the exam workflow is needed and provided exact requirements (WEC-186, 2026-03-10). The `classes` table already has `exam_class`, `exam_type`, and `exam_learners` fields but no backend workflow exists to track exam progress. This is a gap in learner progression tracking.

## User-Visible Outcome

### When this milestone is complete, the user can:

- Record mock exam percentages (1, 2, 3) for exam-track learners
- Record SBA marks and upload scanned SBA documents
- Record final exam marks and upload certificates
- See exam progress on the learner's progression view
- Receive event/task reminders for each exam workflow step (mock 1 → mock 2 → mock 3 → SBA → final)
- Office staff can complete exam tasks from the task dashboard

### Entry point / environment

- Entry point: Learner progression UI (shortcodes), Event/Task dashboard
- Environment: WordPress frontend (browser), AJAX endpoints
- Live dependencies involved: PostgreSQL database, existing event/task system

## Completion Class

- Contract complete means: exam data persists correctly, progression status updates, files upload
- Integration complete means: exam tasks appear on task dashboard, reminders fire, learner view shows exam progress
- Operational complete means: full flow from mock 1 through final exam completion works for real learners

## Final Integrated Acceptance

To call this milestone complete, we must prove:

- An exam-class learner can have all 3 mock exam percentages recorded, SBA marks + scan uploaded, and final exam mark + certificate uploaded — and their LP shows as completed
- Each exam step generates an event/task visible on the task dashboard with working completion
- Non-exam learners continue using the existing POE flow unchanged

## Risks and Unknowns

- **Event/task integration complexity** — Steps 3+ must generate events/tasks with reminders. The existing TaskManager builds tasks from `event_dates` JSONB. Exam tasks may need a different pattern or extension of the existing one.
- **Schema design** — Need new table(s) for exam results. Must decide: extend `learner_lp_tracking` or create separate `learner_exam_results` table.
- **UI complexity** — Exam progress UI must coexist with POE progress UI on the same learner views. Need to conditionally render based on whether the class is exam-track.

## Existing Codebase / Prior Art

- `src/Learners/Services/ProgressionService.php` — LP lifecycle (start, complete, hours). Will need exam-aware completion logic.
- `src/Learners/Services/PortfolioUploadService.php` — File upload pattern. Reuse for SBA scans and certificates.
- `src/Learners/Repositories/LearnerProgressionRepository.php` — CRUD on `learner_lp_tracking`. Will need exam query methods.
- `src/Events/Services/TaskManager.php` — Builds tasks from `event_dates` JSONB, marks tasks complete. Exam tasks must integrate here.
- `src/Events/Services/ClassTaskService.php` — Fetches class tasks for dashboard display.
- `src/Classes/Models/ClassModel.php` — Already has `examClass`, `examType`, `examLearners` properties.
- `src/Classes/Services/FormDataProcessor.php` — Already processes `exam_class`, `exam_type`, `exam_learners` from form data.
- `schema/wecoza_db_schema_bu_march_10.sql` — Current schema with `classes.exam_class`, `classes.exam_learners`, `learner_lp_tracking`, `learner_progression_portfolios`.

> See `.gsd/DECISIONS.md` for all architectural and pattern decisions.

## Scope

### In Scope

- Database schema for exam results (mock exams, SBA, final exam)
- ExamService with business logic for recording results and progression
- File upload for SBA scans and final exam certificates (reuse PortfolioUploadService pattern)
- AJAX endpoints for exam result CRUD
- UI for recording/viewing exam progress on learner progression views
- Event/task generation for exam workflow steps
- Task completion integration on the task dashboard

### Out of Scope / Non-Goals

- External exam body integration (exams are managed externally, we only record results)
- Exam scheduling or calendar features
- Automated pass/fail decisions (office records marks; system doesn't decide outcomes)
- Changes to the class creation form's exam fields (already exist and work)

## Technical Constraints

- PHP 8.0+ (match expressions, typed properties, enums)
- PostgreSQL (not MySQL)
- Read-only DB access from agent — schema SQL goes to `schema/`, developer runs it
- CSS in `ydcoza-styles.css` only
- Must not break existing POE progression flow

## Integration Points

- **Event/Task system** — Exam steps become tasks with reminders
- **Learner progression** — Exam completion is an alternative path to LP completion (instead of POE)
- **File uploads** — SBA scans and certificates use the portfolio upload pattern
- **Class model** — `exam_class` flag determines which flow a learner follows

## Open Questions

- **Task generation trigger** — Should exam tasks be auto-generated when a learner is added to an exam class, or manually created by office staff? Current thinking: auto-generate when learner is assigned to exam class.
- **Exam results table design** — Single `learner_exam_results` table with `exam_step` column (mock_1, mock_2, mock_3, sba, final) vs separate tables? Current thinking: single table with step enum.
