# Feedback Widget Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build an integrated feedback widget that lets users submit structured, AI-guided feedback from any page, persisted locally and synced to Linear.

**Architecture:** New `src/Feedback/` module following WeCoza patterns (BaseRepository, AjaxSecurity, wecoza_view). Widget injected via `wp_footer` hook on all frontend pages. AJAX controller handles submissions, OpenAI validates quality, Linear API creates issues. PostgreSQL table as safety net.

**Tech Stack:** PHP 8.0+, PostgreSQL, OpenAI gpt-4o-mini, Linear REST API, html2canvas, jQuery AJAX, Bootstrap/Phoenix UI

**Design doc:** `docs/plans/2026-02-19-feedback-widget-design.md`

---

## Task 1: Database Schema + Repository

**Files:**
- Create: `schema/feedback_submissions.sql`
- Create: `src/Feedback/Repositories/FeedbackRepository.php`

**Step 1: Create the schema SQL file**

```sql
-- schema/feedback_submissions.sql
-- Feedback Widget - Local persistence for UAT feedback
-- Run manually: psql -U wecoza -d wecoza -f schema/feedback_submissions.sql

CREATE TABLE IF NOT EXISTS feedback_submissions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL,
    feedback_text TEXT NOT NULL,
    ai_conversation JSONB DEFAULT '[]'::jsonb,
    ai_generated_title VARCHAR(500),
    ai_suggested_priority VARCHAR(20),
    page_url TEXT,
    page_title VARCHAR(500),
    shortcode VARCHAR(255),
    url_params JSONB DEFAULT '{}'::jsonb,
    browser_info VARCHAR(500),
    viewport VARCHAR(50),
    screenshot_path VARCHAR(500),
    linear_issue_id VARCHAR(100),
    linear_issue_url VARCHAR(500),
    sync_status VARCHAR(20) DEFAULT 'pending',
    sync_attempts INTEGER DEFAULT 0,
    sync_error TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_feedback_sync_status ON feedback_submissions(sync_status);
CREATE INDEX IF NOT EXISTS idx_feedback_user ON feedback_submissions(user_id);
CREATE INDEX IF NOT EXISTS idx_feedback_created ON feedback_submissions(created_at);
```

**Step 2: Create FeedbackRepository extending BaseRepository**

```php
<?php
declare(strict_types=1);

namespace WeCoza\Feedback\Repositories;

use WeCoza\Core\Abstract\BaseRepository;

final class FeedbackRepository extends BaseRepository
{
    protected static string $table = 'feedback_submissions';
    protected static string $primaryKey = 'id';

    protected function getAllowedOrderColumns(): array
    {
        return ['id', 'user_id', 'category', 'sync_status', 'created_at', 'updated_at'];
    }

    protected function getAllowedFilterColumns(): array
    {
        return ['id', 'user_id', 'category', 'sync_status', 'shortcode', 'created_at'];
    }

    protected function getAllowedInsertColumns(): array
    {
        return [
            'user_id', 'user_email', 'category', 'feedback_text',
            'ai_conversation', 'ai_generated_title', 'ai_suggested_priority',
            'page_url', 'page_title', 'shortcode', 'url_params',
            'browser_info', 'viewport', 'screenshot_path',
            'linear_issue_id', 'linear_issue_url', 'sync_status',
        ];
    }

    protected function getAllowedUpdateColumns(): array
    {
        return [
            'feedback_text', 'ai_conversation', 'ai_generated_title',
            'ai_suggested_priority', 'linear_issue_id', 'linear_issue_url',
            'sync_status', 'sync_attempts', 'sync_error', 'updated_at',
        ];
    }

    public function findPendingSync(int $maxAttempts = 5, int $limit = 20): array
    {
        $sql = "SELECT * FROM feedback_submissions
                WHERE sync_status IN ('pending', 'failed')
                AND sync_attempts < :max_attempts
                ORDER BY created_at ASC
                LIMIT :limit";

        $pdo = $this->db->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':max_attempts', $maxAttempts, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function markSynced(int $id, string $linearIssueId, string $linearIssueUrl): bool
    {
        return $this->update($id, [
            'linear_issue_id'  => $linearIssueId,
            'linear_issue_url' => $linearIssueUrl,
            'sync_status'      => 'synced',
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Atomic markFailed - avoids race condition by using SQL increment
     * instead of read-then-write pattern (Gemini review fix)
     */
    public function markFailed(int $id, string $error): bool
    {
        $sql = "UPDATE feedback_submissions
                SET sync_attempts = sync_attempts + 1,
                    sync_status = CASE
                        WHEN sync_attempts + 1 >= 5 THEN 'permanently_failed'
                        ELSE 'failed'
                    END,
                    sync_error = :error,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";

        try {
            $stmt = $this->db->query($sql, ['id' => $id, 'error' => $error]);
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            error_log(wecoza_sanitize_exception($e->getMessage(), 'FeedbackRepository::markFailed'));
            return false;
        }
    }
}
```

