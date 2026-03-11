# M001: Exam & Assessment Workflow

**Vision:** Learners on exam-track programmes (AET, GETC AET, REALLL) can have their mock exams, SBA, and final exam results recorded and tracked through events/tasks — completing their LP via the exam pathway instead of POE.

## Success Criteria

- Office staff can record mock exam percentages (1, 2, 3) for any exam-class learner
- Office staff can record SBA marks and upload scanned SBA documents
- Office staff can record final exam marks and upload certificates
- Learner progression view shows exam progress with all steps visible
- Each exam step appears as a completable task on the event/task dashboard
- Non-exam learners continue using POE flow with zero changes
- Exam LP completion triggers when final exam mark + certificate are recorded

## Key Risks / Unknowns

- **Event/task integration** — Existing TaskManager is tightly coupled to `event_dates` JSONB. Exam tasks may need a different storage/generation pattern.
- **Conditional UI** — Progression views must branch between POE and exam flows based on class type. Getting this wrong breaks existing views.

## Proof Strategy

- Event/task integration → retire in S02 by proving exam tasks appear on the task dashboard and can be completed
- Conditional UI → retire in S03 by proving both exam and POE learners render correctly on the same progression views

## Verification Classes

- Contract verification: SQL schema validation, PHP unit-level checks on ExamService logic
- Integration verification: AJAX endpoints return correct data, tasks appear on dashboard
- Operational verification: none (no services/daemons)
- UAT / human verification: full exam flow walkthrough in browser — record all 5 steps, verify LP completes

## Milestone Definition of Done

This milestone is complete only when all are true:

- All 5 exam steps (3 mocks + SBA + final) can be recorded via UI
- SBA scan and final certificate uploads work
- Exam tasks show on task dashboard and can be marked complete
- Learner progression view shows exam progress for exam-class learners
- POE learners are unaffected
- Full flow exercised in browser: mock 1 → mock 2 → mock 3 → SBA (marks + upload) → final (mark + certificate) → LP complete

## Slices

- [x] **S01: Exam Data Layer & Service** `risk:high` `depends:[]`
  > After this: exam results can be created, read, and updated via ExamService and ExamRepository against real database tables. Schema SQL is ready for deployment. No UI yet.

- [x] **S02: Event/Task Integration** `risk:high` `depends:[S01]`
  > After this: when exam results are recorded, corresponding tasks appear on the event/task dashboard. Office staff can complete exam tasks. Reminders work for pending exam steps.

- [ ] **S03: Exam Progress UI & AJAX** `risk:medium` `depends:[S01]`
  > After this: office staff can record mock exam percentages, SBA marks + scans, and final exam marks + certificates through the learner progression UI. The UI conditionally shows exam flow vs POE flow.

- [ ] **S04: Integration Testing & Polish** `risk:low` `depends:[S02,S03]`
  > After this: full end-to-end exam workflow verified in browser. Edge cases handled (partial progress, re-recording marks, exam class with mixed learners). LP completion via exam path confirmed working.

## Boundary Map

### S01 → S02

Produces:
- `learner_exam_results` table schema (columns: tracking_id, exam_step enum, percentage, file_path, file_name, recorded_by, recorded_at)
- `ExamService` with `recordExamResult()`, `getExamProgress()`, `isExamComplete()`
- `ExamRepository` with CRUD on `learner_exam_results`
- `ExamStep` enum: mock_1, mock_2, mock_3, sba, final

Consumes:
- nothing (first slice)

### S01 → S03

Produces:
- Same as S01 → S02 (ExamService, ExamRepository, ExamStep enum)
- `PortfolioUploadService` pattern for SBA/certificate uploads (reused, not new)

Consumes:
- nothing (first slice)

### S02 → S04

Produces:
- Exam task generation integrated into TaskManager
- Exam tasks visible on ClassTaskService output

Consumes:
- S01: ExamService, ExamRepository, ExamStep enum

### S03 → S04

Produces:
- AJAX endpoints for exam result CRUD
- Exam progress UI components (views)
- Conditional rendering logic on progression views

Consumes:
- S01: ExamService, ExamRepository, ExamStep enum
