# Quick Task 008: Fix Event Dates Heading Border - Summary

**Completed:** 2026-02-05
**Commit:** 7c997f4

## Issue

The "Event Dates" subheader row had `colspan="3"` but the table has 5 columns (Type, Description, Date, Status, Notes), causing the border to stop halfway across the table.

## Fix

Changed `colspan="3"` to `colspan="5"` in both:
- `views/classes/components/class-capture-partials/update-class.php`
- `views/classes/components/class-capture-partials/create-class.php`

## Result

Border now extends full width of the table under "Event Dates" heading.
