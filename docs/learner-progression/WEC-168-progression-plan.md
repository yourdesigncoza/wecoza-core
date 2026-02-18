# WEC-168: Learner Progressions - Implementation Summary

**Linear Issue**: [WEC-168](https://linear.app/wecoza/issue/WEC-168/progression-clarity)
**Status**: Implementation in progress
**Created**: 2026-01-29
**Updated**: 2026-01-29

---

## Context

Mario answered initial clarification questions about how progressions work. Follow-up questions posted to Linear awaiting response.

## Client Requirements (Confirmed)

| Requirement | Detail |
|-------------|--------|
| LP Sequence | Any order (not fixed) |
| Concurrency | One LP at a time per learner |
| Trigger | Office marks complete when portfolio submitted |
| Track | Current LP, hours/LP, overall % (NO assessments) |
| Class sessions | 95% same LP; exceptions for late starters |
| Facilitator role | Read-only - Wecoza provides LP info |
| Packages | Same principle, learners on different subjects |
| Reports | All progress, monthly, regulatory (Umalusi/DHET) |
| History | Required for compliance reporting |

**Critical**: Progression is **per LEARNER**, not per class.

---

## Client Response (Mario - 2026-01-29)

| Question | Answer |
|----------|--------|
| Hours Source | **Both**: Schedule (trained) + Attendance (present) + Calculate absent |
| Portfolio | **File upload required** |
| Late Starters | **Manual assignment** by office |
| Packages | **Defer** - focus on progressions first |
| Historical Data | **Fresh start** - no migration |

**File Storage**: WordPress uploads (`wp-content/uploads/portfolios/`)

---

## Existing Database Infrastructure

Tables already exist:
- `learner_progressions` - tracks from/to product transitions
- `learner_products` - learner-to-product enrollment
- `products` - includes `product_duration` (hours)
- `class_types` - has `progression_total_hours` and `subject_selection_mode`

**Gap**: No per-learner hours tracking or LP status tracking

---

## Proposed Solution

### New Tables

```sql
-- Track current LP status per learner
learner_lp_tracking (
    tracking_id, learner_id, product_id, class_id,
    hours_trained, hours_present, hours_absent,
    status ENUM('in_progress','completed','on_hold'),
    start_date, completion_date,
    portfolio_file_path, portfolio_uploaded_at,
    marked_complete_by, marked_complete_date, notes
)
-- Constraint: ONE in_progress LP per learner

-- Detailed hours log (audit trail)
learner_hours_log (
    log_id, learner_id, product_id, class_id,
    log_date, hours_trained, hours_present,
    source ENUM('schedule','attendance','manual'),
    session_id, created_by, notes
)

-- Portfolio files
learner_portfolio_files (
    file_id, tracking_id, file_name, file_path,
    file_type, file_size, uploaded_by, uploaded_at
)
```

### PHP Classes

| Class | Location | Purpose |
|-------|----------|---------|
| LearnerProgressionModel | models/ | Data model & business methods |
| LearnerProgressionRepository | repositories/ | DB access layer |
| ProgressionService | services/ | Business logic, validation |

### UI Components

1. **Progressions Tab** - in learner single display
   - Current LP card, hours, %, timeline
   - Mark Complete button (admin)

2. **Admin Management Panel** - new shortcode
   - Filter by client/class/LP/status
   - Bulk actions, export

### AJAX Endpoints

```
get_learner_progression_data
mark_lp_complete
start_new_lp
log_learner_hours
get_progression_history
get_monthly_progressions_report
export_progressions
```

### Percentage Calculation

```
LP % = hours_completed / product_duration * 100
Overall % = SUM(completed LP hours) / SUM(enrolled LP durations) * 100
```

---

## Files to Modify

- `database/learners-db.php` - add progression methods
- `shortcodes/learner-single-display-shortcode.php` - enable tab
- `components/learner-tabs.php` - wire up progressions tab
- `learners-plugin.php` - register handlers

---

## Next Steps

1. ~~Wait for Mario's response on Linear~~ ✅
2. ~~Update this plan with final decisions~~ ✅
3. Implement Phase 1 (Database) ← **IN PROGRESS**
4. Implement Phase 2 (PHP Classes)
5. Implement Phase 3 (UI)
6. Implement Phase 4 (AJAX)
7. Implement Phase 5 (Reports)

---

## Reference

- Full plan: `~/.claude/plans/cozy-sauteeing-kite.md`
- Schema: `schema/wecoza_db_schema_bu_jan_27.sql`
- Linear thread has Mario's detailed answers to initial questions