**Step 3: Give the schema SQL to user to run manually**

Provide the SQL and remind: "Please run `schema/feedback_submissions.sql` against your PostgreSQL database."

**Step 4: Commit**

```bash
git add schema/feedback_submissions.sql src/Feedback/Repositories/FeedbackRepository.php
git commit -m "feat(feedback): add feedback_submissions schema + FeedbackRepository"
```

---

## Task 2: Schema Context Service

**Files:**
- Create: `src/Feedback/Support/SchemaContext.php`

**Step 1: Create curated schema mapping**

```php
<?php
declare(strict_types=1);

namespace WeCoza\Feedback\Support;

final class SchemaContext
{
    private const MODULE_SCHEMAS = [
        'agents' => [
            'module'      => 'Agents',
            'description' => 'Agent management - company representatives managing classes',
            'tables'      => 'agents (id, first_name, last_name, email, cell_number, tel_number, alt_cell_number, id_number, company_id, status, created_at)',
            'related'     => 'agent_meta (key-value metadata), agent_notes (timestamped notes), agent_absences (date ranges)',
        ],
        'learners' => [
            'module'      => 'Learners',
            'description' => 'Learner management - students enrolled in learning programmes',
            'tables'      => 'learners (id, first_name, last_name, id_number, email, cell_number, gender, race, disability_status, highest_qualification, province, created_at)',
            'related'     => 'learner_progressions (lp tracking: hours_trained, hours_present, hours_absent, progress_percentage, status, portfolio_path)',
        ],
        'classes' => [
            'module'      => 'Classes',
            'description' => 'Class management - training sessions linking clients, agents, and learners',
            'tables'      => 'classes (class_id, client_id, site_id, class_type, class_subject, class_code, class_duration, original_start_date, seta_funded, class_agent, project_supervisor_id)',
            'related'     => 'class_schedules (day_of_week, start_time, end_time), class_learners (linking table), qa_visits (quality assurance)',
        ],
        'clients' => [
            'module'      => 'Clients',
            'description' => 'Client/company management - organizations that commission training',
            'tables'      => 'clients (id, company_name, trading_name, registration_number, contact_person, email, phone, address, city, province)',
            'related'     => 'locations (site_id, site_name, address, city, province)',
        ],
    ];

    private const SHORTCODE_MODULE_MAP = [
        'wecoza_capture_agents'         => 'agents',
        'wecoza_display_agents'         => 'agents',
        'wecoza_single_agent'           => 'agents',
        'wecoza_display_learners'       => 'learners',
        'wecoza_learners_form'          => 'learners',
        'wecoza_single_learner_display' => 'learners',
        'wecoza_learners_update_form'   => 'learners',
        'wecoza_capture_class'          => 'classes',
        'wecoza_display_classes'        => 'classes',
        'wecoza_display_single_class'   => 'classes',
        'wecoza_capture_clients'        => 'clients',
        'wecoza_display_clients'        => 'clients',
        'wecoza_update_clients'         => 'clients',
        'wecoza_locations_capture'      => 'clients',
        'wecoza_locations_list'         => 'clients',
        'wecoza_locations_edit'         => 'clients',
    ];

    public static function getModuleFromShortcode(string $shortcode): ?string
    {
        return self::SHORTCODE_MODULE_MAP[$shortcode] ?? null;
    }

    public static function getModuleFromUrl(string $url): ?string
    {
        $urlLower = strtolower($url);
        foreach (['agents', 'learners', 'classes', 'clients'] as $module) {
            if (str_contains($urlLower, $module)) {
                return $module;
            }
        }
        return null;
    }

    public static function getSchemaForModule(?string $module): string
    {
        if ($module === null || !isset(self::MODULE_SCHEMAS[$module])) {
            return 'General WeCoza page - no specific module context available.';
        }

        $schema = self::MODULE_SCHEMAS[$module];

        return sprintf(
            "Module: %s\nDescription: %s\nMain table: %s\nRelated: %s",
            $schema['module'],
            $schema['description'],
            $schema['tables'],
            $schema['related']
        );
    }

    public static function detectModule(?string $shortcode, ?string $url): ?string
    {
        if ($shortcode) {
            $module = self::getModuleFromShortcode($shortcode);
            if ($module) {
                return $module;
            }
        }

        if ($url) {
            return self::getModuleFromUrl($url);
        }

        return null;
    }
}
```

