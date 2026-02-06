# Requirements: WeCoza Core v1.3

**Defined:** 2026-02-06
**Core Value:** Single source of truth for all WeCoza functionality — unified plugin architecture

## v1.3 Requirements

### Dashboard Data Source

- [ ] **DASH-01**: Dashboard shows all classes with "Deliveries" events from `classes.event_dates` JSONB
- [ ] **DASH-02**: Dashboard statistics reflect delivery event counts (total, pending, completed)
- [ ] **DASH-03**: Dashboard displays delivery task status (Pending/Completed from event_dates)
- [ ] **DASH-04**: Dashboard shows class code, subject, client/site, start date for each delivery record

### Filtering & Actions

- [ ] **FILT-01**: User can filter dashboard by delivery status (pending/completed)
- [ ] **FILT-02**: User can search dashboard by class code, subject, or client name
- [ ] **FILT-03**: Dashboard preserves existing cron notification type column (orange/red) where applicable

### Cron Integration

- [ ] **CRON-01**: Existing cron notification system continues to work independently
- [ ] **CRON-02**: Cron notification status shown as supplementary info on dashboard records

## Future Requirements

None identified.

## Out of Scope

| Feature | Reason |
|---------|--------|
| Rewriting the cron notification system | v1.3 focuses on dashboard data source, cron stays as-is |
| Adding new event types | Dashboard shows existing Deliveries events only |
| Material tracking AJAX mark-as-delivered rework | Current AJAX action works for cron records, may need future attention |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| DASH-01 | Phase 19 | Pending |
| DASH-02 | Phase 19 | Pending |
| DASH-03 | Phase 19 | Pending |
| DASH-04 | Phase 19 | Pending |
| FILT-01 | Phase 19 | Pending |
| FILT-02 | Phase 19 | Pending |
| FILT-03 | Phase 19 | Pending |
| CRON-01 | Phase 19 | Pending |
| CRON-02 | Phase 19 | Pending |

**Coverage:**
- v1.3 requirements: 9 total
- Mapped to phases: 9
- Unmapped: 0

---
*Requirements defined: 2026-02-06*
*Last updated: 2026-02-06 after initial definition*
