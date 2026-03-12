---
id: S03
milestone: M002
provides:
  - Collapsible "Relationship History" accordion on single class display page
  - Collapsible "Relationship History" accordion on single agent display page
  - Shared entity-history.js module rendering AJAX-loaded timeline tables
  - Reusable entity-history-section.view.php component
key_files:
  - assets/js/classes/entity-history.js
  - views/components/entity-history-section.view.php
  - views/classes/components/single-class-display.view.php
  - views/agents/display/agent-single-display.view.php
  - src/Classes/Controllers/ClassController.php
  - src/Agents/Controllers/AgentsController.php
key_decisions:
  - History loaded via AJAX on accordion expand (lazy load — no extra queries on page load)
  - Single shared JS module handles all 4 entity types
  - Clean Bootstrap tables per D020 — no interactive timelines
  - Placed before QA Reports (class) and Documents (agent) sections
drill_down_paths:
  - .gsd/milestones/M002/slices/S03/S03-PLAN.md
completed_at: 2026-03-12
---

# S03: Class & Agent History UI

**Added collapsible relationship history sections to single class and single agent display pages — lazy-loaded via AJAX, clean Bootstrap tables.**

## What Was Built

1. **`entity-history.js`** — Shared JS module that:
   - Calls `wecoza_get_entity_history` AJAX endpoint on accordion expand
   - Renders entity-specific timeline tables (class: 7 sections, agent: 7 sections, learner: 5, client: 4)
   - Status badges, date formatting, text truncation
   - Loading spinner and error states

2. **`entity-history-section.view.php`** — Reusable PHP component with collapsible Bootstrap accordion card

3. **Single class display** — History section added before QA Reports. Script registered in ClassController, localized with `wecoza_history_nonce` + class_id.

4. **Single agent display** — History section added before Documents. Script enqueued and localized in AgentsController.renderSingleAgent().

## Verification

- All PHP files pass syntax check
- JS file passes `node --check` validation
- 101 + 43 = 144 automated checks still passing