**Step 2: Commit**

```bash
git add src/Feedback/Support/SchemaContext.php
git commit -m "feat(feedback): add SchemaContext with curated module-to-schema mapping"
```

---

## Task 3: AI Feedback Service

**Files:**
- Create: `src/Feedback/Services/AIFeedbackService.php`

**Step 1: Create the AI service**

This service handles:
1. Vagueness check (returns follow-up question or clears)
2. Enrichment (generates title, priority, structured body)
3. Fallback when OpenAI is unreachable

Key implementation notes:
- Uses `wp_remote_post()` for OpenAI API calls
- API key from `get_option('wecoza_openai_api_key')`
- Model: `gpt-4o-mini`
- Timeout: 15 seconds per API call
- Returns structured array responses

**Vagueness check prompt structure:**
```
System: You are a feedback quality assistant for WeCoza, an internal training management system.
You help users write clear, actionable feedback. The user is on the {module} module.

Schema context: {curated_schema}

Evaluate the feedback and return JSON:
- If feedback is clear and actionable: {"is_clear": true, "follow_up": null}
- If feedback is vague: {"is_clear": false, "follow_up": "Your specific question here"}

Rules for {category}:
- Bug Report: needs what happened + what was expected
- Feature Request: needs what they want + why
- Comment: any substantive text is fine
- Under 10 chars or "fix this"/"broken" without context = vague
```

**Enrichment prompt structure:**
```
System: Generate a structured issue from this feedback.
Return JSON: {"title": "...", "priority": "Urgent|High|Medium|Low", "body": "markdown..."}
```

**Robust AI response parsing (Gemini review fix):**
- Wrap all `json_decode()` calls in try/catch
- If AI returns malformed JSON, attempt to extract JSON from response text via regex
- If still unparseable, fall back to: `is_clear = true` (skip follow-up), use raw feedback text as title/body
- Log malformed AI responses via `wecoza_log()` for debugging
- Never let an AI parsing failure block feedback submission

**Step 2: Commit**

```bash
git add src/Feedback/Services/AIFeedbackService.php
git commit -m "feat(feedback): add AIFeedbackService with vagueness check + enrichment"
```

---

## Task 4: Linear Integration Service

**Files:**
- Create: `src/Feedback/Services/LinearIntegrationService.php`

**Step 1: Create the Linear service**

Key implementation notes:
- Linear uses **GraphQL API** at `https://api.linear.app/graphql`
- API key from `get_option('wecoza_linear_api_key')` - sent as `Authorization: Bearer {key}`
- **Team ID** from `get_option('wecoza_linear_team_id')` - required for `issueCreate` (Gemini review fix)
- Uses `wp_remote_post()` for API calls

