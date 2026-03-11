# WEC-188 — Agent Orders & Payment Tracking (Mario's Feedback 2026-03-10)

## Delivery Notes: SCRAPPED

Not part of the process. Remove from scope.

## What is an Agent Order?

Agents are independent contractors under an agent agreement (like an SLA). They only work when an order is issued.

An order specifies:
- Training location
- Days & times
- Start date & end date
- Rate (per hour or per day)

## Order Number = Class Activation

- Order number activates the class
- Without it, nothing can happen in that class (no capturing)
- Currently created in accounting system, provided to operations team, sent to facilitator

## Current Pain (Manual Process)

1. Orders extracted from accounting system to Google Sheets
2. Columns added manually: client invoice, agent invoice, captured attendance
3. Agent claims captured manually each month next to his order
4. Attendance hours captured manually
5. Client invoice amount captured manually
6. All three must match before agent is paid

## What WeCoza 3.0 Should Do

### Class Creation Linkage
When a class is created, the agent order is essentially created (days, times, duration, location). The order number confirms finance approved it.

### Attendance = Class Hours
When an agent captures attendance (even zeros), it counts as a class day.
- 3-hour class = 3 hours trained that day, regardless of individual learner absence
- These are the hours invoiced to the client

### All-Learners-Absent Rule
If ALL learners are absent on a day:
- Agent claims only 1 hour (not full class hours) — compensation for travel only
- No training occurred

### Agent Invoice Capture
When agent captures last entries for the month, an additional input opens for the agent's invoice (hours invoiced for the month for the class).

### Monthly Summary (Per Class, Per Agent)

| Metric | Example |
|--------|---------|
| Class Hours (total trained) | 24 hours |
| All-Learners-Absent hours | 3 hours |
| Agent Invoice hours | 22 hours |

Logic: 24h trained, 1 day all absent (3h class), agent should claim 24 - 3 + 1 = 22h.
If agent claims more, discrepancy is visible.

## Goals

1. Pay agents correct hours automatically
2. Eliminate manual Google Sheets reconciliation
3. Eliminate paper-based agent invoices
4. Invoice clients correctly from system data
5. Detect dishonest claims (agent invoicing more than entitled)

## Implementation Notes

- `order_number` field on class — required to activate capturing
- Track "all learners absent" days automatically from attendance data
- Monthly rollup: class_hours, absent_days_hours, agent_claimed_hours
- Agent invoice UI: appears at end-of-month capturing
- Reconciliation view: compare calculated vs claimed, flag discrepancies
