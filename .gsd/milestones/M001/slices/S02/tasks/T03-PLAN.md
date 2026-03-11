---
estimated_steps: 4
estimated_files: 2
---

# T03: Extend ClassTaskPresenter for exam tasks and complete integration verification

**Slice:** S02 — Event/Task Integration
**Milestone:** M001

## Description

Update ClassTaskPresenter to handle exam tasks correctly — learner name in label, no note input fields, compatible JSON shape for existing dashboard JS. Complete the verification script so all sections pass, proving the full integration pipeline works end-to-end.

## Steps

1. Modify `ClassTaskPresenter::presentTasks()` to detect exam tasks by ID prefix `exam-`:
   - For open exam tasks: include `complete_label` but skip `note_label`, `note_placeholder`, `note_required`, `note_required_message` fields. Set `note_required` to `false` explicitly if JS needs it. Keep `event_date` as null (exam tasks don't have event dates).
   - For completed exam tasks: include `completed_by`, `completed_at`, `note` (null), `reopen_label` — same shape as event tasks.
   - The label already contains the learner name (set by ExamTaskProvider in T01), so no additional formatting needed here.

2. Verify JSON shape compatibility with existing JS. Check `views/events/event-tasks/main.php` to confirm `buildOpenTaskHtml()` and `buildCompletedTaskHtml()` JS functions handle the exam task shape (no note field for open tasks). If JS requires `note_required` to be present, set it to `false` for exam tasks.

3. Update `tests/exam/verify-exam-task-integration.php` Section 4 (ClassTaskPresenter checks):
   - Verify exam open task has `id`, `label`, `complete_label`, no `note_required: true`
   - Verify exam completed task has `id`, `label`, `completed_by`, `completed_at`, `reopen_label`
   - Verify non-exam tasks still have note fields (no regression)
   - Verify mixed collection (event + exam tasks) produces correct output

4. Run full verification script — all 4 sections must pass. Fix any remaining issues.

## Must-Haves

- [ ] Exam open tasks have no note input field (note_required is false or absent)
- [ ] Exam completed tasks have the same shape as event completed tasks (reopen_label, completed_by, completed_at)
- [ ] Existing non-exam task presentation is completely unchanged
- [ ] JSON shape compatible with dashboard JS functions (buildOpenTaskHtml, buildCompletedTaskHtml)
- [ ] Full verification script passes all sections

## Verification

- `php tests/exam/verify-exam-task-integration.php` — ALL sections pass (1: ExamTaskProvider unit, 2: ExamTaskProvider DB, 3: TaskManager integration, 4: ClassTaskPresenter)
- Check that exam open task JSON has no `note_required: true`
- Check that non-exam open task JSON still has `note_label` and `note_placeholder`

## Observability Impact

- Signals added/changed: None — presenter is pure transformation, no logging needed
- How a future agent inspects this: Run verification script Section 4 to validate presenter output
- Failure state exposed: None — presenter never fails; worst case is wrong JSON shape caught by verification

## Inputs

- `src/Events/Views/Presenters/ClassTaskPresenter.php` — Current presenter to extend
- `src/Events/Services/ExamTaskProvider.php` (T01) — Generates Task objects with exam- prefix IDs
- `views/events/event-tasks/main.php` — Dashboard JS functions that consume presenter output
- `tests/exam/verify-exam-task-integration.php` (T01) — Verification script to complete

## Expected Output

- `src/Events/Views/Presenters/ClassTaskPresenter.php` — Extended with exam task handling
- `tests/exam/verify-exam-task-integration.php` — All sections passing, full S02 integration verified
