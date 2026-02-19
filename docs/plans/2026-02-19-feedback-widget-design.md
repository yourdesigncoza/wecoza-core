# Feedback Widget - Design Document

**Date:** 2026-02-19
**Status:** Approved (reviewed by Gemini 2.5 Pro)

## Purpose

Integrated feedback system for UAT testing. Allows any logged-in user to submit structured feedback (bug reports, feature requests, comments) from any page. AI-guided to ensure quality. Pushes directly to Linear as issues.

## Architecture

### New Module: `src/Feedback/`

```
src/Feedback/
  Controllers/FeedbackController.php      # AJAX handler
  Services/AIFeedbackService.php          # OpenAI vagueness check + enrichment
  Services/LinearIntegrationService.php   # Linear issue creation via REST API
  Shortcodes/FeedbackWidgetShortcode.php  # Renders floating button + modal
  Support/SchemaContext.php               # Curated module-to-schema mapping
  Repositories/FeedbackRepository.php     # Local DB persistence (safety net)
views/feedback/widget.view.php            # Modal HTML template
assets/js/feedback/feedback-widget.js     # UI logic, AJAX, html2canvas
```

**Namespace:** `WeCoza\Feedback\`

### Dependencies

- `get_option('wecoza_openai_api_key')` - OpenAI API key (existing)
- `get_option('wecoza_linear_api_key')` - Linear API key (added to SettingsPage - DONE)
- html2canvas JS library (CDN or bundled)

## UI Design

### Floating Action Button (FAB)

- Fixed position: bottom-right corner
- Speech bubble icon
- Phoenix button styling where possible
- Visible to all logged-in users on all frontend pages
- Mobile-friendly: ensure it doesn't cover critical content

### Modal (on FAB click)

1. **Category selector** - Three pill buttons: Bug Report | Feature Request | Comment
2. **Feedback textarea** - Placeholder: "Describe what you'd like to report..."
3. **Context banner** - Muted text: "You're on: [Page Title] ([shortcode])" so user knows context is captured
4. **Screenshot thumbnail** - Auto-captured via html2canvas when modal opens, shown as preview
5. **Submit button**
6. **AI follow-up area** - Hidden by default. Shows AI question + response textarea when feedback is vague. Up to 3 rounds max, but clear feedback goes straight through (0 rounds).

### Notifications

- **Success toast:** "Feedback submitted, thank you!"
- **Error toast:** "Failed to submit feedback. Please try again." (with details if applicable)

## Data Flow

```
User clicks FAB
  → html2canvas captures screenshot
    - Client-side compression: max 1280px wide, JPEG 60% quality (~100-200KB)
  → Page context auto-collected
  → Modal opens

User selects category → Types feedback → Submits

AJAX POST → FeedbackController
  → Save to local PostgreSQL table first (safety net - never lose feedback)
  → AIFeedbackService (gpt-4o-mini via OpenAI API)
    - Input: feedback text, category, page context, curated module schema
    - Output: { is_clear: bool, follow_up: string }
    - FALLBACK: If OpenAI is unreachable/errors, skip vagueness check
      and submit raw feedback directly to Linear

  If vague (max 3 rounds, most feedback passes in 0-1):
    → Return follow-up question to user
    → User refines, resubmits
    → AI re-evaluates with full conversation history

  When clear OR max 3 rounds OR OpenAI fallback:
    → AI generates: clean title, priority suggestion, structured markdown body
    → (If AI unavailable, use raw feedback as title/body)

  → LinearIntegrationService
    - Creates issue in Linear "UAT Feedback" project
    - Attaches screenshot as file
    - Labels: category (Bug/Feature/Comment) + module (Agents/Learners/etc.) + "UAT Feedback"
    - FALLBACK: If Linear fails, mark local DB record as "pending_sync"
      for retry via WP cron job

  → Update local DB record with Linear issue ID and status
  → Return success → Toast notification
```

## Auto-Captured Page Context

Collected automatically via JavaScript, sent with every submission:

| Field | Source |
|-------|--------|
| URL | `window.location.href` |
| Page title | `document.title` |
| Shortcode | `data-wecoza-shortcode` attribute on shortcode wrapper |
| URL parameters | Parsed from URL (entity IDs like agent_id, class_id) |
| Current user | Localized via `wp_localize_script()` |
| Browser | `navigator.userAgent` |
| Viewport | `window.innerWidth` x `window.innerHeight` |
| Timestamp | ISO-8601 from JS |
| Screenshot | html2canvas capture (base64 JPEG, max 1280px, 60% quality) |

## Local Persistence (Safety Net)

### PostgreSQL Table: `feedback_submissions`

All feedback is saved locally BEFORE pushing to Linear. Ensures no feedback is ever lost.

```sql
CREATE TABLE feedback_submissions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL,          -- bug_report, feature_request, comment
    feedback_text TEXT NOT NULL,
    ai_conversation JSONB,                  -- Full AI follow-up history
    ai_generated_title VARCHAR(500),
    ai_suggested_priority VARCHAR(20),
    page_url TEXT,
    page_title VARCHAR(500),
    shortcode VARCHAR(255),
    url_params JSONB,
    browser_info VARCHAR(500),
    viewport VARCHAR(50),
    screenshot_path VARCHAR(500),           -- Server path to saved screenshot file
    linear_issue_id VARCHAR(100),           -- Linear issue ID once synced
    linear_issue_url VARCHAR(500),          -- Linear issue URL
    sync_status VARCHAR(20) DEFAULT 'pending', -- pending, synced, failed
    sync_attempts INTEGER DEFAULT 0,
    sync_error TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_feedback_sync_status ON feedback_submissions(sync_status);
