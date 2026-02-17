---
phase: quick-12
plan: "01"
subsystem: agents
tags: [ajax, ux, form, feedback]
dependency_graph:
  requires: []
  provides: [wecoza_agents_save AJAX endpoint, agent form AJAX submission]
  affects: [agents-app.js, agent-capture-form.view.php, AgentsAjaxHandlers, AgentsController]
tech_stack:
  added: []
  patterns: [AJAX form submission with FormData, success banner with auto-dismiss, form clear on new save]
key_files:
  created: []
  modified:
    - assets/js/agents/agents-app.js
    - views/agents/components/agent-capture-form.view.php
    - src/Agents/Ajax/AgentsAjaxHandlers.php
    - src/Agents/Controllers/AgentsController.php
decisions:
  - AJAX endpoint uses same agents_nonce_action and edit_others_posts capability as existing handlers
  - Edit mode reloads page after 1.5s (same pattern as client capture); new agent clears form
  - messages.form keys added to wecozaAgents localized config for DRY message management
metrics:
  duration: "2m 10s"
  completed: "2026-02-17"
  tasks_completed: 2
  files_modified: 4
---

# Quick Task 12: After Saving a New Agent - Success Banner and Form Clear Summary

**One-liner:** AJAX form submission for agent capture with success banner, 5s auto-dismiss, and form clear on new saves; error paths show error banner with scroll.

## Tasks Completed

| # | Name | Commit | Files |
|---|------|--------|-------|
| 1 | Add AJAX save handler and register action | 9fd6a45 | AgentsAjaxHandlers.php, AgentsController.php |
| 2 | Add feedback container to view and AJAX submit handler to JS | cdb36ee | agent-capture-form.view.php, agents-app.js |

## What Was Built

### Task 1: PHP AJAX Endpoint

- `handleSave()` method added to `AgentsAjaxHandlers` — verifies nonce (`agents_nonce_action`), capability (`edit_others_posts`), reads `editing_agent_id` from POST to distinguish create vs update, delegates to `AgentService::handleAgentFormSubmission()`, returns `{message, agent_id, is_new}` on success or `{errors}` on failure
- `wp_ajax_wecoza_agents_save` action registered in `registerHandlers()`
- `saveAction: 'wecoza_agents_save'` added to `wecozaAgents` localized JS config
- `messages.form` object added to localized config (`saved`, `saving`, `error` keys)

### Task 2: JS AJAX Handler + View Wrapper

**View changes (`agent-capture-form.view.php`):**
- Added `<div class="wecoza-agents-form-container">` wrapping everything from edit alert through `</form>`
- Added `<div class="wecoza-agents-feedback mt-3"></div>` as first child of container (renders banners above form)

**JS changes (`agents-app.js`):**
- Replaced old basic submit handler (which only added `was-validated`) with full AJAX handler
- `renderMessage(type, message)` — renders Bootstrap dismissible alert with `alert-subtle-success` or `alert-subtle-danger`
- `setSubmittingState(isSubmitting)` — disables submit button and shows "Saving..." text during request
- `clearForm()` — `form[0].reset()`, removes `was-validated`, removes `editing_agent_id` hidden field, clears all inputs explicitly, resets selects, resets ID type radio to SA ID, removes `is-valid`/`is-invalid` classes
- `scrollToFeedback()` — animates scroll to `container.offset().top - 80`
- `extractErrors(errors)` — joins field-level errors with `<br>`, falls back to generic message
- Form submit handler: checks validity, builds `FormData`, appends `action` and `nonce`, AJAX POST to `admin-ajax.php`
- On success+new: renders success, scrolls, clears form, auto-dismisses after 5s
- On success+update: renders success, scrolls, reloads page after 1.5s
- On error/fail: renders error, scrolls
- ID toggle code preserved intact

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check: PASSED

All 4 modified files exist on disk. Both commits (9fd6a45, cdb36ee) verified in git log.
