---
phase: quick-11
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - assets/js/clients/client-capture.js
autonomous: true
requirements: [QUICK-11]

must_haves:
  truths:
    - "After saving a new client, a success banner is visible at the top of the form"
    - "After saving a new client, the page scrolls to the success banner so the user sees it"
    - "After saving a new client, all form fields are reset to empty/default state"
    - "Location cascading fields (town, suburb, postal, address) are hidden after reset"
    - "The success banner remains visible long enough for the user to read it (not auto-dismissed too quickly)"
  artifacts:
    - path: "assets/js/clients/client-capture.js"
      provides: "AJAX success handler with scroll-to-top, prominent banner, and form reset"
  key_links:
    - from: "assets/js/clients/client-capture.js"
      to: "AJAX response from wecoza_save_client"
      via: "$.ajax .done() callback"
      pattern: "renderMessage.*success.*clearForm"
---

<objective>
Fix the client capture form's post-save UX: after successfully saving a new client via AJAX, scroll to the top of the form container so the success banner is visible, and immediately clear/reset all form fields. The existing code has the pieces (renderMessage, clearForm) but the UX flow has issues: no scroll-to-top means users miss the banner on long forms, and the staggered timeouts (1.5s to clear, then 3s more to hide banner) create a confusing experience.

Purpose: Users currently miss the success confirmation because the banner renders off-screen at the top of the container while they're scrolled down near the submit button. The form also has a delayed clear that feels sluggish.

Output: Updated client-capture.js with improved post-save UX flow.
</objective>

<context>
@assets/js/clients/client-capture.js
@src/Clients/Controllers/ClientsController.php (lines 86-96 — localization with clear_form_on_success)
@views/clients/components/client-capture-form.view.php (line 95 — .wecoza-clients-form-container)
</context>

<tasks>

<task type="auto">
  <name>Task 1: Improve post-save success flow in client-capture.js</name>
  <files>assets/js/clients/client-capture.js</files>
  <action>
In the `.done()` callback of the AJAX form submit handler (around line 523), restructure the success flow for new client saves:

1. **Scroll to banner immediately after rendering it:** After `renderMessage('success', message)` on line 527, add a smooth scroll to the `.wecoza-clients-form-container` element (the banner's parent). Use the existing jQuery `$('html, body').animate({ scrollTop: container.offset().top - 80 }, 400)` pattern consistent with other modules (see agents-ajax-pagination.js line 303-305 and class-capture.js line 1556-1558).

2. **Clear form immediately, not after 1.5s delay:** Replace the `setTimeout` wrapping `clearForm()` (lines 549-556) with immediate execution. The user has already seen the "Saving client..." button state — they know it was submitted. Showing success + clearing immediately is cleaner UX. Keep the success banner visible for 5 seconds (increased from 3s) so the user can read it after scroll.

3. **Remove the hidden id input after clear:** After `clearForm()`, also remove the dynamically-created `input[name="id"]` hidden field (lines 535-539 create it for the just-saved client). If it persists, subsequent saves will be treated as updates instead of new creates. The `clearForm` function already clears its value (line 60: `form.find('input[name="id"]').val('')`), but to be safe, fully remove the element if it was dynamically created (i.e., if the form is the capture form, not the update form).

The revised success block for new clients should flow:
```
renderMessage('success', message);
// scroll to top
$('html, body').animate({ scrollTop: container.offset().top - 80 }, 400);
// clear form immediately
clearForm();
// remove dynamic id field so next save creates new client
form.find('input[name="id"]').remove();
form.find('input[name="head_site_id"]').val('');
// auto-dismiss banner after 5 seconds
setTimeout(function() { feedback.fadeOut(300, function() { $(this).empty().show(); }); }, 5000);
```

Do NOT change the update flow (the `else if (!isNewClient)` branch with `window.location.reload()` is fine).

Do NOT move the `isNewClient` check — it must still happen BEFORE the id input is set (line 533 must stay before lines 534-539). But restructure so the new-client clear path does NOT set the id/head_site_id at all (those are only needed for the update path).

The logic should be:
```
if (data.client && data.client.id) {
    if (isNewClient && config.clear_form_on_success) {
        // NEW CLIENT: scroll + clear + dismiss banner
        // Do NOT set id input — form is being cleared for next entry
    } else if (isNewClient) {
        // NEW CLIENT but clear_form_on_success is false: set id so form becomes edit mode
        // (existing behavior for lines 535-546)
    } else {
        // UPDATE: reload page
    }
}
```

Also add the scroll-to-top for error messages (in the `.fail()` and error branches) so users see errors too.
  </action>
  <verify>
    1. Open browser to the client capture page with wecoza_capture_clients shortcode
    2. Fill in required fields and submit
    3. Verify: page scrolls to top, green success banner visible, all fields cleared
    4. Verify: success banner fades out after ~5 seconds
    5. Verify: submitting again creates a NEW client (not an update to the previous one)
    6. Verify: on validation error, page scrolls to top and red error banner is visible
  </verify>
  <done>
    After saving a new client: success banner is visible (scrolled into view), all form fields are reset to empty/default, location cascade fields are hidden, and the banner auto-dismisses after 5 seconds. Subsequent saves create new clients.
  </done>
</task>

</tasks>

<verification>
- Manual test: Create new client, confirm scroll + banner + clear
- Manual test: Create second client immediately after, confirm it gets a new ID (not updating first)
- Manual test: Submit invalid form, confirm error banner visible with scroll
- Manual test: Update existing client (via update shortcode), confirm page reloads as before
</verification>

<success_criteria>
- Success banner scrolls into view immediately after save
- All form fields cleared/reset after new client save
- Location cascade fields (town, suburb, postal, address) hidden after reset
- Banner stays visible for 5 seconds then fades out
- No regression on update flow (still reloads page)
- Error messages also scroll into view
</success_criteria>

<output>
After completion, create `.planning/quick/11-after-saving-a-new-client-via-wecoza-cap/11-SUMMARY.md`
</output>
