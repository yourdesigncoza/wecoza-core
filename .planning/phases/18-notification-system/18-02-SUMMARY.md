---
phase: 18-notification-system
plan: 02
subsystem: events
tags: [events, dispatcher, notifications, action-scheduler]

dependency-graph:
  requires: [18-01]
  provides: [EventDispatcher, event-dispatch-api]
  affects: [18-03, 18-04]

tech-stack:
  added: []
  patterns: [service-class, static-factory, dto-pattern]

key-files:
  created:
    - src/Events/Services/EventDispatcher.php
  modified: []

decisions:
  - id: always-record-events
    choice: "Record events even when notifications disabled"
    rationale: "Audit trail separate from notification delivery"
  - id: significant-fields-filter
    choice: "Filter UPDATE events by significant field changes"
    rationale: "Prevent notification spam from minor edits"
  - id: filter-hook
    choice: "Add wecoza_event_dispatch_enabled filter"
    rationale: "Site-specific customization for event dispatch"

metrics:
  duration: 4m
  completed: 2026-02-05
---

# Phase 18 Plan 02: EventDispatcher Service Summary

EventDispatcher bridges controller actions to notification pipeline via Action Scheduler.

## What Was Built

### EventDispatcher Service (`src/Events/Services/EventDispatcher.php`)

**Core Dispatch Methods:**
- `dispatchClassEvent(EventType, classId, newRow, oldRow)` - Class INSERT/UPDATE/DELETE
- `dispatchLearnerEvent(EventType, learnerId, classId, eventData)` - Learner ADD/REMOVE/UPDATE
- `dispatchStatusChange(classId, oldStatus, newStatus, classData)` - Status transitions

**Static Convenience Methods:**
- `EventDispatcher::classCreated(classId, classData)`
- `EventDispatcher::classUpdated(classId, newData, oldData)`
- `EventDispatcher::classDeleted(classId, classData)`
- `EventDispatcher::learnerAdded(classId, learnerId, learnerData)`
- `EventDispatcher::learnerRemoved(classId, learnerId, learnerData)`

**Filtering & Configuration:**
- `isSignificantChange(diff)` - Check if change affects significant fields
- `isNotificationEnabled(EventType)` - Check if recipient configured
- `getSignificantFields()` - List of significant class fields
- `wecoza_event_dispatch_enabled` filter for site customization

**Significant Class Fields:**
```php
['class_status', 'start_date', 'end_date', 'learner_ids', 'event_dates',
 'class_facilitator', 'class_coach', 'class_assessor', 'original_start_date',
 'client_id', 'class_type', 'class_subject']
```

### Action Scheduler Integration

Events scheduled via:
```php
as_enqueue_async_action('wecoza_process_event', ['event_id' => $eventId], 'wecoza-notifications');
```

## Key Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Event Recording | Always record regardless of notification settings | Audit trail separate from delivery |
| UPDATE Filtering | Only dispatch if significant fields changed | Prevent notification spam |
| Filter Hook | `wecoza_event_dispatch_enabled` | Site-specific customization |
| Diff Computation | Full before/after with field-level diff | AI enrichment needs context |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Created missing 18-01 artifacts**
- **Found during:** Plan initialization
- **Issue:** Plan 18-02 depends on EventType, ClassEventDTO, ClassEventRepository which existed but ClassEventRepository was not committed
- **Fix:** Verified 18-01 commits exist (b0d4bdc, 0c4e23f, cbf0314)
- **Files verified:** EventType.php, ClassEventDTO.php, ClassEventRepository.php

## Commits

| Hash | Message |
|------|---------|
| fe7a8ae | feat(18-02): create EventDispatcher service |
| 15f82cb | feat(18-02): add event filtering configuration |

## Usage Examples

```php
// Simple class creation notification
EventDispatcher::classCreated($classId, $classData);

// Class update with diff tracking
EventDispatcher::classUpdated($classId, $newData, $oldData);

// Learner assignment
EventDispatcher::learnerAdded($classId, $learnerId, $learnerData);

// Instance-based with DI
$dispatcher = new EventDispatcher($repository);
$dispatcher->dispatchStatusChange($classId, 'pending', 'active', $classData);
```

## Next Phase Readiness

**Ready for 18-03 (Controller Integration):**
- EventDispatcher API stable
- Static methods available for easy integration
- Significant change filtering prevents over-notification

**Dependencies provided:**
- `EventDispatcher` service class
- Event dispatch API with static methods
- Action Scheduler hook: `wecoza_process_event`
