---
phase: 54-agent-foundation
plan: 03
subsystem: auth
tags: [wordpress, wp-user, agents, roles, wp-cli, email-sync]

# Dependency graph
requires:
  - phase: 54-02
    provides: wp_user_id column in agents table + AgentRepository::findByWpUserId()
  - phase: 54-01
    provides: wp_agent role registration with capture_attendance capability
provides:
  - AgentWpUserService: WP user lifecycle management for agents
  - Auto-create WP user on agent form save
  - One-way email sync from agent form to WP user
  - wp_agent role removal on soft-delete
  - WP profile email field locked for wp_agent users
  - WP-CLI bulk migration command sync-agent-users
affects:
  - phase-55 (agent login page requires agents to have WP accounts)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Dedicated service per concern: AgentWpUserService keeps WP user logic out of AgentService"
    - "Link-by-email deduplication: existing WP user found by email is linked, not duplicated"
    - "One-way email sync: agent form is source of truth, WP profile email is read-only for agents"

key-files:
  created:
    - src/Agents/Services/AgentWpUserService.php
  modified:
    - src/Agents/Services/AgentService.php
    - wecoza-core.php

key-decisions:
  - "Agent email is source of truth — WP profile email field hidden and POST changes reverted for wp_agent users"
  - "Bulk migration sends no email notifications (demo data assumption)"
  - "Stale wp_user_id (WP user deleted) logs warning; recreation deferred to next save"

patterns-established:
  - "Service composition: AgentService instantiates AgentWpUserService with shared $this->repository"

requirements-completed: [AGT-03]

# Metrics
duration: 45min
completed: 2026-03-04
---

# Phase 54 Plan 03: WP User Auto-Creation for Agents Summary

**AgentWpUserService auto-creates WP accounts on agent save, syncs email one-way, locks WP profile email for agents, and provides wp wecoza sync-agent-users bulk migration**

## Performance

- **Duration:** ~45 min
- **Started:** 2026-03-04T18:30:00Z
- **Completed:** 2026-03-04T19:15:00Z
- **Tasks:** 4 (+ Task 5 manual verification, deferred to human)
- **Files modified:** 3

## Accomplishments

- Created `AgentWpUserService` — dedicated service for all WP user lifecycle logic
- Integrated WP user sync into `AgentService::handleAgentFormSubmission()` and `deleteAgent()`
- Locked WP profile email field for `wp_agent` users (CSS hide + POST revert filter)
- Added `wp wecoza sync-agent-users` WP-CLI command for bulk migration of existing agents

## Task Commits

Each task was committed atomically:

1. **Task 1: Create AgentWpUserService** - `6f985f1` (feat)
2. **Task 2: Integrate into AgentService** - `d4c62ca` (feat)
3. **Task 3: WP profile email lockdown** - `576d182` (feat)
4. **Task 4: WP-CLI sync-agent-users command** - `3277e45` (feat)

## Files Created/Modified

- `src/Agents/Services/AgentWpUserService.php` — Full WP user lifecycle: create, link, email sync, role removal
- `src/Agents/Services/AgentService.php` — Added syncWpUser() call after file uploads; removeAgentRole() before soft-delete
- `wecoza-core.php` — Profile email lockdown hooks + WP-CLI sync-agent-users command

## Decisions Made

- Agent email is the source of truth. WP profile email is hidden for `wp_agent` users and reverted if changed via POST.
- Bulk migration (`sync-agent-users`) suppresses new-user email notifications since existing data is assumed to be demo.
- Stale `wp_user_id` (where the WP user was manually deleted) logs a warning; recreation is deferred to the agent's next save — avoids complexity of cleanup logic.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required. Manual verification (Task 5) can be done by creating a test agent via `[wecoza_capture_agents]` shortcode.

## Next Phase Readiness

- Phase 54 fully complete — all agents will have WP accounts on next save
- `wp wecoza sync-agent-users` can be run now to backfill existing active agents
- Phase 55 (Agent Login Page) can proceed — agents have WP credentials to authenticate with

---
*Phase: 54-agent-foundation*
*Completed: 2026-03-04*
