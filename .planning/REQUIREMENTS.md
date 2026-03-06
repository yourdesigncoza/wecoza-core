# Requirements: WeCoza Core

**Defined:** 2026-03-06
**Core Value:** Single source of truth for all WeCoza functionality — unified plugin infrastructure

## v8.0 Requirements

Requirements for Page Tracking & Report Extraction milestone. Each maps to roadmap phases.

### Page Tracking

- [ ] **PAGE-01**: Agent can capture current workbook page number per learner during attendance session
- [ ] **PAGE-02**: Page number is stored per learner per session alongside hours data
- [ ] **PAGE-03**: Actual page progression percentage is calculated and displayed (current page / total pages in module)
- [ ] **PAGE-04**: Page progression is visible on the progression admin panel alongside hours-based progression

### Report Extraction

- [ ] **RPT-01**: Admin can generate a per-class report extraction
- [ ] **RPT-02**: Report includes class header: Client Name, Site Name, Class Type & Subject, Month, Class Days, Class Times, Facilitator
- [ ] **RPT-03**: Report includes per-learner rows: Surname & Initials, Current Level/Module, Start Date
- [ ] **RPT-04**: Report includes hours columns: Current Month Trained, Current Month Present, Total Trained, Total Present
- [ ] **RPT-05**: Report includes progression columns: Hours-based %, Actual page progression %
- [ ] **RPT-06**: Report is downloadable as CSV
- [ ] **RPT-07**: Report includes learner Race and Gender columns

## Future Requirements

### Target Page Progression

- **TPAG-01**: Admin can define target page numbers per module/level (requires Mario input on target logic)
- **TPAG-02**: Target page progression % is calculated and compared against actual page progression
- **TPAG-03**: On-track/behind status is displayed per learner based on target vs actual pages

### Report Templates

- **RTPL-01**: Summary report template replicated in WeCoza
- **RTPL-02**: Attendance register template replicated in WeCoza
- **RTPL-03**: Progress report template replicated in WeCoza
- **RTPL-04**: Individual learner report template replicated in WeCoza
- **RTPL-05**: Automated monthly report emails sent to clients

### Export Formats

- **XFMT-01**: Report downloadable as Excel (.xlsx) format

## Out of Scope

| Feature | Reason |
|---------|--------|
| Target page progression | Requires Mario to define target page numbers per module — deferred until after call |
| Automated monthly emails | Mario wants to finalize report layout in Google Sheets first, then replicate |
| Report templates (summary, register, progress, individual) | Separate milestone after Mario designs layouts |
| Excel export | CSV sufficient for v8.0; Excel can be added later |
| Agent edit form wp_user_id field | Minor UX fix, separate scope (AGT-09/10) |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| PAGE-01 | Pending | Pending |
| PAGE-02 | Pending | Pending |
| PAGE-03 | Pending | Pending |
| PAGE-04 | Pending | Pending |
| RPT-01 | Pending | Pending |
| RPT-02 | Pending | Pending |
| RPT-03 | Pending | Pending |
| RPT-04 | Pending | Pending |
| RPT-05 | Pending | Pending |
| RPT-06 | Pending | Pending |
| RPT-07 | Pending | Pending |

**Coverage:**
- v8.0 requirements: 11 total
- Mapped to phases: 0
- Unmapped: 11

---
*Requirements defined: 2026-03-06*
*Last updated: 2026-03-06 after initial definition*
