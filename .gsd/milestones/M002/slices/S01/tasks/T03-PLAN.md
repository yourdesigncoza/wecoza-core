# T03 — HistoryRepository Extensions: QA Visits, Portfolios, Class Notes, Events

**Slice:** S01
**Milestone:** M002

## Goal
Extend HistoryRepository with additional query methods for data points identified in the Gemini audit: QA visits, learner portfolios, class notes, class events, and agent subjects/levels. These fill gaps so the S02 HistoryService facade has all raw data available.

## Must-Haves

### Truths
- getClassQAVisits(classId) returns QA visit records for a class
- getClassEvents(classId) returns event history from events_log + event_dates JSONB
- getClassNotes(classId) returns parsed class_notes_data JSONB
- getLearnerPortfolios(learnerId) returns portfolio files with LP context
- getLearnerProgressionDates(learnerId) returns start/completion dates per LP tracking entry
- getAgentQAVisits(agentId) returns QA visits across all agent's classes
- getAgentSubjects(agentId) returns distinct subjects/levels/modules facilitated
- All methods return empty arrays for non-existent IDs

### Artifacts
- Updated `src/Classes/Repositories/HistoryRepository.php` — 7 new methods
- Updated `tests/History/HistoryServiceTest.php` — tests for new methods

## Steps
1. Add getClassQAVisits() — query qa_visits table
2. Add getClassEvents() — query events_log + parse event_dates JSONB
3. Add getClassNotes() — parse class_notes_data JSONB from classes table
4. Add getLearnerPortfolios() — query learner_progression_portfolios with LP context
5. Add getLearnerProgressionDates() — query learner_lp_tracking for start/completion dates
6. Add getAgentQAVisits() — QA visits via agent's classes
7. Add getAgentSubjects() — distinct class_type + class_subject from agent's classes
8. Update tests and verify
