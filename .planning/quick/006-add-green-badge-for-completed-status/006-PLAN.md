# Quick Task 006: Add Green Badge for Completed Status

## Objective

Add visual indicator (green badge) for "Completed" status in Schedule Statistics Event Dates table.

## Tasks

### Task 1: Add badge styling to status cell in JS

**File:** `assets/js/classes/class-schedule-form.js`

Replace plain text status with badge span:
- Completed → green badge (wecoza-badge-success)
- Cancelled → red badge (wecoza-badge-danger)
- Pending → gray badge (wecoza-badge-secondary)

### Task 2: Add CSS for badges

**File:** `/opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css`

Add `.wecoza-badge`, `.wecoza-badge-success`, `.wecoza-badge-danger`, `.wecoza-badge-secondary` classes.

## Acceptance Criteria

- [ ] Completed status shows green badge
- [ ] Cancelled status shows red badge
- [ ] Pending status shows gray badge
- [ ] Badges are uppercase text