**Required GraphQL mutations:**
1. `issueCreate` - create the issue with title, description, **teamId** (required UUID), labelIds, priority, projectId
2. `attachmentCreate` - attach screenshot URL (Linear doesn't accept direct file upload via GraphQL - need to use `fileUpload` mutation or attachment URL)

**For screenshot attachment:** Upload the screenshot to the WordPress uploads directory first, make it accessible via URL, then attach that URL to the Linear issue.

**Label handling:** On first call, fetch existing labels. If "Bug", "Feature Request", "Comment", "UAT Feedback", or module labels don't exist, create them. Cache label IDs in a transient for 24 hours.

**Error handling:** Return success/failure result array with specific error types. Distinguish between retryable errors (network, rate limit) and permanent errors (invalid data, missing team). Caller (FeedbackController) handles marking DB record as synced or failed.

**Pre-flight check:** Before creating issue, validate that team ID and API key are configured. Return early with descriptive error if not.

**Step 2: Commit**

```bash
git add src/Feedback/Services/LinearIntegrationService.php
git commit -m "feat(feedback): add LinearIntegrationService with GraphQL API integration"
```

---

## Task 5: Feedback Controller (AJAX Handler)

**Files:**
- Create: `src/Feedback/Controllers/FeedbackController.php`

**Step 1: Create the AJAX controller**

Follows the existing `TaskController::register()` pattern:

```php
<?php
declare(strict_types=1);

namespace WeCoza\Feedback\Controllers;

use WeCoza\Core\Helpers\AjaxSecurity;
use WeCoza\Feedback\Repositories\FeedbackRepository;
use WeCoza\Feedback\Services\AIFeedbackService;
use WeCoza\Feedback\Services\LinearIntegrationService;

final class FeedbackController
{
    private FeedbackRepository $repository;
    private AIFeedbackService $aiService;
    private LinearIntegrationService $linearService;

    public function __construct(
        ?FeedbackRepository $repository = null,
        ?AIFeedbackService $aiService = null,
        ?LinearIntegrationService $linearService = null
    ) {
        $this->repository    = $repository ?? new FeedbackRepository();
        $this->aiService     = $aiService ?? new AIFeedbackService();
        $this->linearService = $linearService ?? new LinearIntegrationService();
    }

    public static function register(?self $controller = null): void
    {
        $instance = $controller ?? new self();
        add_action('wp_ajax_wecoza_feedback_submit', [$instance, 'handleSubmit']);
        add_action('wp_ajax_wecoza_feedback_followup', [$instance, 'handleFollowup']);
    }

    // handleSubmit: validate nonce + login, save screenshot, save to DB,
    //               call AI vagueness check, return follow_up or proceed to Linear
    // handleFollowup: receive follow-up answer, re-check with AI,
    //                 if clear or max rounds -> enrich + push to Linear
}
```

**Two AJAX actions:**
- `wecoza_feedback_submit` - Initial submission (category, text, context, screenshot)
- `wecoza_feedback_followup` - Follow-up response (feedback_id, answer, round number)

**Screenshot handling:**
- Receive base64 JPEG from client
- Validate size (max 2MB after decode)
- Save to `wp-content/uploads/wecoza-feedback/YYYY/MM/` directory
- Store server path in DB, generate URL for Linear attachment

**Step 2: Commit**

```bash
git add src/Feedback/Controllers/FeedbackController.php
git commit -m "feat(feedback): add FeedbackController AJAX handler"
```

---

## Task 6: Feedback Sync Service (Cron Retry)

**Files:**
- Create: `src/Feedback/Services/FeedbackSyncService.php`

**Step 1: Create the sync service**

```php
<?php
declare(strict_types=1);

namespace WeCoza\Feedback\Services;

use WeCoza\Feedback\Repositories\FeedbackRepository;

final class FeedbackSyncService
{
    private FeedbackRepository $repository;
    private LinearIntegrationService $linearService;

    public function __construct(
        ?FeedbackRepository $repository = null,
        ?LinearIntegrationService $linearService = null
    ) {
        $this->repository    = $repository ?? new FeedbackRepository();
        $this->linearService = $linearService ?? new LinearIntegrationService();
    }

    public function retryFailedSubmissions(): void
    {
        $pending = $this->repository->findPendingSync(5, 20);

        foreach ($pending as $record) {
            try {
                $result = $this->linearService->createIssue($record);

                if ($result['success']) {
                    $this->repository->markSynced(
                        (int) $record['id'],
                        $result['issue_id'],
                        $result['issue_url']
                    );
                    wecoza_log("Feedback #{$record['id']} synced to Linear: {$result['issue_id']}");
                } else {
                    $this->repository->markFailed((int) $record['id'], $result['error']);
                    wecoza_log("Feedback #{$record['id']} sync failed: {$result['error']}", 'error');
                }
            } catch (\Exception $e) {
                $this->repository->markFailed((int) $record['id'], $e->getMessage());
                wecoza_log("Feedback #{$record['id']} sync exception: {$e->getMessage()}", 'error');
            }
        }
    }
}
```

**Step 2: Commit**

```bash
git add src/Feedback/Services/FeedbackSyncService.php
git commit -m "feat(feedback): add FeedbackSyncService for cron retry of failed Linear syncs"
```

---

## Task 7: Widget View + JavaScript

**Files:**
- Create: `views/feedback/widget.view.php`
- Create: `assets/js/feedback/feedback-widget.js`

**Step 1: Create the modal HTML view**

The view renders:
- Floating action button (speech bubble icon, fixed bottom-right)
- Bootstrap modal with:
  - Category pills (Bug Report / Feature Request / Comment)
  - Context banner (page info)
  - Screenshot thumbnail
  - Feedback textarea
  - Submit button
  - Hidden AI follow-up area (question + answer textarea + re-submit)
- Toast container for success/error messages

Use Phoenix/Bootstrap classes per project conventions. No separate CSS file - any custom CSS goes in `ydcoza-styles.css`.

**Step 2: Create the JavaScript**

`feedback-widget.js` handles:
1. **Screenshot capture:** On FAB click, `html2canvas(document.body)` → resize to max 1280px → JPEG 60% → base64
   - **Client-side size check (Gemini review fix):** After compression, check base64 length. If > 2MB, reduce quality to 40% and retry. If still > 2MB, skip screenshot and submit without it.
2. **Context collection:** URL, title, `[data-wecoza-shortcode]`, URL params, viewport
3. **AJAX submit:** POST to `wecoza_feedback_submit` with all data
4. **Follow-up handling:** If response has `follow_up`, show AI question + textarea, POST to `wecoza_feedback_followup`
5. **Round tracking:** Count rounds (max 3), show "Submitting..." after max
6. **Toast notifications:** Success and error toasts
7. **Loading states:** Disable submit button, show spinner during AJAX

Localized data via `wp_localize_script()`:
```php
wp_localize_script('wecoza-feedback-widget', 'wecozaFeedback', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce'   => wp_create_nonce('wecoza_feedback'),
    'user'    => wp_get_current_user()->user_email,
    'page'    => get_the_title(),
]);
```

**Step 3: Commit**

```bash
git add views/feedback/widget.view.php assets/js/feedback/feedback-widget.js
git commit -m "feat(feedback): add widget view template + JavaScript UI logic"
```

---

## Task 8: Widget Shortcode (Footer Injection)

**Files:**
- Create: `src/Feedback/Shortcodes/FeedbackWidgetShortcode.php`

**Step 1: Create the shortcode class**

This is NOT a traditional shortcode - it injects the widget via `wp_footer` on all frontend pages:

```php
<?php
declare(strict_types=1);

namespace WeCoza\Feedback\Shortcodes;

final class FeedbackWidgetShortcode
{
    private bool $assetsEnqueued = false;

    public static function register(?self $instance = null): void
    {
        $widget = $instance ?? new self();

        // Only on frontend, for logged-in users
        if (is_admin()) {
            return;
        }

        add_action('wp_enqueue_scripts', [$widget, 'enqueueAssets']);
        add_action('wp_footer', [$widget, 'renderWidget']);
    }

    public function enqueueAssets(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        // html2canvas from CDN
        wp_enqueue_script(
            'html2canvas',
            'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js',
            [],
            '1.4.1',
            true
        );

        wp_enqueue_script(
            'wecoza-feedback-widget',
            wecoza_js_url('feedback/feedback-widget.js'),
            ['jquery', 'html2canvas'],
            WECOZA_CORE_VERSION,
            true
        );

        wp_localize_script('wecoza-feedback-widget', 'wecozaFeedback', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wecoza_feedback'),
            'user'    => wp_get_current_user()->user_email,
        ]);

        $this->assetsEnqueued = true;
    }

    public function renderWidget(): void
    {
        if (!is_user_logged_in() || !$this->assetsEnqueued) {
            return;
        }

        echo wecoza_view('feedback/widget', [], true);
    }
}
```

**Step 2: Commit**

```bash
git add src/Feedback/Shortcodes/FeedbackWidgetShortcode.php
git commit -m "feat(feedback): add FeedbackWidgetShortcode for wp_footer injection"
```

---

## Task 9: Plugin Registration (wecoza-core.php)

**Files:**
- Modify: `wecoza-core.php`

**Step 1: Add namespace to autoloader**

In the `$namespaces` array (around line 48-60), add:
```php
"WeCoza\\Feedback\\" => WECOZA_CORE_PATH . "src/Feedback/",
```

**Step 1b: Add `wecoza_linear_team_id` to SettingsPage (Gemini review fix)**

In `src/Settings/SettingsPage.php`, add `wecoza_linear_team_id` as a text field alongside the Linear API key. This stores the Linear team UUID required by `issueCreate`. The user gets this from Linear Settings > Teams.

**Step 2: Register Feedback module components**

After the Settings Module registration block (around line 265), add:
```php
// Initialize Feedback Widget Module
if (class_exists(\WeCoza\Feedback\Shortcodes\FeedbackWidgetShortcode::class)) {
    \WeCoza\Feedback\Shortcodes\FeedbackWidgetShortcode::register();
}
if (class_exists(\WeCoza\Feedback\Controllers\FeedbackController::class)) {
    \WeCoza\Feedback\Controllers\FeedbackController::register();
}
```

**Step 3: Add cron schedule + handler**

Add custom 15-minute interval:
```php
add_filter('cron_schedules', function (array $schedules): array {
    $schedules['every_fifteen_minutes'] = [
        'interval' => 900,
        'display'  => __('Every 15 Minutes', 'wecoza-core'),
    ];
    return $schedules;
});
```

Add cron handler:
```php
add_action('wecoza_feedback_retry_sync', function () {
    try {
        $service = new \WeCoza\Feedback\Services\FeedbackSyncService();
        $service->retryFailedSubmissions();
    } catch (\Exception $e) {
        wecoza_log('Feedback sync retry failed: ' . $e->getMessage(), 'error');
    }
});
```

**Step 4: Add cron to activation hook**

In the `register_activation_hook` callback (around line 730):
```php
if (!wp_next_scheduled('wecoza_feedback_retry_sync')) {
    wp_schedule_event(time(), 'every_fifteen_minutes', 'wecoza_feedback_retry_sync');
}
```

**Step 5: Add cron cleanup to deactivation hook**

In the `register_deactivation_hook` callback (around line 770):
```php
$feedbackTimestamp = wp_next_scheduled('wecoza_feedback_retry_sync');
if ($feedbackTimestamp) {
    wp_unschedule_event($feedbackTimestamp, 'wecoza_feedback_retry_sync');
}
```

**Step 6: Commit**

```bash
git add wecoza-core.php
git commit -m "feat(feedback): register Feedback module namespace, AJAX, cron in wecoza-core.php"
```

---

## Task 10: CSS Styles

**Files:**
- Modify: `/opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css`

**Step 1: Check Phoenix for relevant existing classes**

Search Phoenix extracted HTML for floating button, FAB, modal patterns. Use whatever exists.

**Step 2: Add minimal custom CSS**

Only add what Phoenix doesn't provide:
- FAB positioning (fixed bottom-right, z-index)
- Screenshot thumbnail sizing in modal
- AI follow-up area styling
- Toast positioning

**Step 3: Commit**

```bash
git add /opt/lampp/htdocs/wecoza/wp-content/themes/wecoza_3_child_theme/includes/css/ydcoza-styles.css
git commit -m "feat(feedback): add feedback widget CSS to ydcoza-styles.css"
```

---

## Task 11: Integration Testing

**Step 1: Manual testing checklist**

1. Navigate to any frontend page as logged-in user
2. Verify FAB button appears bottom-right
3. Click FAB - verify screenshot captured, modal opens
4. Verify context banner shows correct page info
5. Select "Bug Report", type "fix this", submit
6. Verify AI follow-up question appears
7. Respond with detail, verify it either passes or asks again (max 3)
8. After final submit, verify:
   - Toast shows "Feedback submitted, thank you!"
   - Record exists in `feedback_submissions` table
   - Linear issue created in UAT Feedback project with labels
   - Screenshot attached to Linear issue
9. Test fallback: temporarily invalidate OpenAI key, submit feedback, verify it goes through raw
10. Test Linear failure: temporarily invalidate Linear key, submit, verify record saved locally with `sync_status = 'pending'`

**Step 2: Commit any fixes**

---

## Task Dependency Order

```
Task 1 (DB + Repo)
  ↓
Task 2 (SchemaContext) ──────────────────┐
  ↓                                      ↓
Task 3 (AI Service) ─────────→ Task 5 (Controller)
  ↓                                      ↑
Task 4 (Linear Service) ────────────────┘
  ↓
Task 6 (Sync Service)
  ↓
Task 7 (View + JS)
  ↓
Task 8 (Widget Shortcode)
  ↓
Task 9 (Plugin Registration)
  ↓
Task 10 (CSS)
  ↓
Task 11 (Integration Testing)
```

Tasks 2, 3, 4 can be implemented in parallel after Task 1.
Tasks 7 + 8 can be worked on in parallel with Tasks 3-6 (just need the AJAX action names).
