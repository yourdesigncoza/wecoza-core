# Phase 19: Material Tracking Dashboard Data Source Fix - Research

**Researched:** 2026-02-06
**Domain:** PostgreSQL JSONB queries, WordPress dashboard integration, dual data source management
**Confidence:** HIGH

## Summary

Phase 19 fixes the Material Tracking Dashboard by changing its primary data source from `class_material_tracking` (cron-only records) to `classes.event_dates` JSONB (user-entered Deliveries events). The current system has two disconnected tracking mechanisms:

1. **Event Tasks System (v1.2)**: Users manually add "Deliveries" events in class forms, stored in `classes.event_dates` JSONB with structure `{type, description, date, status, notes, completed_by, completed_at}`. These events show in the Event Tasks Dashboard.

2. **Material Tracking System (v1.0)**: Daily cron job (`wecoza_material_notifications_check`) creates records in `class_material_tracking` table only when classes are exactly 7 or 5 days from start date. The Material Tracking Dashboard only shows these cron records.

**Problem**: Classes with "Deliveries" events show 0 records on Material Tracking Dashboard because it doesn't query `event_dates` JSONB.

**Solution**: Rewrite repository queries to read `event_dates` JSONB for Deliveries-type events as primary data source. Join with `class_material_tracking` to show supplementary cron notification status (orange/red badges). Update presenter and views to handle combined data shape.

**Primary recommendation:** Use PostgreSQL's JSONB array functions (`jsonb_array_elements()`, filtering with `WHERE elem->>'type' = 'Deliveries'`) to unnest event_dates and join with class/client/site data. Map event status to dashboard status. Preserve existing cron notification column as supplementary info.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PostgreSQL JSONB | PostgreSQL 12+ | Structured JSON queries | Native JSONB operators, indexed queries |
| PDO (pdo_pgsql) | PHP 8.0+ | Database connection | Required for WeCoza Core, prepared statements |
| BaseRepository | wecoza-core | CRUD operations | Column whitelisting, SQL injection protection |
| MaterialTrackingRepository | wecoza-core | Material tracking queries | Existing infrastructure, extends BaseRepository |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| jsonb_array_elements() | PostgreSQL 9.4+ | Unnest JSONB arrays | Convert event_dates array to rows |
| jsonb_array_elements_text() | PostgreSQL 9.4+ | Unnest as text | When JSON structure not needed |
| JSONB operators | PostgreSQL 9.4+ | ->>, ->, @>, ? | Extract values, containment checks |
| LEFT JOIN | SQL standard | Preserve null records | Show classes even without cron records |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| JSONB queries | Separate event_tasks table | More normalized but requires schema migration; JSONB query is simpler |
| Dual queries (events + cron) | Single unified table | Would require data migration; dual-source preserves existing cron system |
| PHP JSON decoding | PostgreSQL JSONB functions | PHP approach requires fetching all classes; DB-side filtering is more efficient |

**Installation:**
```bash
# No additional installation needed - PostgreSQL JSONB is standard
# Verify pdo_pgsql extension exists:
php -m | grep pdo_pgsql
```

## Architecture Patterns

### Existing Project Structure
```
src/Events/
├── Controllers/
│   └── MaterialTrackingController.php   # AJAX handler for mark-delivered
├── Repositories/
│   └── MaterialTrackingRepository.php   # ⚠️ NEEDS REWRITE - currently queries class_material_tracking only
├── Services/
│   ├── MaterialTrackingDashboardService.php  # Service layer - filters/permissions
│   └── MaterialNotificationService.php       # Cron job - creates class_material_tracking records
├── Shortcodes/
│   └── MaterialTrackingShortcode.php    # [wecoza_material_tracking] shortcode
└── Views/Presenters/
    └── MaterialTrackingPresenter.php    # ⚠️ NEEDS UPDATE - data shape changes

views/events/material-tracking/
├── dashboard.php        # ⚠️ NEEDS UPDATE - add delivery event date column
├── statistics.php       # ⚠️ NEEDS UPDATE - statistics from event_dates
├── list-item.php        # ⚠️ NEEDS UPDATE - show event date, preserve cron badge
└── empty-state.php      # Keep as-is
```

