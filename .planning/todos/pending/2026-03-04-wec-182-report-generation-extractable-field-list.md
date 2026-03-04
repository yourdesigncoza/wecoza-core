---
created: 2026-03-04T10:15:12.303Z
title: "WEC-182 Report generation: extractable field list from Mario"
area: learners
linear: https://linear.app/wecoza/issue/WEC-182
blocked_by: mario-field-list
files:
  - src/Learners/Repositories/LearnerProgressionRepository.php
  - src/Learners/Ajax/ProgressionAjaxHandlers.php
---

## Problem

Mario (after discussing with Lance) wants a phased report generation approach:
1. Mario provides a list of fields to extract into Excel/CSV
2. They also need to add info to Subjects/Learning Programmes
3. They build report templates in Google Sheets
4. We replicate final reports in WeCoza for automated monthly client emails

4 reports planned:
- Summary report
- Attendance register
- Progress report
- Individual learner report

Waiting for Mario to send the extractable field list.

## Solution

Once field list arrives:
1. Build flexible CSV/Excel extraction endpoint covering all requested fields
2. May need to extend `class_type_subjects` table with additional fields Mario wants to add
3. Wait for Google Sheets templates, then build in-app report generation + email delivery
