---
phase: 13-notification-card-agent-name-display-ack
plan: 01
subsystem: Events/Notifications
tags: [notifications, agent-name, acknowledge, soft-delete, ajax]
dependency_graph:
  requires: [class_events table, agents table]
  provides: [agent name display, badge swap on acknowledge, delete with audit trail]
  affects: [AISummaryShortcode, NotificationDashboardService, ClassEventRepository, card/timeline/item views]
tech_stack:
  added: []
  patterns: [soft-delete pattern with deleted_by audit, in-request cache for agent name resolution]
key_files:
  created:
    - schema/add-soft-delete-to-class-events.sql
  modified:
    - src/Events/Services/NotificationDashboardService.php
    - src/Events/Views/Presenters/AISummaryPresenter.php
    - src/Events/Repositories/ClassEventRepository.php
    - src/Events/Shortcodes/AISummaryShortcode.php
    - views/events/ai-summary/card.php
    - views/events/ai-summary/timeline.php
    - views/events/ai-summary/item.php
    - views/events/ai-summary/main.php
decisions:
  - Agent name resolved from agents table using event_data->new_row->class_agent (not class_events.user_id)
  - In-request array cache on NotificationDashboardService avoids repeated DB queries for same agent across multiple notifications
  - Soft-delete uses deleted_at TIMESTAMPTZ + deleted_by INTEGER; requires user-run DDL in schema/add-soft-delete-to-class-events.sql
  - status-badge wrapper with data-role allows JS to swap NEW to Read without page refresh
  - notification-count badge uses data-role for JS updates after acknowledge and delete
metrics:
  duration: 4 min
  completed: 2026-02-18
  tasks_completed: 2
  tasks_pending_verify: 1
  files_modified: 9
---

# Quick Task 13: Notification Card Agent Name Display + Acknowledge Badge Swap + Delete

**One-liner:** Agent name from agents table in notification cards, acknowledge swaps NEW-to-Read badge with count update, delete button soft-deletes with WordPress user ID audit trail.

## What Was Built

### Task 1: Agent name resolution + acknowledge badge swap + notification counts

**Agent name resolution (NotificationDashboardService):**
- Added `private array $agentNameCache = []` for in-request caching
- Added `resolveAgentName(int $agentId): string` — queries `SELECT first_name, surname FROM agents WHERE agent_id = :id`, returns "First Last" or "Unknown Agent" on failure
- In `transformForDisplay()`, extracts `class_agent` from `event_data['new_row']` and resolves to name
- Added `agent_id` and `agent_name` to the returned transform array

**Presenter (AISummaryPresenter):**
- Passes `agent_id` and `agent_name` through `presentSingle()`
- `agent_name` added to `buildSearchIndex()` — notifications are now searchable by agent name

**Views (card.php, timeline.php, item.php):**
- Agent name rendered below class subject with `<i class="bi bi-person">` icon
- Static `<?php if (!$isRead): ?> NEW badge` replaced with `<span data-role="status-badge">` wrapper containing conditional badge rendering (NEW / Read)

**Notification count (main.php + AISummaryShortcode):**
- `$totalCount = count($records)` and `$acknowledgedCount = $this->service->getAcknowledgedCount()` passed to template
- Count badge now renders: "4 Notifications, 1 Read" via `data-role="notification-count"`

**JS (AISummaryShortcode::getAssets):**
- `markAsAcknowledged()` now swaps `data-role="status-badge"` inner HTML to `<span class="badge badge-phoenix badge-phoenix-success fs-10">Read</span>`
- `updateNotificationCountBadge()` helper updates count badge text
- `markAsAcknowledged()` calls `updateNotificationCountBadge()` with `data.data.acknowledged_count`
- `ajaxMarkAcknowledged` PHP handler now includes `acknowledged_count` in success response

### Task 2: Delete notification with WordPress user ID tracking

**DDL (user must run manually):**
- File: `schema/add-soft-delete-to-class-events.sql`
- Adds `deleted_at TIMESTAMPTZ DEFAULT NULL` and `deleted_by INTEGER DEFAULT NULL` to `class_events`

**ClassEventRepository:**
- `softDelete(int $eventId, int $deletedByUserId): bool` — sets `deleted_at = CURRENT_TIMESTAMP, deleted_by = :deleted_by` where `deleted_at IS NULL`
- `getAcknowledgedCount(): int` — `COUNT(*) WHERE acknowledged_at IS NOT NULL AND deleted_at IS NULL`
- `getTimeline()` updated: `WHERE deleted_at IS NULL` (was no WHERE clause)
- `getUnreadCount()` updated: `AND deleted_at IS NULL` added

**NotificationDashboardService:**
- `getAcknowledgedCount()` delegates to repository
- `deleteNotification(int $eventId, int $deletedByUserId): bool` delegates to repository

**AISummaryShortcode:**
- `wp_ajax_wecoza_delete_notification` registered in `register()`
- `ajaxDeleteNotification()` handler: verifies nonce + `read` capability, validates event_id, calls `$this->service->deleteNotification($eventId, get_current_user_id())`
- Returns unread_count in success response

**Views (card.php, timeline.php, item.php):**
- Delete button added: `btn btn-sm btn-outline-danger` with `data-role="delete-btn"` and `data-event-id`
- Placed next to Acknowledge button in footer/actions area

**JS (AISummaryShortcode::getAssets):**
- `deleteNotification(container, eventId)` function: confirm dialog, fetch POST, fades out item with `opacity 0.3s` transition, updates count badges
- Click handler checks `target.closest('[data-role="delete-btn"]')` before acknowledge-btn check

## Deviations from Plan

None — plan executed exactly as written.

## DDL Required

Before the delete feature works, the user must run:

```sql
ALTER TABLE class_events ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMPTZ DEFAULT NULL;
ALTER TABLE class_events ADD COLUMN IF NOT EXISTS deleted_by INTEGER DEFAULT NULL;
```

File location: `schema/add-soft-delete-to-class-events.sql`

**Note:** Until the DDL is run, the notification shortcode will show an error because `getTimeline()` references `deleted_at`. Run the DDL first.

## Commits

| Hash | Description |
|------|-------------|
| fd37095 | feat(13-01): notification cards — agent name, acknowledge badge swap, delete |

## Self-Check

### Files exist:
- schema/add-soft-delete-to-class-events.sql: created
- src/Events/Services/NotificationDashboardService.php: modified
- src/Events/Views/Presenters/AISummaryPresenter.php: modified
- src/Events/Repositories/ClassEventRepository.php: modified
- src/Events/Shortcodes/AISummaryShortcode.php: modified
- views/events/ai-summary/card.php: modified
- views/events/ai-summary/timeline.php: modified
- views/events/ai-summary/item.php: modified
- views/events/ai-summary/main.php: modified

### Commits exist:
- fd37095: confirmed

## Self-Check: PASSED