### Pattern 1: JSONB Array Unnesting with Row Numbers
**What:** Convert JSONB array to rows with index preservation for task identification
**When to use:** Querying event_dates to find specific event types
**Example:**
```sql
-- Source: Event Tasks system pattern (ClassTaskRepository.php line 84)
-- Adapted for Material Tracking Dashboard

SELECT
    c.class_id,
    c.class_code,
    c.class_subject,
    c.original_start_date,
    cl.client_name,
    s.site_name,
    elem->>'type' as event_type,
    elem->>'description' as event_description,
    elem->>'date' as event_date,
    elem->>'status' as event_status,
    elem->>'notes' as event_notes,
    elem->>'completed_at' as event_completed_at,
    (elem_index - 1) as event_index,  -- For task ID generation (0-indexed)
    cmt.notification_type,
    cmt.notification_sent_at,
    cmt.delivery_status as cron_status
FROM classes c
LEFT JOIN clients cl ON c.client_id = cl.client_id
LEFT JOIN sites s ON c.site_id = s.site_id
CROSS JOIN LATERAL jsonb_array_elements(c.event_dates) WITH ORDINALITY AS events(elem, elem_index)
LEFT JOIN class_material_tracking cmt ON cmt.class_id = c.class_id
WHERE elem->>'type' = 'Deliveries'
ORDER BY c.original_start_date DESC, c.class_code, elem_index;
```

**Key insight:** `WITH ORDINALITY` adds `elem_index` column (1-indexed) for row numbers. Subtract 1 to match PHP array indexing.

### Pattern 2: Status Mapping from Event to Dashboard
**What:** Map event_dates status field to dashboard status (Pending/Completed)
**When to use:** Presenting event status in dashboard UI
**Example:**
```php
// Source: MaterialTrackingPresenter.php pattern (lines 115-123)
// Adapted for event_dates source

private function getStatusFromEvent(string $eventStatus): string
{
    return match (strtolower($eventStatus)) {
        'completed' => 'delivered',
        'pending' => 'pending',
        default => 'pending',
    };
}

// In presentRecords():
$record['delivery_status'] = $this->getStatusFromEvent($record['event_status'] ?? 'pending');
```

### Pattern 3: Dual Data Source Join (Events + Cron)
**What:** Primary data from event_dates, supplementary data from class_material_tracking
**When to use:** Showing both manual events and automated cron notifications
**Example:**
```sql
-- Primary source: event_dates JSONB
SELECT
    c.class_id,
    elem->>'status' as primary_status,  -- Used for dashboard status
    -- Supplementary cron data (may be NULL):
    cmt.notification_type,              -- 'orange' or 'red' or NULL
    cmt.notification_sent_at            -- Timestamp or NULL
FROM classes c
CROSS JOIN LATERAL jsonb_array_elements(c.event_dates) WITH ORDINALITY AS events(elem, elem_index)
LEFT JOIN class_material_tracking cmt ON cmt.class_id = c.class_id  -- May not exist
WHERE elem->>'type' = 'Deliveries'
```

**Key insight:** LEFT JOIN preserves all Deliveries events even when no cron record exists. This shows manually-entered events immediately, before cron job runs.

### Pattern 4: Statistics Aggregation from JSONB
**What:** Count events by status from JSONB array
**When to use:** Dashboard statistics bar (Total, Pending, Completed)
**Example:**
```sql
-- Source: MaterialTrackingRepository.php line 302-331 pattern
-- Adapted for event_dates source

SELECT
    COUNT(*) as total,
    COALESCE(SUM(CASE WHEN elem->>'status' = 'Pending' THEN 1 ELSE 0 END), 0) as pending,
    COALESCE(SUM(CASE WHEN elem->>'status' = 'Completed' THEN 1 ELSE 0 END), 0) as completed
FROM classes c
CROSS JOIN LATERAL jsonb_array_elements(c.event_dates) AS events(elem)
WHERE elem->>'type' = 'Deliveries';
```

