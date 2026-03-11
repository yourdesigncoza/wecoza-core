# WEC-187 — Excessive Hours Report (Mario's Feedback 2026-03-10)

## Non-Progression Report: REMOVED

Mario confirmed this is not needed. Stuck learners will surface via excessive hours (hours trained accumulate while learner doesn't progress).

## Excessive Hours Trigger

Fires when total hours trained exceeds the allocated hours for the class/programme.

### Applies To
- AET
- REALLL
- GETC AET
- Business Admin NQF 2-4
- Adult Matric

### Does NOT Apply To
- Walk, Run & Hexa packages (built to go past allocated hours)
- EEP and other soft skills (will never exceed allocated hours)

## Trigger Timing

- Monthly, after month close
- Unresolved flags carry forward each month until resolved

## Action Workflow

1. Flag triggers awareness (dashboard alert or notification)
2. Decision: contact facilitator OR arrange QA visit
3. Capture resolution: note field or dropdown of actions taken
4. Flag remains until action is recorded

## Report Format

Mario chose **Option A** (dashboard-style).

## Implementation Notes

- Need a monthly job/check that runs after month close
- Compare `hours_trained` vs product/programme `allocated_hours`
- Filter by applicable programme types only
- UI: flagged items with action dropdown + notes field
- Resolved/unresolved status tracking
