# WeCoza 3.0 — Implementation Proposals for Remaining Gaps

**Date:** 2026-03-10
**Status:** Awaiting client validation before detailed planning

---

## Priority 1: Products/Curriculum Rules Engine

### What Already Exists
- `class_types` table with 8 types (AET, REALLL, SOFT, GETC, BA2-BA4, WALK/HEXA/RUN)
- `class_type_subjects` table with subject codes, names, durations (e.g., BA2LP1=72h, RLC=160h)
- `subject_selection_mode` ('own', 'all_subjects', 'progression')
- `progression_total_hours` for GETC/BA types
- `ClassTypesController` serving this data to forms
- All level codes from the original spec already seeded (CL1B-CL4, RLC/RLN/RLF, BA2LP1-10, etc.)

### What's Missing
- **Product rules** — no threshold definitions for "excessive training hours"
- **Flag/alert system** — no mechanism to flag learners who exceed duration thresholds
- **Progression rules per product** — no definition of "what comes after CL2?" or prerequisite chains
- **Product-specific assessment type** — no way to define whether a subject uses POE vs Exam vs Competency-based

### Proposal
Add `product_rules` JSONB column (or small related table) to `class_type_subjects` storing: max_hours_threshold, progression_path (next subject), assessment_type (poe/exam/competency), flag triggers. Build a `ProductRulesService` to evaluate rules against learner progression data.

### Open Question
Is the curriculum structure working well enough as-is, or does the client want a standalone "Products" admin screen? Is the gap a rules engine on existing data, or a whole new entity?

---

## Priority 2: Exam/GETC Workflow

### What Already Exists
- `exam_class` boolean, `exam_type` text, `exam_learners` JSONB on classes
- Generic LP tracking (hours, status, portfolio upload for completion)
- Placement assessment fields on learners

### What's Missing
- Mock exam tracking (spec: 3 required before final)
- SBA marking and scanning workflow
- Exam results/marks storage
- Multi-step assessment pipeline: Mock 1 → Mock 2 → Mock 3 → SBA → Final Exam
- Level 4/GETC-specific progression path

### Proposal
New `learner_assessments` table tracking individual assessment events (type, result, scanned_file, date). A GETC Assessment Workflow service enforcing: 3 mocks passed → SBA completed & scanned → eligible for final exam. Integrates with ProgressionService — Level 4 LP completion requires exam workflow satisfaction.

### Open Questions
- SBA "marked and scanned" — upload only, or marks entry + upload?
- What data is captured per mock exam? Just pass/fail, or actual marks?

---

## Priority 3: Threshold-Based Reports

### What Already Exists
- `learner_hours_log` with full audit trail
- `learner_lp_tracking` with hours and status
- `class_type_subjects.subject_duration`
- ReportService with CSV export

### What's Missing
- Excessive Training Hours Report (compare hours to threshold)
- Non-Progression Report (identify stale in_progress LPs)
- Configurable thresholds per product

### Proposal
Two new report views querying learner_lp_tracking joined with class_type_subjects:
1. Excessive Hours: WHERE hours_trained > subject_duration * threshold
2. Non-Progression: WHERE status='in_progress' AND start_date < expected duration

Depends on Priority 1 for threshold values, but can start with sensible defaults.

### Open Question
Standalone report pages, or tabs within existing report UI?

---

## Priority 4: Agent Orders & Payments

### What Already Exists
- TaskManager handles agent-order as task type with order number validation
- `classes.order_nr` and `order_nr_metadata`
- Auto-activation: draft→active when order number entered

### What's Missing
- No agent_orders entity with line items/dates/amounts
- No payment tracking
- No invoice/delivery note workflow
- No agent payment history

### Proposal — Scope Options
- **(a) Lightweight:** Track order numbers, delivery dates, payment status on existing classes
- **(b) Medium:** New agent_orders table with order details linked to classes
- **(c) Full:** Complete invoicing with line items, amounts, payment reconciliation

### Open Question
Which scope is expected? Simple tracking or full invoicing system?

---

## Priority 5: History/Audit Trail

### What Already Exists
- `class_status_history` — every status change
- `events_log` — system event processing
- `class_events` — class/learner events with soft-delete
- `learner_lp_tracking` — completed LPs as history
- `learner_hours_log` — hours audit trail
- `created_at`/`updated_at`/`created_by`/`updated_by` on most tables

### What's Missing
- No field-level change tracking
- No agent/learner modification history
- No exam history table

### Proposal — Recommended: PostgreSQL Triggers
Create `audit_log` table with PostgreSQL trigger functions on key tables (learners, agents, classes, clients). Captures: table, record_id, action, old/new values (JSONB), who, when. Zero application code changes — all at DB level.

### Open Questions
- Field-level tracking needed, or high-level "who did what when"?
- Data retention period?