### Anti-Patterns to Avoid
- **Fetching all classes and filtering in PHP:** Query performance degrades. Use `WHERE elem->>'type' = 'Deliveries'` in SQL.
- **Overwriting cron records:** Cron system should remain independent. Only JOIN for display.
- **Breaking existing cron notifications:** MaterialNotificationService.php must continue working unchanged.
- **Assuming event_dates always exists:** Use `COALESCE(c.event_dates, '[]'::jsonb)` for classes without events.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| JSONB array unnesting | Custom JSON_DECODE + loop | `jsonb_array_elements()` | DB-side filtering is faster, supports indexes |
| Status mapping | Multiple IF statements | `match()` expression (PHP 8.0+) | Already used in codebase, cleaner |
| Date filtering | String comparison | PostgreSQL DATE operators | Handles timezones, intervals correctly |
| Cron data merging | Separate queries + PHP merge | LEFT JOIN in SQL | Single query is faster, simpler |

**Key insight:** PostgreSQL JSONB queries are battle-tested and performant. Codebase already uses JSONB for `event_dates` in Event Tasks system (ClassTaskRepository.php). Reuse same pattern.

## Common Pitfalls

### Pitfall 1: JSONB Null vs Empty Array
**What goes wrong:** `event_dates` is NULL for old classes created before v1.2, causing CROSS JOIN to return 0 rows
**Why it happens:** NULL JSONB cannot be unnested with `jsonb_array_elements()`
**How to avoid:** Use `COALESCE(c.event_dates, '[]'::jsonb)` in CROSS JOIN
**Warning signs:** Dashboard shows no records even though classes with Deliveries exist

**Example:**
```sql
-- WRONG: Returns 0 rows if event_dates is NULL
CROSS JOIN LATERAL jsonb_array_elements(c.event_dates) AS events(elem)

-- CORRECT: Treats NULL as empty array, returns 0 rows but doesn't break query
CROSS JOIN LATERAL jsonb_array_elements(COALESCE(c.event_dates, '[]'::jsonb)) AS events(elem)
```

### Pitfall 2: Case-Sensitive Event Type Matching
**What goes wrong:** Events stored as "Deliveries" but query checks "deliveries", resulting in 0 matches
**Why it happens:** JSONB ->> operator is case-sensitive
**How to avoid:** Use exact case "Deliveries" (capital D) or add `LOWER(elem->>'type') = 'deliveries'`
**Warning signs:** Event Tasks dashboard shows Deliveries events, but Material Tracking dashboard shows 0 records

**Verified case from codebase:**
```php
// Source: ScheduleService.php line 207
if (($event['type'] ?? '') === 'Deliveries' && !empty($event['date'])) {
    // Type is "Deliveries" with capital D
}
```

### Pitfall 3: Breaking Cron Notification System
**What goes wrong:** Refactored queries accidentally depend on event_dates, breaking cron job that creates class_material_tracking records
**Why it happens:** MaterialNotificationService must remain independent of event_dates
**How to avoid:** Ensure `MaterialNotificationService::findClassesByDaysUntilStart()` still queries classes table without JOIN to event_dates
**Warning signs:** Cron job starts failing, no new orange/red notifications sent

**Keep this query unchanged:**
```sql
-- Source: MaterialNotificationService.php lines 65-93
-- This query MUST NOT be modified - it's used by cron job
SELECT c.class_id, c.class_code, ...
FROM classes c
WHERE c.original_start_date = CURRENT_DATE + INTERVAL '7 days'  -- Cron logic
  AND NOT EXISTS (SELECT 1 FROM class_material_tracking ...)
```

### Pitfall 4: WITH ORDINALITY 1-Indexing vs PHP 0-Indexing
**What goes wrong:** Event index mismatch between database and PHP array indexing
**Why it happens:** PostgreSQL `WITH ORDINALITY` starts at 1, PHP arrays start at 0
**How to avoid:** Subtract 1 from elem_index in SELECT: `(elem_index - 1) as event_index`
**Warning signs:** Clicking "Mark as Delivered" updates wrong event (off-by-one error)

**Example:**
```sql
-- WRONG: elem_index is 1, 2, 3... but PHP expects 0, 1, 2...
CROSS JOIN LATERAL jsonb_array_elements(c.event_dates) WITH ORDINALITY AS events(elem, elem_index)

-- CORRECT: Adjust to 0-based indexing for PHP
SELECT (elem_index - 1) as event_index
FROM classes c
CROSS JOIN LATERAL jsonb_array_elements(c.event_dates) WITH ORDINALITY AS events(elem, elem_index)
```

