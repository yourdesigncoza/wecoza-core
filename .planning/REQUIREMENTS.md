# Requirements: WeCoza Core v5.0 — Learner Progression

**Defined:** 2026-02-18
**Core Value:** Complete learner LP progression tracking — from class assignment through completion, with management, reporting, and regulatory compliance.

## v5.0 Requirements

### AJAX Wiring

- [ ] **AJAX-01**: Admin can mark LP as complete via "Mark Complete" button with portfolio upload
- [ ] **AJAX-02**: Admin can upload additional portfolio files to an existing progression
- [ ] **AJAX-03**: Frontend can fetch learner progression data without page reload (current LP, history, overall progress)
- [ ] **AJAX-04**: All three AJAX handlers registered in wecoza-core.php with proper namespace references

### Admin Management

- [ ] **ADMIN-01**: Admin can view all progressions in a filterable table (filter by client, class, LP/product, status)
- [ ] **ADMIN-02**: Admin can bulk-mark multiple progressions as complete
- [ ] **ADMIN-03**: Admin can view audit trail (hours log history) for any progression
- [ ] **ADMIN-04**: Admin management page accessible via shortcode `[wecoza_progression_admin]`
- [ ] **ADMIN-05**: Admin can start a new LP for a learner (manual assignment)
- [ ] **ADMIN-06**: Admin can put an LP on hold or resume it

### Learner Report (WEC-165)

- [ ] **RPT-01**: User can search for a learner by name or ID on the progression report page
- [ ] **RPT-02**: User can view individual learner timeline showing LP progression (level, class, date)
- [ ] **RPT-03**: User can filter learners by employer/client company
- [ ] **RPT-04**: User can view multiple learners grouped by company with individual timelines
- [ ] **RPT-05**: User can see Phoenix-styled summary cards (total learners, completion rate, avg progress, active LPs)
- [ ] **RPT-06**: Report page accessible via shortcode `[wecoza_learner_progression_report]`

### Regulatory Reporting

- [ ] **REG-01**: Admin can generate monthly progressions report (date range filter)
- [ ] **REG-02**: Monthly report shows learner name, LP, class, client, start/completion dates, hours
- [ ] **REG-03**: Admin can export any report/table to CSV
- [ ] **REG-04**: Report data suitable for Umalusi/DHET compliance submissions

### Class Integration

- [ ] **CLASS-01**: Available Learners table in class form shows "Last Completed Course" column
- [ ] **CLASS-02**: Collision detection warns when adding learner with active LP to class
- [ ] **CLASS-03**: Class learner modal shows read-only progression info (current LP, progress bar, status badge)

## v6+ Requirements

Deferred to future milestones.

### Advanced Export
- **EXP-01**: PDF export with formatted layout (requires TCPDF/DOMPDF)
- **EXP-02**: Excel export with formatting (requires PhpSpreadsheet)

### Statistics Dashboard
- **STAT-01**: Chart.js progression rate gauge
- **STAT-02**: Level distribution bar chart
- **STAT-03**: Company comparison interactive table

### Attendance Integration
- **ATT-01**: Auto-sync hours from class schedule data
- **ATT-02**: Auto-sync hours from attendance records
- **ATT-03**: Scheduled recalculation of hours from multiple sources

### Packages
- **PKG-01**: Package support where learners work on different subjects simultaneously

## Out of Scope

| Feature | Reason |
|---------|--------|
| Packages (different subjects per learner) | Deferred per WEC-168 discussion with Mario |
| Chart.js / interactive charts | User chose Phoenix summary cards over charting library |
| PDF/Excel export | User chose CSV only for this milestone |
| Mobile app | Not planned |
| Facilitator portal | Facilitators are read-only per Mario — Wecoza provides LP info |
| Assessment tracking | Mario confirmed NO assessments — hours + portfolio only |
| Auto attendance-to-hours sync | Requires attendance module integration — separate milestone |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| AJAX-01 | — | Pending |
| AJAX-02 | — | Pending |
| AJAX-03 | — | Pending |
| AJAX-04 | — | Pending |
| ADMIN-01 | — | Pending |
| ADMIN-02 | — | Pending |
| ADMIN-03 | — | Pending |
| ADMIN-04 | — | Pending |
| ADMIN-05 | — | Pending |
| ADMIN-06 | — | Pending |
| RPT-01 | — | Pending |
| RPT-02 | — | Pending |
| RPT-03 | — | Pending |
| RPT-04 | — | Pending |
| RPT-05 | — | Pending |
| RPT-06 | — | Pending |
| REG-01 | — | Pending |
| REG-02 | — | Pending |
| REG-03 | — | Pending |
| REG-04 | — | Pending |
| CLASS-01 | — | Pending |
| CLASS-02 | — | Pending |
| CLASS-03 | — | Pending |

**Coverage:**
- v5.0 requirements: 23 total
- Mapped to phases: 0
- Unmapped: 23

---
*Requirements defined: 2026-02-18*
*Last updated: 2026-02-18 after initial definition*
