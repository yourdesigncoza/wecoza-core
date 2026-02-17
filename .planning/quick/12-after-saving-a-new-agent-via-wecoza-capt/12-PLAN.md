---
phase: quick-12
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - assets/js/agents/agents-app.js
  - views/agents/components/agent-capture-form.view.php
  - src/Agents/Controllers/AgentsController.php
  - src/Agents/Ajax/AgentsAjaxHandlers.php
autonomous: true
requirements: [QUICK-12]
must_haves:
  truths:
    - "After saving a NEW agent, a success banner appears at the top of the form"
    - "After saving a NEW agent, all form fields are cleared/reset to blank"
    - "The success banner auto-dismisses after 5 seconds with a fadeOut"
    - "After saving a NEW agent, the page scrolls to the feedback banner"
    - "On validation/server errors, an error banner appears and page scrolls to it"
    - "Edit mode (update existing agent) continues to work without clearing the form"
  artifacts:
    - path: "assets/js/agents/agents-app.js"
      provides: "AJAX form submission with success/error handling, clearForm, scrollToFeedback"
    - path: "views/agents/components/agent-capture-form.view.php"
      provides: "Feedback container div, wrapper container div for scrollToFeedback targeting"
    - path: "src/Agents/Ajax/AgentsAjaxHandlers.php"
      provides: "handleSave AJAX endpoint for agent create/update"
    - path: "src/Agents/Controllers/AgentsController.php"
      provides: "Registers wecoza_agents_save AJAX action, passes save action name in localized JS config"
  key_links:
    - from: "assets/js/agents/agents-app.js"
      to: "/wp-admin/admin-ajax.php"
      via: "jQuery AJAX POST with FormData"
      pattern: "action.*wecoza_agents_save"
    - from: "src/Agents/Ajax/AgentsAjaxHandlers.php"
      to: "AgentService::handleAgentFormSubmission"
      via: "handleSave method delegates to service"
      pattern: "handleAgentFormSubmission"
---

<objective>
Add AJAX form submission to the agent capture form with success banner display and form clearing after saving a new agent. Follows the same pattern implemented for the client capture form in quick-11.

Purpose: After saving a new agent, users currently see no feedback and the form retains all data. This task adds a visible success banner, auto-scrolls to it, clears the form for the next entry, and auto-dismisses the banner after 5 seconds.

Output: Modified agents-app.js with AJAX submit handler, updated view with feedback container, new AJAX handler for save, updated controller to register the AJAX action.
</objective>

<execution_context>
@assets/js/clients/client-capture.js (reference pattern from quick-11)
</execution_context>

<context>
@assets/js/agents/agents-app.js
@assets/js/agents/agent-form-validation.js
@views/agents/components/agent-capture-form.view.php
@src/Agents/Controllers/AgentsController.php
@src/Agents/Ajax/AgentsAjaxHandlers.php
@src/Agents/Services/AgentService.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add AJAX save handler and register action</name>
  <files>
    src/Agents/Ajax/AgentsAjaxHandlers.php
    src/Agents/Controllers/AgentsController.php
  </files>
  <action>
**AgentsAjaxHandlers.php** -- Add a `handleSave` method and register it:

1. In `registerHandlers()`, add:
   ```php
   add_action('wp_ajax_wecoza_agents_save', [$this, 'handleSave']);
   ```

2. Add `handleSave()` method that:
   - Verifies nonce via `AjaxSecurity::requireNonce('agents_nonce_action')`
   - Checks capability `edit_others_posts` via `AjaxSecurity::requireCapability()`
   - Gets `editing_agent_id` from POST (int, default 0) to determine create vs update
   - If editing (agent_id > 0), loads current agent via `$this->agentService->getAgent($agent_id)`; if not found, sends error
   - Calls `$this->agentService->handleAgentFormSubmission($_POST, $_FILES, $agent_id ?: null, $current_agent)`
   - On success: `AjaxSecurity::sendSuccess(['message' => 'Agent saved successfully.', 'agent_id' => $result['agent_id'], 'is_new' => ($agent_id === 0)])`
   - On failure: `AjaxSecurity::sendError(...)` with `$result['errors']` -- send as `['errors' => $result['errors']]` so JS can parse field-level errors
   - Wrap in try/catch for unexpected errors

**AgentsController.php** -- Update `enqueueAssets()`:

1. In the `wp_localize_script` call for `wecozaAgents`, add a `saveAction` key:
   ```php
   'saveAction' => 'wecoza_agents_save',
   ```
   Add it alongside the existing keys (ajaxUrl, nonce, etc.)
  </action>
  <verify>
    Grep for `wecoza_agents_save` in both files to confirm registration and localization. Verify no syntax errors by checking PHP lint: `php -l src/Agents/Ajax/AgentsAjaxHandlers.php && php -l src/Agents/Controllers/AgentsController.php`
  </verify>
  <done>
    AJAX endpoint `wecoza_agents_save` is registered and the action name is passed to JS via `wecozaAgents.saveAction`.
  </done>
</task>

<task type="auto">
  <name>Task 2: Add feedback container to view and AJAX submit handler to JS</name>
  <files>
    views/agents/components/agent-capture-form.view.php
    assets/js/agents/agents-app.js
  </files>
  <action>
**agent-capture-form.view.php** -- Add wrapper and feedback containers:

1. Wrap the entire form output (everything from the edit alert through the form) in a container div:
   ```html
   <div class="wecoza-agents-form-container">
       <div class="wecoza-agents-feedback mt-3"></div>
       <!-- existing edit alert and form here -->
   </div>
   ```
   Place the feedback div BEFORE the edit alert and form so banners appear at top.

