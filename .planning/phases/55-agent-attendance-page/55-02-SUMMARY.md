---
phase: 55-agent-attendance-page
plan: 02
subsystem: auth
tags: [wordpress, redirect, agents, login, security]

requires:
  - phase: 55-agent-attendance-page
    plan: 01
    provides: AgentAccessController, agent-attendance page slug

provides:
  - login_redirect filter (priority 9) sending wp_agent to /app/agent-attendance/
  - admin_init redirect blocking wp_agent from /wp-admin/ (AJAX-exempt)
  - template_redirect page cage with app CPT slug allowlist

affects: []

tech-stack:
  added: []
  patterns:
    - "Priority 9 login_redirect filter — fires before theme priority 10 catch-all"
    - "wp_doing_ajax() guard on admin_init — prevents blocking attendance AJAX calls"
    - "is_singular('app') + slug allowlist for page cage — leverages app CPT instead of page type"

key-files:
  created: []
  modified:
    - src/Agents/Controllers/AgentAccessController.php
    - wecoza-core.php

key-decisions:
  - "Used /app/agent-attendance/ (app CPT) instead of /agent-attendance/ (page) — 55-01 originally created a page, switched to app CPT for consistency with other WeCoza pages"
  - "Added is_login() guard in enforceAgentPageCage to prevent blocking WP login/logout URLs"
  - "Slug allowlist ['agent-attendance', 'display-single-class'] — minimal set for attendance workflow"

patterns-established:
  - "Pattern: wp_agent role cage via three-hook approach (login_redirect + admin_init + template_redirect)"

requirements-completed: [AGT-07, AGT-08]

duration: retroactive
completed: 2026-03-04
---

# Phase 55 Plan 02: Agent Redirect Cage Summary

**Three WordPress redirect hooks cage wp_agent users to attendance page and single-class view only**

## Performance

- **Duration:** Retroactive summary (code already committed)
- **Completed:** 2026-03-04
- **Tasks:** 1 (code) + 1 (human verification checkpoint)
- **Files modified:** 2

## Accomplishments

- Added `redirectAgentOnLogin()` filter at priority 9 — sends wp_agent to /app/agent-attendance/ on login
- Added `blockAgentAdminAccess()` on admin_init — redirects wp_agent away from /wp-admin/ with wp_doing_ajax() guard
- Added `enforceAgentPageCage()` on template_redirect — allowlist of app CPT slugs (agent-attendance, display-single-class)
- Wired AgentAccessController into wecoza-core.php bootstrap

## Task Commits

1. **feat(55-02): add agent redirect cage** - `2aa907f`
2. **fix(55-02): move agent attendance page under /app/** - `79f6fde`
3. **fix(55-02): use app CPT instead of page** - `99b8945`

## Files Modified

- `src/Agents/Controllers/AgentAccessController.php` — Three redirect hook methods added to constructor and class
- `wecoza-core.php` — AgentAccessController bootstrap instantiation

## Decisions Made

- Switched from WP page to app CPT for agent-attendance — consistent with other WeCoza app pages
- Priority 9 on login_redirect ensures agent redirect fires before theme's priority-10 catch-all redirect
- wp_doing_ajax() guard is first check in blockAgentAdminAccess — prevents breaking attendance AJAX calls
- is_login() guard in page cage prevents blocking WordPress login/logout flow

## Deviations from Plan

- Plan specified `/agent-attendance/` as a WP page; implementation evolved to use `/app/agent-attendance/` as an app CPT post (commits 79f6fde, 99b8945)

## Issues Encountered

None.

---
*Phase: 55-agent-attendance-page*
*Completed: 2026-03-04*
