# WEC-168 Progression Clarity - Implementation Plan

## Summary

Implement auto-progression creation when learners are assigned to classes, with collision handling and enhanced visibility of learner course history.

---

## Gap Analysis

### What's Already Implemented ✓

| Requirement | Status | Location |
|-------------|--------|----------|
| One LP at a time constraint | ✓ | `ProgressionService.php:39` |
| Three-way hours (trained/present/absent) | ✓ | `LearnerProgressionModel.php` |
| Progress % calculation | ✓ | `getProgressPercentage()` |
| Portfolio upload for completion | ✓ | `markComplete()` method |
| Mark complete workflow | ✓ | `learner-progressions.php` view |
| Historical progression table | ✓ | `learner_progressions` table |
| Hours audit trail | ✓ | `learner_hours_log` table |
| Class-to-progression link | ✓ | `class_id` FK in `learner_lp_tracking` |

### Critical Gap

**When learners are assigned to classes in Create/Edit Class, NO automatic LP record is created.**

Current flow: `Class saved → learner_ids stored → STOP`
Required flow: `Class saved → learner_ids stored → create learner_lp_tracking records`

---

## Implementation Tasks

### 1. Show Last Completed Course in Available Learners Table

**File:** `views/classes/components/class-capture-partials/create-class.php`

- Add "Last Course" column to Available Learners table
- Query `learner_lp_tracking` for most recent completed LP per learner
- Display product name and completion date

**Files to modify:**
- `create-class.php` - Add column to learner selection table
- `LearnerRepository.php` - Add method `getLearnersWithLastCourse()`
- `learner-selection-table.js` - Update table rendering

---

### 2. Auto-Create LP on Learner Assignment

**File:** `src/Classes/Controllers/ClassAjaxController.php`

When class is saved, detect newly added learners and create LP records:

```php
// In saveClassAjax() after class save
$newLearnerIds = array_diff($currentLearnerIds, $previousLearnerIds);
foreach ($newLearnerIds as $learnerId) {
    $progressionService->startLearnerProgression(
        $learnerId,
        $class->getProductId(),
        $class->getClassId()
    );
}
```

**Files to modify:**
- `ClassAjaxController.php` - Add progression creation logic
- `ProgressionService.php` - Handle collision gracefully (return warning, not throw)

---

### 3. Collision Warning with Override Option

**Behavior:** If learner has in-progress LP, show warning but allow office to proceed.

**UI Flow:**
1. User selects learner with active LP
2. JS detects collision, shows warning modal:
   - "Learner has active LP: [Course Name] (X% complete)"
   - Options: "Add Anyway" / "Cancel"
3. If override chosen, proceed with assignment
4. Backend queues new LP as 'pending' or marks current as 'on_hold'

**Files to modify:**
- `learner-selection-table.js` - Add collision detection & modal
- `LearnerRepository.php` - Add method `getActiveLPForLearner()`
- `ClassAjaxController.php` - Handle override flag

---

### 4. Class Learner Modal - Read-Only Progression Display

**File:** `views/classes/components/single-class/modal-learners.php`

Add progression info to each learner row:
- Current LP name (or "None")
- Progress bar with %
- Hours: present / trained
- Status badge (in_progress, completed, on_hold)

**Files to modify:**
- `modal-learners.php` - Add progression columns
- `ClassController.php` - Include progression data in learner fetch

---

## File Changes Summary

| File | Changes |
|------|---------|
| `src/Classes/Controllers/ClassAjaxController.php` | Add auto-LP creation, collision handling |
| `src/Classes/Controllers/ClassController.php` | Include progression data in learner queries |
| `src/Learners/Repositories/LearnerRepository.php` | Add `getLearnersWithLastCourse()`, `getActiveLPForLearner()` |
| `src/Learners/Services/ProgressionService.php` | Modify to return warning instead of throw on collision |
| `views/classes/components/class-capture-partials/create-class.php` | Add "Last Course" column |
| `views/classes/components/single-class/modal-learners.php` | Add progression display columns |
| `assets/js/classes/learner-selection-table.js` | Add collision detection, warning modal |

---

## Verification

1. **Create class with learners** → LP records auto-created in `learner_lp_tracking`
2. **Add learner with active LP** → Warning shown, override works
3. **View Available Learners** → Last completed course visible
4. **View class learner modal** → Progression data displayed (read-only)
5. **Check progression reports** → New class assignments appear

---

## Dependencies

- `ProgressionService` already exists with `startLearnerProgression()`
- `LearnerProgressionModel` already has status management
- Database tables (`learner_lp_tracking`) already support class_id FK

---

## Additional Considerations (from Gemini Review)

### Edge Cases Identified