CREATE INDEX idx_feedback_user ON feedback_submissions(user_id);
CREATE INDEX idx_feedback_created ON feedback_submissions(created_at);
```

### Retry Mechanism

- WP cron job runs every 15 minutes
- Picks up records with `sync_status = 'failed'` and `sync_attempts < 5`
- Retries Linear API call with exponential backoff
- After 5 failures, marks as `permanently_failed` and logs for manual review

## AI Feedback Service

### Vagueness Check

- **Model:** gpt-4o-mini (fast, cheap)
- **API:** OpenAI chat completions via `wp_remote_post()`
- **API key:** `get_option('wecoza_openai_api_key')`
- **Max rounds:** 3 follow-up questions, but clear feedback passes through immediately (0 rounds)
- **Fallback:** If OpenAI API is down or errors, skip AI entirely and submit raw feedback

### Schema-Aware Context (Option B - Curated Summaries)

AI prompt includes a curated, human-readable schema summary for the current module:
- Module detected from shortcode/URL
- `SchemaContext.php` maps each module to key tables and fields
- Example: "Agents module: agents table has name, email, cell_number, tel_number, alt_cell_number, status, company_id"
- Lightweight, easy to maintain, token-efficient
- Future upgrade path: OpenAI Vector Store if schema grows significantly

### Vagueness Rules

- Bug reports need: what happened + what was expected
- Feature requests need: what they want + why
- Feedback under ~10 chars or with no actionable content triggers follow-up
- Phrases like "fix this", "broken", "doesn't work" without detail trigger follow-up

### Enrichment on Submit

Before creating Linear issue, AI also:
- Generates a clean issue title from raw feedback
- Suggests priority (Urgent / High / Medium / Low)
- Formats body as structured markdown

## Linear Integration

### Issue Structure

```markdown
Title: [AI-generated clean title]

## Feedback
[User's original text + follow-up responses]

## Category
Bug Report | Feature Request | Comment

## Page Context
- **Page:** [page title]
- **URL:** [full URL]
- **Shortcode:** [detected shortcode]
- **User:** [email]
- **Browser:** [user agent]
- **Timestamp:** [ISO-8601]

## AI Analysis
- **Priority suggestion:** [Urgent/High/Medium/Low]
- **Module:** [Agents/Learners/Classes/Clients]
- **Relevant schema:** [key fields referenced]
```

### Labels

Applied automatically:
- Category: `Bug`, `Feature Request`, or `Comment`
- Module: `Agents`, `Learners`, `Classes`, `Clients` (detected from page)
- `UAT Feedback` on all issues

### Screenshot

Attached as file attachment on the Linear issue.

### API

- Linear REST API via `wp_remote_post()`
- API key: `get_option('wecoza_linear_api_key')`
- Target: Single "UAT Feedback" project
- Retry on failure with exponential backoff (via cron)

## Shortcode Detection

To detect which shortcode is on the page, shortcode output wrappers need a `data-wecoza-shortcode` attribute:

```html
<div data-wecoza-shortcode="wecoza_display_agents" data-wecoza-module="agents">
  <!-- shortcode content -->
</div>
```

JS reads this attribute to include in context payload.

## Security

- AJAX protected by nonce via `AjaxSecurity::requireNonce()`
- OpenAI/Linear API keys server-side only (never exposed to client)
- Screenshot compressed client-side (max 1280px, JPEG 60%), validated server-side (size limit, MIME check)
- User input sanitized via `wecoza_sanitize_value()`
- All logged-in users can submit (internal app, all users expected to give feedback)
- All data handling is internal only - only company personnel have access to Linear

## Files Modified

- `src/Settings/SettingsPage.php` - Added `wecoza_linear_api_key` option (DONE)

## Files to Create

- `src/Feedback/Controllers/FeedbackController.php`
- `src/Feedback/Services/AIFeedbackService.php`
- `src/Feedback/Services/LinearIntegrationService.php`
- `src/Feedback/Services/FeedbackSyncService.php` - Cron retry for failed Linear syncs
- `src/Feedback/Shortcodes/FeedbackWidgetShortcode.php`
- `src/Feedback/Support/SchemaContext.php` - Curated module-to-schema mapping
- `src/Feedback/Repositories/FeedbackRepository.php` - Local DB CRUD
- `views/feedback/widget.view.php`
- `assets/js/feedback/feedback-widget.js`
- `schema/feedback_submissions.sql` - PostgreSQL table DDL
- CSS additions to `ydcoza-styles.css` (FAB positioning, modal tweaks)

## Registration

- Widget injected globally on all frontend pages via `wp_footer` hook
- AJAX actions registered in `wecoza-core.php`
- Namespace `WeCoza\Feedback\` registered in PSR-4 autoloader
- WP cron event for retry sync: `wecoza_feedback_retry_sync` (every 15 min)
