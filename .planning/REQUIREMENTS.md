# Requirements: WeCoza Core

**Defined:** 2026-03-11
**Core Value:** Single source of truth for all WeCoza functionality — unified plugin infrastructure

## v9.0 Requirements

Requirements for Agent Orders & Payment Tracking milestone. Each maps to roadmap phases.

### Agent Orders

- [ ] **ORD-01**: Admin can set agent rate (hourly/daily) and amount for a class assignment
- [ ] **ORD-02**: Agent order created automatically when class has order_nr and assigned agent
- [x] **ORD-03**: System supports rate changes mid-class (new order row with different start_date)
- [x] **ORD-04**: Data migration populates agent_orders for existing active classes

### Attendance Enhancement

- [ ] **ATT-01**: System detects when all learners have 0 hours present in a capture
- [ ] **ATT-02**: Agent confirms "all learners absent" via prompt before submission

### Agent Invoicing

- [ ] **INV-01**: Agent can view auto-calculated monthly summary (class hours, absent days, payable hours)
- [ ] **INV-02**: Agent can submit claimed hours for a month with optional notes
- [ ] **INV-03**: System calculates discrepancy between claimed and calculated payable hours
- [ ] **INV-04**: Invoice section appears on single class view (inline with attendance)

### Reconciliation

- [ ] **REC-01**: Admin can view reconciliation summary per class per month
- [ ] **REC-02**: Admin can approve an agent's monthly invoice
- [ ] **REC-03**: Admin can dispute an agent's monthly invoice
- [ ] **REC-04**: Discrepancies are visually flagged (overclaim highlighted)

## Future Requirements

### Client Invoicing
- **CINV-01**: System auto-calculates client invoice amount from class hours and client rate
- **CINV-02**: Monthly client invoice report with class breakdown

### Bulk Reconciliation
- **BREC-01**: Admin can view reconciliation across all classes/agents for a month
- **BREC-02**: Bulk approve multiple invoices at once

## Out of Scope

| Feature | Reason |
|---------|--------|
| Delivery notes | Mario confirmed: not part of agent order process |
| Accounting system integration | Orders created in external accounting; WeCoza tracks reference only |
| Agent payment processing | WeCoza calculates hours; actual payment happens externally |
| Non-progression report | Mario: stuck learners surface via excessive hours (WEC-187), separate milestone |
| Client billing/invoicing | Separate domain — agent payment reconciliation only for v9.0 |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| ORD-01 | Phase 62 | Pending |
| ORD-02 | Phase 60 | Pending |
| ORD-03 | Phase 59 | Complete |
| ORD-04 | Phase 59 | Complete |
| ATT-01 | Phase 61 | Pending |
| ATT-02 | Phase 61 | Pending |
| INV-01 | Phase 62 | Pending |
| INV-02 | Phase 62 | Pending |
| INV-03 | Phase 60 | Pending |
| INV-04 | Phase 62 | Pending |
| REC-01 | Phase 63 | Pending |
| REC-02 | Phase 63 | Pending |
| REC-03 | Phase 63 | Pending |
| REC-04 | Phase 63 | Pending |

**Coverage:**
- v9.0 requirements: 14 total
- Mapped to phases: 14
- Unmapped: 0 ✓

---
*Requirements defined: 2026-03-11*
*Last updated: 2026-03-11 — traceability mapped during roadmap creation*
