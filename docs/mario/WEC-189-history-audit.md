# WEC-189 — Change History & Audit Trail (Mario's Feedback 2026-03-10)

## Audit Trail Decision

- **Priority:** Nice to have (not critical)
- **Concern:** "everyone playing policeman" — don't want staff wasting time checking who did what
- **Detail level:** High level only
- **Entities:** Class changes & Learner changes
- **Retention:** 3 years

## Entity History (More Important Than Audit Trail)

Mario emphasized that **entity relationship history** matters more than change tracking.

### Learner History
- Class history (which classes was the learner in)
- Client history (at which clients did the learner train)
- Levels completed
- Portfolios (for each level/module/subject completed)
- Progression dates (start/completion dates for levels/modules/subjects)

### Agent History
- Class history (which classes facilitated, how long)
- Client history (at which clients facilitated, how long)
- Learner history (which learners trained — optional/nice-to-have)
- Subject/level/module history (what did they facilitate previously)
- Agent performance notes
- QA reports from QA visits at their classes

### Client History
- Learner history (all learners trained under this client)
- Class history (all classes under this client)
- Monthly report history
- Agent history (which agents trained here)

### Class History
- Learner history (learners trained in this class)
- Progression history
- Agent history (agents placed in this class)
- Notes on the class
- Events history (all events + dates)

## Implementation Notes

- Most entity history is already derivable from existing relational data (learner-class, agent-class, class-client links)
- Key gap: historical records when relationships change (agent replaced, learner moved)
- Need timestamped relationship records, not just current state
- Audit trail: simple log table (who, what entity, what changed, when) — high level only
- 3-year retention with periodic cleanup
