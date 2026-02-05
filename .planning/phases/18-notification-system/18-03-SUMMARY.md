---
phase: 18
plan: 03
subsystem: notification-services
tags: [notification, event-pipeline, class-events, refactor]

dependency-graph:
  requires:
    - "18-01: ClassEventRepository, ClassEventDTO, EventType enum"
    - "18-02: EventDispatcher"
  provides:
    - "Notification pipeline services reading from class_events table"
    - "Status workflow: pending -> enriching -> enriched -> sending -> sent/failed"
  affects:
    - "18-04: Dashboard service uses same ClassEventRepository"
    - "18-05: AJAX handlers invoke these services"

tech-stack:
  patterns:
    - "Repository pattern for data access"
    - "DTO pattern for typed event data"
    - "Event-driven workflow with status transitions"

key-files:
  modified:
    - src/Events/Services/NotificationProcessor.php
    - src/Events/Services/NotificationEnricher.php
    - src/Events/Services/NotificationEmailer.php

decisions:
  - key: "EventType to operation mapping"
    value: "CLASS_INSERT/LEARNER_ADD -> INSERT, CLASS_UPDATE/LEARNER_UPDATE/STATUS_CHANGE -> UPDATE, CLASS_DELETE/LEARNER_REMOVE -> DELETE"
    rationale: "NotificationSettings still uses legacy operation strings; mapping preserves recipient lookup behavior"

metrics:
  duration: "2 minutes"
  completed: "2026-02-05"
---

# Phase 18 Plan 03: Update Notification Services Summary

Refactored 3-stage notification pipeline to read from class_events table instead of dropped class_change_logs.

## What Was Done

### Task 1: NotificationProcessor Refactored
- Removed direct SQL queries for class_change_logs
- Uses `ClassEventRepository.findPendingForProcessing()` for batch fetching
- Maps EventType enum to legacy operation strings for recipient lookup
- Updates status to 'enriching' or 'sending' before scheduling jobs
- Changed job arguments from log_id to event_id

**Commit:** `1f682fe`

### Task 2: NotificationEnricher Refactored
- Removed direct SQL queries for class_change_logs
- Uses `ClassEventRepository.findByEventId()` for event lookup
- Extracts data from eventData JSONB (new_row, old_row, diff)
- Uses `updateAiSummary()` for AI summary persistence
- Updates status to 'enriched' after AI processing

**Commit:** `70a249e`

### Task 3: NotificationEmailer Refactored
- Removed direct SQL queries for class_change_logs
- Uses `ClassEventRepository.findByEventId()` for event lookup
- Uses `markSent()` for sent_at timestamp tracking
- Updates status to 'sent' on success, 'failed' on error

**Commit:** `b20d463`

## Pipeline Flow

```
[Event Captured]
      |
      v
  [pending] ─── NotificationProcessor.process()
      |
      ├── AI needed?
      │      |
      │      Yes ──> [enriching] ─── NotificationEnricher.enrich()
      │                    |
      │                    v
      │              [enriched] ─────┐
      │                              │
      No ─────> [sending] ───────────┤
                                     v
                          NotificationEmailer.send()
                                     |
                          ┌──────────┴──────────┐
                          v                     v
                       [sent]               [failed]
```

## Key Implementation Details

### EventType to Operation Mapping
All 3 services share identical mapping logic:
```php
match ($event->eventType->value) {
    'CLASS_INSERT', 'LEARNER_ADD' => 'INSERT',
    'CLASS_UPDATE', 'LEARNER_UPDATE', 'STATUS_CHANGE' => 'UPDATE',
    'CLASS_DELETE', 'LEARNER_REMOVE' => 'DELETE',
    default => 'UPDATE',
};
```

### Data Access Pattern
All services now use ClassEventRepository instead of direct SQL:
- `findPendingForProcessing($limit)` - batch fetch pending events
- `findByEventId($id)` - fetch single event with all JSONB data parsed
- `updateStatus($id, $status)` - update notification_status
- `updateAiSummary($id, $summary)` - persist AI enrichment with enriched_at
- `markSent($id)` - set notification_status='sent' and sent_at=now()

## Deviations from Plan

None - plan executed exactly as written.

## Verification Results

| Check | Result |
|-------|--------|
| PHP lint all 3 files | Pass |
| No class_change_logs references | Pass |
| All use ClassEventRepository | Pass |
| No log_id references | Pass |
| Status updates implemented | Pass |

## Next Phase Readiness

- Services ready for AJAX handlers (18-05)
- Status workflow complete for dashboard display (18-04)
- No blockers identified