1. **Orphaned LPs on Learner Removal**
   - When learner removed from class, what happens to their LP?
   - Options: keep `in_progress` (orphaned), auto-transition to `on_hold`, or mark `cancelled`
   - **Decision needed:** Should removal set LP status to `on_hold`?

2. **Product ID Changes After Assignment**
   - If class product_id changes after learners assigned, existing LPs reference old product
   - **Recommendation:** Disable product_id editing once learners assigned, OR
   - Only auto-update LP product_id if hours = 0 (no training started)

3. **Data Migration for Existing Classes**
   - Existing classes have learners without LP records
   - Need backfill script or "Fix Integrity" button after deployment

4. **Race Conditions**
   - Two admins assigning same learner to different classes simultaneously
   - **Solution:** Wrap LP creation in database transaction
   - Consider unique index on `learner_id` WHERE `status = 'in_progress'`

### Performance Optimizations

1. **Avoid N+1 Queries**
   - Use LEFT JOIN on `learner_lp_tracking` to fetch last completed course
   - Single SQL query for entire learner list, not individual queries

```sql
SELECT l.*,
       lpt.product_name as last_course,
       lpt.completion_date as last_completion,
       active.product_id as active_lp_product
FROM learners l
LEFT JOIN (
    SELECT learner_id, product_name, completion_date,
           ROW_NUMBER() OVER (PARTITION BY learner_id ORDER BY completion_date DESC) as rn
    FROM learner_lp_tracking
    WHERE status = 'completed'
) lpt ON l.id = lpt.learner_id AND lpt.rn = 1
LEFT JOIN learner_lp_tracking active ON l.id = active.learner_id AND active.status = 'in_progress'
```

### Implementation Flow Refinement

**The Diffing Logic (in ClassAjaxController.php):**
```php
$current_ids = [/* IDs currently in DB */];
$new_ids     = [/* IDs submitted from form */];

$to_add    = array_diff($new_ids, $current_ids);  // Create LPs
$to_remove = array_diff($current_ids, $new_ids);  // Handle orphaned LPs
$to_update = array_intersect($new_ids, $current_ids); // Check product_id sync
```

**Force Override Flow:**
1. Frontend sends learners
2. Backend detects collision → Returns 409 with details
3. Frontend shows modal, user clicks "Override"
4. Frontend re-sends with `force_create: true`
5. Backend places old LP `on_hold` and creates new one

### Database Transactions

Wrap in transaction to ensure atomicity:
```php
$wpdb->query('START TRANSACTION');
try {
    // Update class learner_ids
    // Create/update LP records
    $wpdb->query('COMMIT');
} catch (Exception $e) {
    $wpdb->query('ROLLBACK');
}
```

---

## Linear Issue Reference

- **Issue:** WEC-168 - Progression Clarity
- **URL:** https://linear.app/wecoza/issue/WEC-168/progression-clarity
- **Branch:** `laudesmichael/wec-168-progression-clarity`

---

## Implementation Status

### Completed Tasks

- [x] **LearnerRepository**: Added `getLearnersWithProgressionContext()` and `getActiveLPForLearner()` methods
- [x] **ClassRepository**: Updated `getLearners()` to include progression context (last completed course, active LP)
- [x] **create-class.php**: Added "Last Course" and "Active LP" columns to Available Learners table
- [x] **learner-selection-table.js**: Added collision detection modal for learners with active LPs
- [x] **ProgressionService**: Added `createLPForClassAssignment()` and `checkForActiveLPCollision()` methods
- [x] **ClassAjaxController**: Added `createLPsForNewLearners()` for auto-LP creation on class save
- [x] **modal-learners.php**: Added progression display columns (Current LP, Progress bar)

### Files Modified

| File | Changes |
|------|---------|
| `src/Learners/Repositories/LearnerRepository.php` | +2 methods for progression context |
| `src/Classes/Repositories/ClassRepository.php` | Updated getLearners() with CTEs for progression |
| `src/Learners/Services/ProgressionService.php` | +3 methods for collision handling |
| `src/Classes/Controllers/ClassAjaxController.php` | +1 method for auto-LP creation |
| `views/classes/components/class-capture-partials/create-class.php` | +2 columns in learner table |
| `views/classes/components/single-class/modal-learners.php` | +2 columns for LP display |
| `assets/js/classes/learner-selection-table.js` | +collision modal logic |

### Testing Required

1. Create a new class with learners → verify LP records created in `learner_lp_tracking`
2. Add a learner with an active LP → verify warning modal appears
3. Click "Add Anyway" → verify previous LP put on hold, new LP created
4. View Available Learners table → verify "Last Course" column shows data
5. View class learner modal → verify progression display works
