---
status: complete
phase: 20-material-tracking-urgency
source: 20-01-SUMMARY.md
started: 2026-02-10T12:00:00Z
updated: 2026-02-10T17:58:00Z
---

## Current Test

[testing complete]

## Tests

### 1. Overdue pending delivery shows red border
expected: Navigate to the Material Tracking Dashboard. Find a pending delivery row where the delivery date is today or in the past. That row should have a visible red left border (3px solid red).
result: issue
reported: "I see the urgency-overdue class but nothing displays - border not visible"
severity: major
fix: Changed CSS from border-left to box-shadow inset on td:first-child to avoid overflow-hidden clipping
retest: pass

### 2. Approaching pending delivery shows orange border
expected: Find a pending delivery row where the delivery date is 1-3 days from now. That row should have a visible orange left border (3px solid orange).
result: skipped
reason: No test data with delivery dates 1-3 days away

### 3. Comfortable pending delivery shows no border
expected: Find a pending delivery row where the delivery date is 4 or more days in the future. That row should have no special left border — it looks like a normal row.
result: pass

### 4. Completed/delivered rows show no border
expected: Find a row that has been marked as completed or delivered. Regardless of the date, it should have no urgency border at all.
result: pass

### 5. Mark as delivered removes urgency border
expected: Find a pending row that currently shows a red or orange urgency border. Check the "mark as delivered" checkbox. The urgency border should disappear immediately without a page refresh.
result: pass

## Summary

total: 5
passed: 3
issues: 0
pending: 0
skipped: 1
fixed: 1

## Gaps

[none - issue found in test 1 was fixed during session (CSS border-left changed to box-shadow inset)]