### Pitfall 5: Multiple Deliveries Events Per Class
**What goes wrong:** Class has 3 Deliveries events, dashboard shows 3 rows for same class
**Why it happens:** CROSS JOIN unnests ALL Deliveries events to separate rows
**How to avoid:** This is CORRECT behavior. Each delivery event should be a separate task row.
**Warning signs:** None - this is intended behavior. User may add "Deliveries: Initial Materials", "Deliveries: Exam Papers", etc.

**Design decision:** Dashboard shows one row per Deliveries event, not one row per class. This matches Event Tasks Dashboard behavior.

## Code Examples

Verified patterns from official sources:

### JSONB Query with Type Filter
```sql
-- Source: Adapted from ClassTaskRepository.php fetchClasses() query
-- Location: src/Events/Repositories/ClassTaskRepository.php line 59-93

SELECT
    c.class_id,
    c.class_code,
    c.class_subject,
    c.original_start_date,
    cl.client_name,
    s.site_name,
    elem->>'type' as event_type,
    elem->>'description' as event_description,
    elem->>'date' as event_date,
    elem->>'status' as event_status,
    elem->>'notes' as event_notes,
    elem->>'completed_by' as event_completed_by,
    elem->>'completed_at' as event_completed_at,
    (elem_index - 1) as event_index
FROM classes c
LEFT JOIN clients cl ON c.client_id = cl.client_id
LEFT JOIN sites s ON c.site_id = s.site_id
CROSS JOIN LATERAL jsonb_array_elements(COALESCE(c.event_dates, '[]'::jsonb))
    WITH ORDINALITY AS events(elem, elem_index)
WHERE elem->>'type' = 'Deliveries'
ORDER BY c.original_start_date DESC NULLS LAST;
```

### Status Badge Rendering with Match Expression
```php
// Source: MaterialTrackingPresenter.php lines 115-123
// Keep this pattern, adapt data source

private function getStatusBadge(string $status): string
{
    return match ($status) {
        'pending' => '<span class="badge badge-phoenix badge-phoenix-secondary fs-10">⏳ Pending</span>',
        'notified' => '<span class="badge badge-phoenix badge-phoenix-info fs-10">📧 Notified</span>',
        'delivered' => '<span class="badge badge-phoenix badge-phoenix-success fs-10">✅ Delivered</span>',
        default => '<span class="badge badge-phoenix badge-phoenix-secondary fs-10">' . esc_html($status) . '</span>',
    };
}
```

### Cron Notification Badge (Supplementary Info)
```php
// Source: MaterialTrackingPresenter.php lines 96-107
// Keep this for supplementary cron notification display

private function getNotificationBadge(?string $type): string
{
    if ($type === 'orange') {
        return '<span class="badge badge-phoenix badge-phoenix-warning fs-10">🟠 7d</span>';
    }

    if ($type === 'red') {
        return '<span class="badge badge-phoenix badge-phoenix-danger fs-10">🔴 5d</span>';
    }

    return ''; // No cron notification sent yet
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Cron-only tracking | Event-driven + cron supplementary | Phase 19 (v1.3) | Dashboard shows all manual events immediately, not just cron-triggered |
| class_material_tracking as source | event_dates JSONB as primary source | Phase 19 (v1.3) | More accurate tracking, no 7/5-day delay |
| Separate systems (Events vs Tracking) | Unified view with dual source | Phase 19 (v1.3) | Single dashboard shows both manual events and cron status |

**Deprecated/outdated:**
- Querying only `class_material_tracking` table: Now supplementary, not primary
- Assuming all tracking records come from cron: Now user-entered events are primary
- Dashboard showing "0 records" when events exist: Fixed by querying event_dates

## Event Structure Reference

**Source:** Event Tasks system design doc (docs/plans/2026-02-03-event-tasks-refactor-design.md)

```json
{
  "type": "Deliveries",
  "description": "Initial materials",
  "date": "2025-03-15",
  "status": "Pending",
  "notes": "Hand deliver to site manager",
  "completed_by": 123,
  "completed_at": "2025-03-15T14:30:00Z"
}
```

**Field mapping to dashboard columns:**
- `type` → Filter (WHERE type = 'Deliveries')
- `description` → Display in event label (optional)
- `date` → Delivery Event Date column (NEW column)
- `status` → Maps to dashboard status (Pending → pending, Completed → delivered)
- `notes` → Not shown in list view (available in detail view)
- `completed_by` → Not shown (internal tracking)
- `completed_at` → Not shown (internal tracking)

## Dashboard Columns (New Structure)

| Column | Data Source | Notes |
|--------|-------------|-------|
| Class Code/Subject | `classes` table | Unchanged |
| Client/Site | `clients`, `sites` tables | Unchanged |
| Class Start Date | `classes.original_start_date` | Unchanged |
| **Delivery Event Date** | **`event_dates[].date` (NEW)** | Shows when delivery is scheduled |
| Notification Type | `class_material_tracking.notification_type` | Supplementary (orange/red badge or blank) |
| Status | `event_dates[].status` | Primary status (Pending/Completed) |
| Actions | Mark as delivered checkbox | Updates `event_dates[N].status` |

## Data Flow

```
User adds Deliveries event in class form
    ↓