**agents-app.js** -- Convert to AJAX submission following the client-capture.js pattern:

Replace the existing form submit handler (lines 22-33) with a full AJAX handler. Keep all other existing code (ID toggle logic lines 35-82). The new AJAX handler must:

1. **Variables** at top of IIFE (after existing `$form` and ID toggle code):
   ```javascript
   var form = $('#agents-form');
   var container = form.closest('.wecoza-agents-form-container');
   var submitButton = form.find('button[type="submit"]');
   var feedback = container.find('.wecoza-agents-feedback');
   ```

2. **`renderMessage(type, message)`** -- Same pattern as client-capture.js. Uses `alert-subtle-success` or `alert-subtle-danger` with Bootstrap dismissible close button.

3. **`setSubmittingState(isSubmitting)`** -- Disables submit button, changes text to "Saving..." while submitting, restores original text after.

4. **`scrollToFeedback()`** -- Scrolls to `container.offset().top - 80` with 400ms animation. Same as client pattern.

5. **`clearForm()`** -- Must handle agent-specific fields:
   - Call `form[0].reset()`
   - Remove Bootstrap `was-validated` class
   - Remove hidden `editing_agent_id` field if present
   - Clear all text/email/tel/number/date inputs: `form.find('input[type="text"], input[type="email"], input[type="tel"], input[type="number"], input[type="date"]').val('')`
   - Reset all selects to first option: `form.find('select').prop('selectedIndex', 0)`
   - Clear file inputs (they get cleared by `form[0].reset()`)
   - Reset ID type radio to SA ID (check `#sa_id_option`, show `#sa_id_field`, hide `#passport_field`)
   - Clear initials field
   - Remove any `is-valid` / `is-invalid` classes from all inputs: `form.find('.is-valid, .is-invalid').removeClass('is-valid is-invalid')`

6. **`form.on('submit', ...)`** handler:
   - Check `form[0].checkValidity()` -- if invalid, add `was-validated` class and return (no preventDefault)
   - `event.preventDefault()` -- prevent normal POST
   - Add `was-validated` class
   - Build `FormData` from `form[0]`
   - Append `action: wecozaAgents.saveAction` (i.e., `wecoza_agents_save`)
   - Append `nonce: wecozaAgents.nonce`
   - Call `setSubmittingState(true)`
   - AJAX POST to `wecozaAgents.ajaxUrl` with `processData: false, contentType: false`
   - `.done()`:
     - If `response.success`: render success message, call `scrollToFeedback()`, remove `was-validated` class
       - If `response.data.is_new` (new agent): call `clearForm()`, auto-dismiss banner after 5 seconds with `feedback.fadeOut(300, function() { $(this).empty().show(); })`
       - If NOT new (update): keep form data, optionally reload after 1.5s delay
     - If error response with `response.data.errors`: extract error messages (join with `<br>`), render error, scroll to feedback
     - Otherwise: generic error message, scroll to feedback
   - `.fail()`: generic error message, scroll to feedback
   - `.always()`: `setSubmittingState(false)`

IMPORTANT: Keep the existing ID toggle code (lines 35-82 of current file) intact. The new AJAX code should be added alongside it within the same IIFE, not replace it. Remove only the old basic submit handler (lines 22-33).

Also: The `needs-validation` class on the form triggers a SECOND submit handler in agent-form-validation.js (lines 84-112). That handler calls `event.preventDefault()` on invalid forms and adds `was-validated`. This is compatible with the AJAX approach -- on invalid, both handlers prevent submission. On valid, the agent-form-validation.js handler does NOT call preventDefault (it only does so for invalid forms), so the AJAX handler's preventDefault will be the one that fires. No changes needed to agent-form-validation.js.
  </action>
  <verify>
    1. `php -l views/agents/components/agent-capture-form.view.php` -- no syntax errors
    2. Verify feedback container exists: grep for `wecoza-agents-feedback` in the view
    3. Verify AJAX handler: grep for `wecoza_agents_save\|saveAction\|clearForm\|scrollToFeedback` in agents-app.js
    4. Open browser to the agent capture page, submit a new agent, verify: success banner appears, form clears, banner auto-dismisses after 5s
  </verify>
  <done>
    After saving a new agent via the form: a success banner is displayed at the top, the page scrolls to it, all form fields are cleared/reset, and the banner auto-dismisses after 5 seconds. Error cases show an error banner. Edit mode (update) does not clear the form.
  </done>
</task>

</tasks>

<verification>
1. Navigate to the agent capture form page
2. Fill in all required fields for a new agent
3. Click "Add New Agent"
4. Verify: success banner appears with green styling at top of form
5. Verify: page scrolls to the banner
6. Verify: all form fields are cleared/reset to blank
7. Verify: banner auto-dismisses after 5 seconds
8. Test error case: submit with missing required fields -- verify error banner and scroll
9. Test edit mode: navigate to edit an existing agent, update a field, save -- verify success banner shows but form is NOT cleared
</verification>

<success_criteria>
- New agent save shows success banner, clears form, auto-dismisses banner after 5s
- Edit agent save shows success banner, does NOT clear form
- Validation errors show error banner with field-specific messages
- Page scrolls to feedback banner on success and error
- File uploads (criminal record, signed agreement) still work via FormData AJAX
- No PHP syntax errors in modified files
</success_criteria>

<output>
After completion, create `.planning/quick/12-after-saving-a-new-agent-via-wecoza-capt/12-SUMMARY.md`
</output>
