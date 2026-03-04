---
phase: quick-20
plan: 01
subsystem: attendance
tags: [attendance, validation, UX, WEC-182]
dependency-graph:
  requires: []
  provides: [hours-over-warning, monthly-summary-row]
  affects: [attendance-capture]
tech-stack:
  added: []
  patterns: [soft-validation-warning, tfoot-summary-row]
key-files:
  created: []
  modified:
    - assets/js/classes/attendance-capture.js
    - views/classes/components/single-class/attendance.php
    - /opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css
decisions:
  - Removed hard block on over-hours submission; amber icon is soft warning only
  - Summary row shows session capture ratio (captured/total) rather than per-learner hours (not available at session level)
metrics:
  duration: 88s
  completed: 2026-03-04
  tasks: 2/2
---

# Quick Task 20: Hours Validation Warning + Monthly Summary Row

Soft amber warning on hours_present exceeding scheduled, plus tfoot summary row with scheduled hours total and color-coded attendance percentage badge.

## Task Results

| Task | Name | Commit | Key Changes |
|------|------|--------|-------------|
| 1 | Hours validation warning in capture modal | a6f8077 | Amber icon on over-hours input, removed hard submission block, CSS rule |
| 2 | Monthly summary totals row | 2ece533 | tfoot in PHP view, renderSummaryRow() function, auto-updates on filter |

## Changes Made

### Task 1: Hours Validation Warning
- Modified `bindEvents()` input handler to append/remove amber `bi-exclamation-triangle-fill` icon when `hours_present > max`
- Modified `submitCapture()` to only reject negative/NaN values (removed `hoursPresent > maxHours` guard)
- Updated validation error message to reflect new behavior
- Added `.hours-over-warning` CSS rule to ydcoza-styles.css

### Task 2: Monthly Summary Totals Row
- Added `<tfoot id="attendance-sessions-tfoot">` to attendance table in PHP view
- Created `renderSummaryRow()` function computing: total scheduled hours (non-blocked), captured/total session count, attendance percentage with Phoenix color-coded badge (green >= 80%, amber >= 50%, red < 50%)
- Called from `renderSessionTable()` so it updates on month filter change
- Clears tfoot when no sessions match filter

## Deviations from Plan

None - plan executed exactly as written.

## Self-Check: PASSED
