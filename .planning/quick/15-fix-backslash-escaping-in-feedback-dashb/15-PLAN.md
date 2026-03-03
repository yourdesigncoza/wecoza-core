---
phase: 15-fix-backslash-escaping-in-feedback-dashb
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - src/Feedback/Controllers/FeedbackController.php
  - src/Feedback/Shortcodes/FeedbackDashboardShortcode.php
autonomous: true
requirements: [BUG-BACKSLASH-ESCAPE]
must_haves:
  truths:
    - "Feedback text containing quotes (e.g. 'status from \"On Hold\" to \"Resume\"') displays without backslashes in the dashboard"
    - "New comments added via the dashboard display without backslashes"
    - "AI follow-up answers containing quotes are stored without backslashes"
  artifacts:
    - path: "src/Feedback/Controllers/FeedbackController.php"
      provides: "wp_unslash applied to all $_POST inputs before sanitization"
    - path: "src/Feedback/Shortcodes/FeedbackDashboardShortcode.php"
      provides: "wp_unslash applied to comment_text before sanitization"
  key_links:
    - from: "FeedbackController::handleSubmit"
      to: "FeedbackRepository::insert"
      via: "wp_unslash -> wecoza_sanitize_value -> insert"
      pattern: "wp_unslash.*\\$_POST"
    - from: "FeedbackDashboardShortcode::handleComment"
      to: "FeedbackCommentRepository::insert"
      via: "wp_unslash -> sanitize_textarea_field -> insert"
      pattern: "wp_unslash.*\\$_POST"
---

<objective>
Fix backslash escaping in Feedback Dashboard comments and feedback text.

Purpose: WordPress adds "magic quotes" (backslashes before quotes) to all $_POST data via wp_magic_quotes(). The Feedback module does not call wp_unslash() before sanitizing, so text like `status from "On Hold" to "Resume"` gets stored as `status from \"On Hold\" to \"Resume\"` and displays with visible backslashes.

Output: Clean, unescaped text displayed in the feedback dashboard for all feedback items and comments.
</objective>

<execution_context>
@/home/laudes/.claude/get-shit-done/workflows/execute-plan.md
@/home/laudes/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@src/Feedback/Controllers/FeedbackController.php
@src/Feedback/Shortcodes/FeedbackDashboardShortcode.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add wp_unslash to all $_POST reads in Feedback module</name>
  <files>src/Feedback/Controllers/FeedbackController.php, src/Feedback/Shortcodes/FeedbackDashboardShortcode.php</files>
  <action>
In FeedbackController::handleSubmit() (lines 45-51), wrap every $_POST value with wp_unslash() BEFORE passing to wecoza_sanitize_value(). Change:
```php
$category     = wecoza_sanitize_value($_POST['category'] ?? '', 'string');
$feedbackText = wecoza_sanitize_value($_POST['feedback_text'] ?? '', 'string');
$pageUrl      = wecoza_sanitize_value($_POST['page_url'] ?? '', 'string');
$pageTitle    = wecoza_sanitize_value($_POST['page_title'] ?? '', 'string');
$shortcode    = wecoza_sanitize_value($_POST['shortcode'] ?? '', 'string');
$browserInfo  = wecoza_sanitize_value($_POST['browser_info'] ?? '', 'string');
$viewport     = wecoza_sanitize_value($_POST['viewport'] ?? '', 'string');
```
To:
```php
$category     = wecoza_sanitize_value(wp_unslash($_POST['category'] ?? ''), 'string');
$feedbackText = wecoza_sanitize_value(wp_unslash($_POST['feedback_text'] ?? ''), 'string');
$pageUrl      = wecoza_sanitize_value(wp_unslash($_POST['page_url'] ?? ''), 'string');
$pageTitle    = wecoza_sanitize_value(wp_unslash($_POST['page_title'] ?? ''), 'string');
$shortcode    = wecoza_sanitize_value(wp_unslash($_POST['shortcode'] ?? ''), 'string');
$browserInfo  = wecoza_sanitize_value(wp_unslash($_POST['browser_info'] ?? ''), 'string');
$viewport     = wecoza_sanitize_value(wp_unslash($_POST['viewport'] ?? ''), 'string');
```

In FeedbackController::handleFollowup() (line 133), same pattern:
```php
$answer = wecoza_sanitize_value(wp_unslash($_POST['answer'] ?? ''), 'string');
```

Also in handleSubmit() line 64, the url_params already has stripslashes() — replace with wp_unslash() for consistency:
```php
$decoded = json_decode(wp_unslash($_POST['url_params']), true);
```

In FeedbackDashboardShortcode::handleComment() (line 77), wrap with wp_unslash():
```php
$commentText = sanitize_textarea_field(wp_unslash($_POST['comment_text'] ?? ''));
```

Do NOT change how data is read FROM the database or rendered in views — esc_html() in the view is correct and must stay. The fix is purely at the INPUT layer where $_POST is first read.
  </action>
  <verify>
    <automated>grep -n "wp_unslash" src/Feedback/Controllers/FeedbackController.php src/Feedback/Shortcodes/FeedbackDashboardShortcode.php | wc -l</automated>
    Expect 10 matches (9 in Controller, 1 in Shortcode). Also verify no raw $_POST reads remain without wp_unslash:
    grep -n "\$_POST\[" src/Feedback/Controllers/FeedbackController.php src/Feedback/Shortcodes/FeedbackDashboardShortcode.php | grep -v "wp_unslash" | grep -v "feedback_id\|round\|skip\|screenshot"
    Should return empty (feedback_id, round, skip are integers cast with (int) — no string escaping needed; screenshot is base64 — no quotes).
  </verify>
  <done>All string-type $_POST values in FeedbackController (handleSubmit + handleFollowup) and FeedbackDashboardShortcode (handleComment) are wrapped with wp_unslash() before sanitization. Future feedback submissions and comments will be stored without spurious backslashes.</done>
</task>

</tasks>

<verification>
1. `grep -c "wp_unslash" src/Feedback/Controllers/FeedbackController.php` returns 9+
2. `grep -c "wp_unslash" src/Feedback/Shortcodes/FeedbackDashboardShortcode.php` returns 1+
3. No PHP syntax errors: `php -l src/Feedback/Controllers/FeedbackController.php && php -l src/Feedback/Shortcodes/FeedbackDashboardShortcode.php`
</verification>

<success_criteria>
- All string $_POST values in the Feedback module pass through wp_unslash() before sanitization
- PHP lint passes on both modified files
- New feedback submissions containing quotes will be stored and displayed without backslashes
- Note: Existing records already in the database still contain backslashes — this fix prevents new occurrences only
</success_criteria>

<output>
After completion, create `.planning/quick/15-fix-backslash-escaping-in-feedback-dashb/15-SUMMARY.md`
</output>
