# S02: Entity History UI Tabs

**Goal:** Add a History tab to all 4 entity detail pages (learner, agent, client, class) that displays relationship timeline data from HistoryService.
**Demo:** Navigate to any entity's detail page, click the History tab, and see the full relationship timeline with all sections Mario requested. Browser-verified.

## Must-Haves

- History tab on learner detail page with: class history, client history, levels completed, portfolios, progression dates
- History tab on agent detail page with: class history, client history, subjects facilitated, performance notes, QA reports
- History tab on client detail page with: learner history, class history, agent history
- History tab on class detail page with: learner history, agent history, progression history, notes, events
- AJAX-loaded tab content (lazy load on tab click, not on page load)
- Consistent timeline UI component reused across all 4 entity types

## Proof Level

- This slice proves: integration
- Real runtime required: yes (WordPress + PostgreSQL)
- Human/UAT required: yes (visual review of tab layout)

## Verification

- Browser-verify all 4 entity detail pages show History tab
- Browser-verify AJAX loading works (click tab → data appears)
- Browser-verify each entity shows all required sections per WEC-189
- `browser_assert` checks for tab visibility, data content, and no console errors

## Observability / Diagnostics

- Runtime signals: AJAX endpoint returns structured JSON with success/error status
- Inspection surfaces: Browser console shows AJAX request/response; `wecoza_log()` on server errors
- Failure visibility: AJAX error responses include error message; empty-state message shown when no history data
- Redaction constraints: No PII in console logs; learner names shown only to users with `manage_learners` capability

## Integration Closure

- Upstream surfaces consumed: `HistoryService` (S01) for all data; existing shortcode views for tab insertion points
- New wiring introduced: AJAX handler for history data; tab component in entity detail views; JS for tab switching and AJAX loading
- What remains: S03 (audit trail admin view + retention cron)

## Tasks

- [ ] **T01: History AJAX handler** `est:1h`
  - Why: History tabs load data via AJAX to avoid slowing page load. Need a single AJAX endpoint that serves history data for any entity type.
  - Files: `src/History/Ajax/HistoryAjaxHandler.php`, `wecoza-core.php`
  - Do: Create `HistoryAjaxHandler` with `handleGetHistory()` method. Accepts `entity_type` (learner/agent/client/class) and `entity_id`. Uses `AjaxSecurity` for nonce verification. Calls appropriate `HistoryService` method. Returns JSON with `success: true, data: {sections}`. Register AJAX actions `wp_ajax_wecoza_get_entity_history`. Follow existing AJAX handler patterns (e.g., `ClassStatusAjaxHandler`).
  - Verify: `curl` or browser DevTools confirm AJAX returns correct JSON
  - Done when: AJAX endpoint returns history data for all 4 entity types

- [ ] **T02: Reusable history timeline view components** `est:1.5h`
  - Why: All 4 entity pages share the same timeline UI pattern. Build once, reuse everywhere.
  - Files: `views/components/history-tab.php`, `views/components/history-timeline.php`, `views/components/history-section.php`
  - Do: Create a tab container component that wraps a History tab alongside existing tabs. Create a timeline component that renders a list of history items with date, description, and optional metadata. Create a section component (e.g., "Class History", "Portfolio History") that groups timeline items. Use `wecoza_component()` for rendering. Follow existing component patterns in `views/components/`. Support empty-state messaging per section.
  - Verify: Components render correctly when called with test data
  - Done when: Reusable components exist for tab container, timeline, and section

- [ ] **T03: Learner & Agent history tab integration** `est:2h`
  - Why: Wire history tab into learner and agent single-entity display pages. These are the two entities Mario prioritized most.
  - Files: `views/learners/single-learner-display.view.php` (or equivalent), `views/agents/single-agent.view.php` (or equivalent), `assets/js/history/entity-history.js`, CSS in `ydcoza-styles.css`
  - Do: Add History tab to learner detail page showing: class history, client history, levels completed, portfolios, progression dates. Add History tab to agent detail page showing: class history, client history, subjects facilitated, performance notes, QA reports. JS handles tab click → AJAX fetch → render sections using the reusable components. Enqueue JS/CSS only on pages with history tabs. Style timeline consistently with existing UI.
  - Verify: Browser-navigate to learner detail page, click History tab, verify all 5 sections render. Same for agent with all 6 sections.
  - Done when: Learner and agent detail pages show fully populated History tabs

- [ ] **T04: Client & Class history tab integration** `est:1.5h`
  - Why: Complete the remaining 2 entity types for full coverage.
  - Files: `views/clients/single-client.view.php` (or equivalent), `views/classes/components/single-class-display.view.php`, `assets/js/history/entity-history.js`
  - Do: Add History tab to client detail page showing: learner history, class history, agent history. Add History tab to class detail page showing: learner history, agent history, progression history, notes, events history. Reuse same JS and components from T03. Class events history should use existing `class_events` data.
  - Verify: Browser-navigate to client and class detail pages, click History tabs, verify all sections render.
  - Done when: All 4 entity types have fully populated History tabs

## Files Likely Touched

- `src/History/Ajax/HistoryAjaxHandler.php` (new)
- `views/components/history-tab.php` (new)
- `views/components/history-timeline.php` (new)
- `views/components/history-section.php` (new)
- `views/learners/single-learner-display.view.php` (modified)
- `views/agents/single-agent.view.php` (modified — find actual filename)
- `views/clients/single-client.view.php` (modified — find actual filename)
- `views/classes/components/single-class-display.view.php` (modified)
- `assets/js/history/entity-history.js` (new)
- `ydcoza-styles.css` in child theme (appended)
- `wecoza-core.php` (AJAX handler registration)
