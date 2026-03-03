---
status: investigating
trigger: "FEAT-41: Require date selection when placing on hold, resuming, or completing training"
created: 2026-03-03T00:00:00Z
updated: 2026-03-03T00:00:00Z
---

## Current Focus

hypothesis: Confirmed - all three actions (hold/resume/complete) execute immediately with no date prompt
test: Traced full data flow through all layers
expecting: Implementing date picker modal in JS + passing effective_date to PHP backend
next_action: Implement the fix across all affected files

## Symptoms

expected: Date picker appears before hold/resume/complete actions execute
actual: Actions execute immediately without date selection
errors: None - feature enhancement
reproduction: Go to Learner Progression Report & Management page, click Hold/Resume/Complete
started: Feature request - never had date selection

## Eliminated

- hypothesis: Date might exist in DB but not exposed in UI
  evidence: learner_lp_tracking has completion_date (date column) but no hold_date or resume_date
  timestamp: 2026-03-03

## Evidence

- timestamp: 2026-03-03
  checked: progression-admin.js handleToggleHold()
  found: Immediately fires AJAX POST to toggle_progression_hold with no date prompt
  implication: Need to intercept click and show date modal before AJAX call

- timestamp: 2026-03-03
  checked: progression-admin.js handleMarkSingleComplete()
  found: Uses native confirm() dialog then fires AJAX immediately
  implication: Need date modal before AJAX call

- timestamp: 2026-03-03
  checked: learner-progressions.js (single learner view)
  found: Mark Complete opens confirmation modal, then proceed to portfolio upload - no date field
  implication: Need date picker in the mark complete flow

- timestamp: 2026-03-03
  checked: ProgressionAjaxHandlers.php handle_toggle_progression_hold()
  found: Reads POST tracking_id and toggle action, no date param accepted
  implication: Need to add effective_date POST param and pass to model

- timestamp: 2026-03-03
  checked: ProgressionAjaxHandlers.php handle_mark_progression_complete()
  found: No date param accepted - hardcodes wp_date('Y-m-d') in markComplete()
  implication: Need to add effective_date and pass to markComplete()

- timestamp: 2026-03-03
  checked: LearnerProgressionModel.php markComplete() and putOnHold()
  found: markComplete() hardcodes $this->completionDate = wp_date('Y-m-d'); putOnHold() has no date
  implication: Need to accept optional date parameter in both methods

- timestamp: 2026-03-03
  checked: LearnerProgressionRepository.php update() column whitelist
  found: 'completion_date' is in the allowed update columns - no hold_date or resume_date column exists
  implication: For hold/resume, effective_date can be stored in notes; for complete, it maps to completion_date

- timestamp: 2026-03-03
  checked: learner_lp_tracking DB schema
  found: Columns: tracking_id, learner_id, class_type_subject_id, class_id, hours_trained, hours_present, hours_absent, status, start_date, completion_date, portfolio_file_path, portfolio_uploaded_at, marked_complete_by, marked_complete_date, notes, created_at, updated_at
  implication: No dedicated hold_date/resume_date column. effective_date for complete maps to completion_date. For hold/resume, we can store in notes or add to schema

- timestamp: 2026-03-03
  checked: progression-admin.php view
  found: Contains bulkCompleteModal, startNewLPModal, hoursLogModal - need to add datePickerModal
  implication: New modal goes in progression-admin.php

- timestamp: 2026-03-03
  checked: learner-progressions.php view (single learner)
  found: markCompleteConfirmModal exists - need date field added
  implication: Add date input to existing markCompleteConfirmModal or its flow

## Resolution

root_cause: Three action handlers (handleToggleHold, handleMarkSingleComplete in admin JS; handleMarkComplete in single learner JS) all execute immediately without prompting for an effective date. The backend methods accept no date parameter and hardcode today's date.

fix:
  1. Add date picker modal to progression-admin.php (for hold/resume/single complete in admin table)
  2. Update progression-admin.js to show date modal before executing hold/resume/single-complete AJAX
  3. Add date field to learner-progressions.php markCompleteConfirmModal
  4. Update learner-progressions.js to include effective_date in mark complete AJAX
  5. Update ProgressionAjaxHandlers.php handle_toggle_progression_hold() and handle_mark_progression_complete() to accept effective_date
  6. Update LearnerProgressionModel.php markComplete() and putOnHold() to accept optional date param
  7. For hold/resume: store effective_date in notes (no schema change needed)
  8. For complete: pass effective_date as completion_date

verification:
files_changed:
  - views/learners/progression-admin.php (add date picker modal)
  - assets/js/learners/progression-admin.js (intercept actions with date modal)
  - views/learners/components/learner-progressions.php (add date input to confirm modal)
  - assets/js/learners/learner-progressions.js (include date in mark complete AJAX)
  - src/Learners/Ajax/ProgressionAjaxHandlers.php (accept effective_date param)
  - src/Learners/Models/LearnerProgressionModel.php (accept date param in markComplete/putOnHold/resume)
