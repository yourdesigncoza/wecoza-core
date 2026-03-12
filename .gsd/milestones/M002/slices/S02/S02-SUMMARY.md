---
id: S02
milestone: M002
provides:
  - HistoryService facade with 4 timeline methods (getClassTimeline, getAgentTimeline, getLearnerTimeline, getClientTimeline)
  - AJAX endpoint wecoza_get_entity_history serving all 4 entity types
  - AuditService wired into ClassStatusAjaxHandler
  - WP-Cron weekly audit purge (36-month retention)
  - [wecoza_audit_log] shortcode with filtering and pagination
  - 144 automated checks across 2 test suites
key_files:
  - src/Classes/Services/HistoryService.php
  - src/Classes/Ajax/HistoryAjaxHandlers.php
  - src/Classes/Shortcodes/AuditLogShortcode.php
  - views/components/audit-log-table.view.php
  - wecoza-core.php
key_decisions:
  - HistoryService composes 13 HistoryRepository methods into 4 entity-specific timelines
  - Client timeline derives agents and learners from class relationships
  - AJAX nonce: wecoza_history_nonce
  - Audit wiring is fire-and-forget after successful DB commit
  - Cron uses WP standard scheduled events, cleaned up on deactivation
drill_down_paths:
  - .gsd/milestones/M002/slices/S02/tasks/T01-SUMMARY.md
  - .gsd/milestones/M002/slices/S02/tasks/T02-SUMMARY.md
completed_at: 2026-03-12
---

# S02: History Service Facade, AJAX & Audit Wiring

**Built HistoryService facade, AJAX endpoint, audit log wiring, cron purge, and shortcode — 144 automated checks passing.**

## What S03/S04 Consume From This Slice

- `HistoryService::getClassTimeline(classId)` → 7 keys (agents, learners, status, dates, QA, events, notes)
- `HistoryService::getAgentTimeline(agentId)` → 7 keys (classes, notes, absences, QA, subjects, clients)
- `HistoryService::getLearnerTimeline(learnerId)` → 5 keys (enrollments, hours, portfolios, progression dates, clients)
- `HistoryService::getClientTimeline(clientId)` → 4 keys (classes, locations, agents, learners)
- AJAX endpoint `wp_ajax_wecoza_get_entity_history` with nonce `wecoza_history_nonce`
- `[wecoza_audit_log]` shortcode for admin pages
