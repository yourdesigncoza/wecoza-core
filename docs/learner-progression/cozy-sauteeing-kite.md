# WEC-168: Learner Progressions Implementation Plan

## Client Requirements Summary

| Aspect | Requirement |
|--------|-------------|
| Sequence | LPs done in any order (not fixed) |
| Concurrency | One LP at a time per learner |
| Trigger | Office marks complete when portfolio submitted |
| Tracking | Current LP, hours/LP, overall % (NO assessments) |
| Class sessions | 95% same LP; exceptions for late starters |
| Facilitator | Read-only - Wecoza provides LP info |
| Packages | Same principle but learners on different subjects |
| Reports | All progress, monthly progressions, regulatory |
| History | Required for Umalusi & DHET |

**Critical**: Progression is per LEARNER, not per class.

---

## Existing Infrastructure

**Tables already exist:**
- `learner_progressions` (progression_id, learner_id, from_product_id, to_product_id, progression_date, notes)
- `learner_products` (learner_id, product_id, start_date, end_date)
- `products` (product_id, product_name, product_duration [hours], learning_area, learning_area_duration)
- `class_types` (subject_selection_mode: 'own'|'all_subjects'|'progression', progression_total_hours)

**Missing**: Hours tracking per LP per learner, status tracking, portfolio submission tracking

---

## Phase 1: Database Schema

### 1.1 New Table: `learner_lp_tracking`
```sql
CREATE TABLE learner_lp_tracking (
    tracking_id SERIAL PRIMARY KEY,
    learner_id INT NOT NULL REFERENCES learners(id),
    product_id INT NOT NULL REFERENCES products(product_id),
    class_id INT REFERENCES classes(class_id),
    hours_completed NUMERIC(8,2) DEFAULT 0,
    status VARCHAR(20) DEFAULT 'in_progress', -- in_progress|completed|withdrawn
    start_date DATE NOT NULL,
    completion_date DATE,
    portfolio_submitted BOOLEAN DEFAULT FALSE,
    portfolio_submitted_date DATE,
    marked_complete_by INT,
    marked_complete_date TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);
-- Partial unique: only ONE in_progress LP per learner
CREATE UNIQUE INDEX idx_one_active_lp ON learner_lp_tracking(learner_id) WHERE status = 'in_progress';
```

### 1.2 New Table: `learner_hours_log`
```sql
CREATE TABLE learner_hours_log (
    log_id SERIAL PRIMARY KEY,
    learner_id INT NOT NULL REFERENCES learners(id),
    product_id INT NOT NULL REFERENCES products(product_id),
    class_id INT REFERENCES classes(class_id),
    log_date DATE NOT NULL,
    hours NUMERIC(5,2) NOT NULL,
    source VARCHAR(50), -- class_session|manual_entry|adjustment
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT NOW()
);
```

### 1.3 Alter `learner_progressions`
```sql
ALTER TABLE learner_progressions ADD COLUMN
    triggered_by INT,
    portfolio_reference VARCHAR(255),
    class_id INT REFERENCES classes(class_id),
    hours_on_from_product NUMERIC(8,2),
    created_at TIMESTAMP DEFAULT NOW();
```

---

## Phase 2: PHP Classes

### 2.1 Model: `LearnerProgressionModel.php`
Location: `models/LearnerProgressionModel.php`

Methods:
- `getCurrentLP(int $learnerId): ?array`
- `getProgressionHistory(int $learnerId): array`
- `canStartNewLP(int $learnerId): bool`
- `markComplete(int $learnerId, int $userId, string $notes): bool`
- `startNewLP(int $learnerId, int $productId, int $classId): int`
- `addHours(int $learnerId, int $productId, float $hours, string $source): bool`
- `calculateLPCompletion(int $learnerId, int $productId): float`
- `calculateOverallCompletion(int $learnerId): float`

### 2.2 Repository: `LearnerProgressionRepository.php`
Location: `repositories/LearnerProgressionRepository.php`

DB access layer for all progression queries.

### 2.3 Service: `ProgressionService.php`
Location: `services/ProgressionService.php`

Business logic: validation, percentage calc, hours sync.

---

## Phase 3: Admin UI

### 3.1 Progressions Tab (Learner Single Display)
Location: `components/learner-progressions-tab.php`

- Current LP card (name, hours, %, start date)
- "Mark Complete" button (admin only)
- Progression history timeline
- Hours breakdown table
- Overall completion gauge

### 3.2 Admin Progression Management
New shortcode: `[wecoza_progression_management]`

- Filterable learner list by client/class/LP/status
- Bulk mark complete
- Export to CSV
- Audit trail

---

## Phase 4: AJAX Endpoints

File: `ajax/progression-ajax-handlers.php`

```php
wp_ajax_get_learner_progression_data
wp_ajax_mark_lp_complete
wp_ajax_start_new_lp
wp_ajax_log_learner_hours
wp_ajax_get_progression_history
wp_ajax_get_monthly_progressions_report
wp_ajax_export_progressions
```

---

## Phase 5: Reports

1. **Learner Progress Report** - Per-learner LP history & current status
2. **Monthly Progressions** - Date range filter, export for Umalusi/DHET
3. **Compliance Report** - Aggregated by LP/client/class, completion rates

---

## Critical Files to Modify

| File | Change |
|------|--------|
| `database/learners-db.php` | Add progression methods |
| `shortcodes/learner-single-display-shortcode.php` | Enable progressions tab |
| `components/learner-tabs.php` | Wire up progressions tab |
| `ajax/learners-ajax-handlers.php` | Pattern reference |
| `learners-plugin.php` | Register new shortcodes/handlers |

---

## Percentage Calculation

```
LP Completion % = (hours_completed / product_duration) * 100
Overall % = SUM(completed LP hours) / SUM(all enrolled LP durations) * 100
```

---

## Verification

1. Create learner, assign to class with LP
2. Log hours via admin UI
3. Mark LP complete when portfolio submitted
4. Verify progression record created
5. Verify new LP can be started
6. Generate monthly progressions report
7. Export and verify data for regulatory compliance

---

## Unresolved Questions

1. **Hours source**: Auto-calculate from attendance schedule or manual entry by office?
2. **Portfolio upload**: File upload on completion or just confirmation checkbox?
3. **Late starters**: Start at LP1 regardless of class position, or different handling?
4. **Packages**: Implement same phase or defer to later?
5. **Historical data**: Migration needed or fresh start?