Saved to classes.event_dates JSONB
    ↓
Material Tracking Dashboard queries event_dates
    ↓
Displays immediately (no cron delay)
    ↓
[Parallel] Daily cron runs at 7d/5d before start
    ↓
Creates class_material_tracking record
    ↓
Dashboard shows supplementary orange/red badge
```

## Open Questions

Things that couldn't be fully resolved:

1. **Filter behavior with multiple Deliveries per class**
   - What we know: CROSS JOIN unnests to multiple rows per class
   - What's unclear: Should status filter affect visibility of individual events or entire class?
   - Recommendation: Filter individual event rows (current behavior). User can search by class code to see all events for that class.

2. **Statistics calculation method**
   - What we know: Current statistics count `class_material_tracking` records
   - What's unclear: Should statistics count unique classes or unique delivery events?
   - Recommendation: Count unique delivery events (one class with 3 Deliveries = 3 total). Matches dashboard row count.

3. **Handling classes with both Deliveries and Collections events**
   - What we know: event_dates can contain multiple event types
   - What's unclear: Should Collections events appear on Material Tracking dashboard?
   - Recommendation: No. Only Deliveries events. Collections have separate meaning (picking up materials).

4. **Mark as Delivered action with multiple Deliveries**
   - What we know: Checkbox marks delivered via AJAX
   - What's unclear: Should it update only the specific event or all Deliveries for that class?
   - Recommendation: Update only the specific event_index. User marks each delivery separately.

## Sources

### Primary (HIGH confidence)
- WeCoza Core codebase (wecoza-core plugin)
  - `src/Events/Repositories/MaterialTrackingRepository.php` - Current implementation
  - `src/Events/Repositories/ClassTaskRepository.php` - JSONB query pattern reference
  - `src/Events/Services/MaterialNotificationService.php` - Cron job logic
  - `src/Classes/Services/ScheduleService.php` - Event type case verification ("Deliveries")
  - `docs/plans/2026-02-03-event-tasks-refactor-design.md` - Event structure spec
  - `.planning/ROADMAP.md` - Phase 19 requirements
  - `.planning/REQUIREMENTS.md` - DASH-01..04, FILT-01..03, CRON-01..02

### Secondary (MEDIUM confidence)
- PostgreSQL 12 Documentation (official docs) - JSONB functions
  - https://www.postgresql.org/docs/12/functions-json.html
  - jsonb_array_elements(), WITH ORDINALITY, JSONB operators

### Tertiary (LOW confidence)
- None used - all research from primary sources

## Metadata

**Confidence breakdown:**
- JSONB query patterns: HIGH - Verified from ClassTaskRepository existing code
- Event structure: HIGH - Verified from design doc and ScheduleService.php
- Cron system: HIGH - Verified from MaterialNotificationService.php
- Data shape changes: HIGH - Verified from current Presenter/Repository code

**Research date:** 2026-02-06
**Valid until:** 2026-03-06 (30 days - stable domain, schema changes unlikely)

---

## RESEARCH COMPLETE

Phase 19 research is complete. Ready for planning.

**Key Findings:**
1. JSONB query pattern already exists in ClassTaskRepository - reuse for MaterialTrackingRepository
2. Event structure verified: `type: "Deliveries"` (capital D), status: "Pending"/"Completed"
3. Dual data source architecture: event_dates primary, class_material_tracking supplementary
4. Cron system must remain unchanged - LEFT JOIN for display only
5. Dashboard shows one row per Deliveries event (not per class) - matches Event Tasks pattern
