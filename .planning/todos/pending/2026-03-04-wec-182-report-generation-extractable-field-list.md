---
created: 2026-03-04T10:15:12.303Z
title: "WEC-182 Report generation: extractable field list from Mario"
area: learners
linear: https://linear.app/wecoza/issue/WEC-182
blocked_by: mario-field-list
status: waiting
files:
  - src/Learners/Repositories/LearnerProgressionRepository.php
  - src/Learners/Ajax/ProgressionAjaxHandlers.php
---

## Status

Mario said (2026-03-04): "I will send the list to you by tomorrow at the latest tomorrow."

Expected by: 2026-03-05.

## Plan

Phased approach:
1. Mario provides extractable field list
2. They also need to add info to Subjects/Learning Programmes
3. They build report templates in Google Sheets
4. We replicate final reports in WeCoza for automated monthly client emails

4 reports planned: summary, attendance register, progress report, individual learner report.

## Solution

Once field list arrives:
1. Build flexible CSV/Excel extraction endpoint covering all requested fields
2. May need to extend `class_type_subjects` table with additional fields
3. Wait for Google Sheets templates, then build in-app report generation + email delivery
