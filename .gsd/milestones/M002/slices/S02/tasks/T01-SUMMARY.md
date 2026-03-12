---
id: T01
parent: S02
milestone: M002
provides:
  - HistoryService facade with 4 timeline methods
  - getClassTimeline (7 keys), getAgentTimeline (7 keys), getLearnerTimeline (5 keys), getClientTimeline (4 keys)
  - Derived client/agent/learner lists from class relationships
key_files:
  - src/Classes/Services/HistoryService.php
  - tests/History/HistoryServiceTest.php
key_decisions:
  - Client timeline derives learner list by querying learner_ids JSONB from associated classes
  - Agent timeline derives clients from primary classes
  - Learner timeline derives clients from class enrollments
patterns_established:
  - HistoryService composes HistoryRepository methods; no direct DB queries except deriveLearnerListFromClasses
  - Constructor injection with null-coalescing for HistoryRepository
observability_surfaces:
  - Timeline methods return structured arrays; empty sub-arrays for missing entities
duration: 15m
verification_result: passed
completed_at: 2026-03-12
blocker_discovered: false
---

# T01: HistoryService Facade — Unified Timeline Methods

**Created HistoryService with 4 timeline methods covering all WEC-189 data points — 101 checks passing.**

## What Happened

Built `HistoryService` in `src/Classes/Services/` composing all 13 HistoryRepository methods into 4 entity-specific timeline arrays:

1. **`getClassTimeline(classId)`** → 7 keys: agent_assignments, learner_assignments, status_changes, stop_restart_dates, qa_visits, events, notes
2. **`getAgentTimeline(agentId)`** → 7 keys: primary_classes, backup_classes, notes, absences, qa_visits, subjects, clients (derived)
3. **`getLearnerTimeline(learnerId)`** → 5 keys: class_enrollments, hours_logged, portfolios, progression_dates, clients (derived)
4. **`getClientTimeline(clientId)`** → 4 keys: classes, locations, agents (derived), learners (derived from JSONB)

## Verification

- `php tests/History/HistoryServiceTest.php` — **101 passed, 0 failed**
- Live data: Client 1 correctly derives 9 learners from 2 classes

## Files Created/Modified

- `src/Classes/Services/HistoryService.php` — HistoryService facade
- `tests/History/HistoryServiceTest.php` — Added HistoryService tests (101 total)
