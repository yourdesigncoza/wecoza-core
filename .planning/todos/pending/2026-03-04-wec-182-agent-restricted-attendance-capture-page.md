---
created: 2026-03-04T13:50:07.564Z
title: "WEC-182 Agent-restricted attendance capture page"
area: attendance
linear: https://linear.app/wecoza/issue/WEC-182
files:
  - src/Classes/Services/AttendanceService.php
  - src/Classes/Ajax/AttendanceAjaxHandlers.php
  - assets/js/classes/attendance-capture.js
  - views/classes/components/single-class/attendance.php
  - views/templates/agent-minimal.php (NEW)
  - schema/wecoza_db_schema_bu_march_04.sql
---

## Problem

Mario confirmed agents (facilitators) are the ones who capture attendance. They need a dedicated page where they can ONLY capture attendance — no access to other WeCoza pages. Currently agents are not registered as WordPress users at all.

Quote from Mario: "They are the ones that will capture, so not sure how you plan for them to capture live on this page or if they have a separate page to capture. Remember, they must only be able to capture; they should not be able to change anything else or go to any other page in Wecoza."

## Solution

### 1. WordPress Role: `wecoza_agent`
- Register custom WP role with minimal capabilities: `read` + custom `capture_attendance`
- Existing admin/manager roles also get `capture_attendance` cap
- Extensible — more caps can be added later (e.g., `view_own_schedule`)

### 2. Schema Change: Link agents to WP users
- Add `wp_user_id` column to `agents` table (PostgreSQL)
- Links PG agent record to WP user account
- Lookup: WP user → `wp_user_id` → `agent_id` → `classes.class_agent`

### 3. Minimal Page Template (plugin-registered)
- Register a custom page template from the plugin via `theme_page_templates` filter
- Lives in `views/templates/agent-minimal.php` — not theme-dependent
- Bare-bones HTML shell: WeCoza logo, page title, shortcode content, logout button
- NO nav menu, NO sidebar, NO footer links — agents see nothing else
- Only loads the CSS/JS assets needed for attendance capture
- Admin assigns this template to a WP page (e.g., `/agent-attendance/`)
- If more agent features are added later, same template is reused — just add content to the page

### 4. Shortcode: `[wecoza_agent_attendance_management]`
- Capability-gated: requires `capture_attendance`
- Queries classes where `class_agent = agent_id` (via wp_user_id lookup)
- Also includes classes where agent is in `backup_agent_ids` jsonb array
- Reuses existing attendance capture JS/AJAX — no duplication
- Exception reporting included (made more visible per Mario's feedback)

### 5. Access Control
- Agents redirected to their attendance page on login (skip WP admin)
- Hide WP admin bar for `wecoza_agent` role
- If agent tries other WeCoza pages → redirect back to attendance page

### Database Schema (classes table — already exists)
```
classes.class_agent        INTEGER  — assigned agent for the class
classes.backup_agent_ids   JSONB    — backup agent IDs array
classes.initial_class_agent INTEGER — original agent assignment
```

### Agents table (needs change)
```sql
ALTER TABLE agents ADD COLUMN wp_user_id INTEGER;
CREATE UNIQUE INDEX idx_agents_wp_user_id ON agents(wp_user_id) WHERE wp_user_id IS NOT NULL;
```

### What we REUSE (no duplication)
- Attendance capture modal JS + AJAX handlers
- AttendanceService (generateSessionList, captureAttendance, markException)
- Exception modal (with improved visibility)

### What we BUILD NEW
- `wecoza_agent` role registration (plugin activation hook)
- `wp_user_id` column on agents table
- Minimal page template: `views/templates/agent-minimal.php` (registered via plugin filter)
- `[wecoza_agent_attendance_management]` shortcode + view
- Agent-class lookup query (agent's classes via wp_user_id → agent_id)
- Login redirect for agent role
- Admin bar hide for agent role
