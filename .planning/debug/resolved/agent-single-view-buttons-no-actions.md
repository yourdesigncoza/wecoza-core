---
status: resolved
trigger: "agent-single-view-buttons-no-actions"
created: 2026-02-13T10:30:00Z
updated: 2026-02-13T11:10:00Z
---

## Current Focus

hypothesis: Fix applied - buttons now have proper hrefs and data attributes
test: Manual verification required - visit single agent page and test buttons
expecting: Back button navigates to list, Edit opens edit form, Delete prompts and works
next_action: User verification needed

## Symptoms

expected: Clicking "Back To Agents" navigates to agents list, "Edit" opens edit form, "Delete" deletes the agent
actual: Buttons render visually (see screenshot - colored buttons with text) but have no click actions
errors: None visible - buttons just don't respond to clicks
reproduction: Visit any single agent view page using [wecoza_single_agent] shortcode
started: Unknown - may have been broken during migration to wecoza-core plugin

## Eliminated

## Evidence

- timestamp: 2026-02-13T10:35:00Z
  checked: views/agents/display/agent-single-display.view.php lines 59-66
  found: Buttons are plain <button> elements with no href, onclick, or data attributes
  implication: Buttons render but have no mechanism to perform any action

- timestamp: 2026-02-13T10:40:00Z
  checked: views/agents/display/agent-display-table-rows.view.php lines 64-80
  found: Action buttons use <a> tags with proper hrefs for View/Edit, and <button> with data-agent-id for Delete
  implication: Single agent view buttons should follow same pattern

- timestamp: 2026-02-13T10:42:00Z
  checked: src/Agents/Controllers/AgentsController.php lines 800-826
  found: Helper methods exist - getEditUrl(), getViewUrl(), getBackUrl() - but Back URL is passed to view, Edit/Delete URLs not generated
  implication: Need to add logic to generate Edit URL and pass it to view; Delete needs data-agent-id attribute

- timestamp: 2026-02-13T10:45:00Z
  checked: assets/js/agents/agent-delete.js lines 17-27
  found: JavaScript listens for button[data-agent-id] clicks with .bi-trash icon
  implication: Delete button needs data-agent-id attribute and .bi-trash icon class to work

- timestamp: 2026-02-13T11:00:00Z
  checked: assets/js/agents/agent-delete.js lines 48-59
  found: Delete handler expects table row context (button.closest('tr')) and fades out row
  implication: On single agent view (no table), delete will work but UI handling will fail - needs redirect to agents list after delete

## Resolution

root_cause: Single agent view template has placeholder buttons without proper links or attributes. "Back To Agents" and "Edit" buttons are plain <button> elements instead of <a> tags with hrefs. "Delete" button lacks data-agent-id attribute needed by JavaScript handler.

fix: |
  1. Updated AgentsController::renderSingleAgent() to pass edit_url to view template
  2. Replaced plain <button> elements with proper <a> tags for Back and Edit buttons
  3. Added data-agent-id attribute to Delete button so JavaScript handler can work
  4. Added proper icons and i18n text to all buttons
  5. Wrapped Edit and Delete buttons in can_manage permission check
  6. Enhanced agent-delete.js to handle both table context and single view context
     - Table context: fades out row as before
     - Single view: shows success message and redirects to agents list

verification: Manual testing required - buttons should now navigate and function correctly
files_changed:
  - src/Agents/Controllers/AgentsController.php
  - views/agents/display/agent-single-display.view.php
  - assets/js/agents/agent-delete.js
