# T01 — HistoryService Facade: Unified Timeline Methods

**Slice:** S02
**Milestone:** M002

## Goal
Create HistoryService that composes HistoryRepository methods into per-entity timeline arrays covering all WEC-189 data points.

## Must-Haves

### Truths
- getClassTimeline(classId) returns merged array with: agent_assignments, learner_assignments, status_changes, stop_restart_dates, qa_visits, events, notes
- getAgentTimeline(agentId) returns: primary_classes, backup_classes, notes, absences, qa_visits, subjects
- getLearnerTimeline(learnerId) returns: class_enrollments, hours_logged, portfolios, progression_dates
- getClientTimeline(clientId) returns: classes, locations, derived agent list, derived learner list
- All methods return structured arrays; empty sub-arrays for missing entities
- Constructor injection with null-coalescing default for HistoryRepository

### Artifacts
- `src/Classes/Services/HistoryService.php`
- Updated `tests/History/HistoryServiceTest.php` with HistoryService tests

## Steps
1. Create HistoryService with HistoryRepository dependency
2. Implement getClassTimeline() — merges getClassHistory + getClassQAVisits + getClassEvents + getClassNotes
3. Implement getAgentTimeline() — merges getAgentHistory + getAgentQAVisits + getAgentSubjects
4. Implement getLearnerTimeline() — merges getLearnerHistory + getLearnerPortfolios + getLearnerProgressionDates
5. Implement getClientTimeline() — merges getClientHistory + derives agent/learner lists from classes
6. Update tests and verify
