## Summary

Standalone reporting page tracking learner level progression with timeline visualization, employer filtering, and aggregate statistics.

**Target Plugin:** `wecoza-learners-plugin`

---

## Key Features

* Search by learner name/ID
* Filter by employer/company
* Timeline visualization: Level + Class Name + Date
* Statistics: progression rates, level distribution, company comparisons
* Export to PDF and Excel

**Shortcode:** `[wecoza_learner_progression_report]`

---

## Use Cases

1. **Individual learner journey** - Track one learner across all classes/levels over time
2. **Employer/company report** - Show progress of all learners from a specific employer
3. **Aggregate statistics** - Overall progression rates, level distribution, company comparisons

---

## UI Layout

```
+------------------------------------------------------------------+
| Learner Progression Report                                        |
+------------------------------------------------------------------+
| [Search Learner: ___________] [Employer: ▼ Select ___]  [Search] |
+------------------------------------------------------------------+

+------------------------------------------------------------------+
| STATISTICS DASHBOARD (collapsible)                                |
+------------------------------------------------------------------+
| | Progression Rate | | Level Distribution | | Company Compare |   |
| | Chart.js Gauge   | | Bar Chart          | | Comparison Table|   |
+------------------------------------------------------------------+

+------------------------------------------------------------------+
| TIMELINE VIEW                                                     |
+------------------------------------------------------------------+
| Learner: John Smith (ABC Corp)                    [PDF] [Excel]  |
+------------------------------------------------------------------+
|  ●━━━━━●━━━━━━━━●━━━━━━━━━━━●                                    |
|  L1    L2       L3          L4                                   |
|                                                                   |
| Level 1 │ Welding Basics - Class A │ 15 Jan 2025                 |
| Level 2 │ Welding Advanced - Class B │ 28 Mar 2025               |
| Level 3 │ Welding Expert - Class C │ 12 Jul 2025                 |
+------------------------------------------------------------------+
```

---

## Implementation Phases

### Phase 1: Core Infrastructure

- [ ] Create `LearnerProgressionController.php`
- [ ] Create `LearnerProgressionModel.php`
- [ ] Register shortcode
- [ ] Set up AJAX endpoints

### Phase 2: Individual Learner View

- [ ] Create `progression-report.php` view
- [ ] Implement learner search (autocomplete)
- [ ] Build timeline visualization
- [ ] Create detail table

### Phase 3: Employer Filter

- [ ] Add employer dropdown (Select2)
- [ ] Implement multi-learner view
- [ ] Group timelines by learner

### Phase 4: Statistics Dashboard

- [ ] Chart.js progression rate gauge
- [ ] Level distribution bar chart
- [ ] Company comparison table
- [ ] Make dashboard collapsible

### Phase 5: Export Functionality

- [ ] Install TCPDF/DOMPDF
- [ ] Install PhpSpreadsheet
- [ ] Implement PDF export
- [ ] Implement Excel export

---

## Files to Create

| File | Purpose |
| -- | -- |
| `app/Controllers/LearnerProgressionController.php` | AJAX handlers, shortcode |
| `app/Models/LearnerProgressionModel.php` | Database queries |
| `app/Views/components/progression-report.php` | Main page template |
| `assets/js/learner-progression-report.js` | Timeline, filters, charts |

---

## Database Tables Used

* `learner_progressions` - Core progression records
* `learners` - Learner details
* `classes` - Class information
* `employers` - Company data

---


