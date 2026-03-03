# External Integrations

**Analysis Date:** 2026-03-03

## APIs & External Services

**OpenAI:**
- Service: GPT models for AI-powered features
- What it's used for:
  - AI summary generation for class change notifications (`src/Events/Services/AISummaryService.php`)
  - Feedback vagueness detection and follow-up question generation (`src/Feedback/Services/AIFeedbackService.php`)
- SDK/Client: WordPress `wp_remote_post()` for HTTP requests
- Auth: API key stored in `wecoza_openai_api_key` WordPress option
- Models: gpt-4o-mini (default), gpt-4.1, configurable via `wecoza_openai_model` option
- API URL: Configurable via `wecoza_openai_api_url` option (default: https://api.openai.com/v1/chat/completions)
- Timeout: 30 seconds
- Request format: JSON POST with system prompt + user message
- Response handling: JSON decode with error checking

**Google Maps:**
- Service: Location services and place search
- What it's used for:
  - Location search and autocomplete in agent creation/editing (`src/Agents/Controllers/AgentsController.php`)
  - Location display and place search in client location management (`src/Clients/Controllers/LocationsController.php`)
- SDK/Client: Google Maps JavaScript API (enqueued via wp_enqueue_script)
- Auth: API key stored in `wecoza_google_maps_api_key` WordPress option
- Libraries: places, loading=async
- Version: weekly (latest)
- URL Format: `https://maps.googleapis.com/maps/api/js?key=[KEY]&libraries=places&loading=async&v=weekly`

**Trello:**
- Service: Issue/feedback tracking and project management
- What it's used for:
  - Creating feedback cards in Trello board from user feedback (`src/Feedback/Services/TrelloService.php`)
  - Organizing feedback by status (Open, In Progress, Done lists)
- SDK/Client: WordPress `wp_remote_post()` and `wp_remote_get()` for HTTP requests
- Auth: API key in `wecoza_trello_api_key`, API token in `wecoza_trello_api_token`
- Board ID: Stored in `wecoza_trello_board_id` (supports both short format and full 24-char hex)
- Base URL: https://api.trello.com/1
- Timeout: 15 seconds
- Features:
  - Board ID resolution (short to full ID conversion via GET /boards/{id})
  - List management (create/find lists like "Open", "In Progress", "Done")
  - Card creation with descriptions and labels
  - Caching of board structure and member IDs
- Endpoints used: /boards/{id}, /lists, /cards, /members

**CDN Libraries:**
- Chart.js 4.4.0 (cdn.jsdelivr.net) - Data visualization charts
- FullCalendar 6.1.17 (cdn.jsdelivr.net) - Calendar component
- HTML2Canvas 1.4.1 (cdnjs.cloudflare.com) - Screenshot/canvas rendering

## Data Storage

**Databases:**
- PostgreSQL (primary)
  - Host: 102.141.145.117 (DigitalOcean Managed Database)
  - Port: 5432 (default)
  - Database: wecoza_db
  - User: John (default)
  - Connection: Singleton via `PostgresConnection::getInstance()` in `core/Database/PostgresConnection.php`
  - Client: PDO with pdo_pgsql extension
  - Features:
    - Lazy-loaded (defers connection until first query)
    - SSL support (sslmode: prefer for DigitalOcean, configurable in config/app.php)
    - Prepared statements for SQL injection prevention
    - Column whitelisting for filter/sort/insert/update safety
  - Credentials stored in WordPress options:
    - `wecoza_postgres_host`
    - `wecoza_postgres_port`
    - `wecoza_postgres_dbname`
    - `wecoza_postgres_user`
    - `wecoza_postgres_password` (required, no default)

- WordPress Database (secondary - MySQL)
  - Used for: User authentication, WordPress options (plugin settings), postmeta
  - Built-in WordPress functionality

**File Storage:**
- Local filesystem via WordPress uploads
  - Portfolio uploads: Learner LP completion files via `src/Learners/Services/PortfolioUploadService.php`
  - Class uploads: Via `src/Classes/Services/UploadService.php`
  - CSV exports: Regulatory exports via `src/Learners/Ajax/ProgressionAjaxHandlers.php` (streamed to browser)

**Caching:**
- WordPress transients API (object cache)
  - `db_queries` group: 1800 seconds (30 minutes)
  - `config` group: 7200 seconds (2 hours)
  - Default: 3600 seconds (1 hour)
  - Implementation: Static variable caching in `wecoza_config()` function

## Authentication & Identity

**Auth Provider:**
- WordPress native authentication
  - No custom auth system
  - Entire plugin requires WordPress login
  - All endpoints protected by `is_user_logged_in()` checks
  - Sensitive operations require `manage_options` capability

**Capability System:**
- `manage_learners` - Access to learner PII data (admin-only)
- `manage_options` - Admin panel access, API key management
- Capability checking via `current_user_can()` in AJAX handlers

**Nonce Protection:**
- AJAX endpoints require nonce verification
- Nonce handled via `AjaxSecurity::requireNonce()` and `AjaxSecurity::verifyNonce()`
- Sanitization via `AjaxSecurity::sanitizeArray()` for input validation

## Monitoring & Observability

**Error Tracking:**
- WordPress error logging (no external service)
- Logged to: `/opt/lampp/htdocs/wecoza/wp-content/debug.log` (when WP_DEBUG enabled)

**Logs:**
- `wecoza_log($msg, $level)` custom function logs to WordPress debug.log
- Database errors: PDO exceptions logged to PHP error_log
- API errors: Logged with response code and body for debugging

## CI/CD & Deployment

**Hosting:**
- XAMPP/Apache for development
- DigitalOcean Managed Database for PostgreSQL production
- WordPress plugin environment

**CI Pipeline:**
- Not detected (manual deployment)
- Integration tests available in `tests/` directory (PHPUnit-based)

## Environment Configuration

**Required env vars (WordPress options):**
- `wecoza_postgres_password` - **Critical**, no default (warning logged if missing)
- `wecoza_openai_api_key` - Required for AI features (validated with sk-* format)
- `wecoza_google_maps_api_key` - Required for location features
- `wecoza_trello_api_key`, `wecoza_trello_api_token`, `wecoza_trello_board_id` - Required for feedback integration

**Optional configurations:**
- `wecoza_openai_api_url` - Custom OpenAI endpoint (default: https://api.openai.com/v1/chat/completions)
- `wecoza_openai_model` - AI model override (default: gpt-4o-mini)

**Secrets location:**
- WordPress wp_options table (database-stored, not .env files)
- No separate .env file (not applicable to WordPress plugins)
- API key masking: First 4 and last 4 characters visible for validation

## Webhooks & Callbacks

**Incoming:**
- Not detected in codebase

**Outgoing:**
- Trello card creation API calls when feedback is submitted
- OpenAI API POST requests for AI summaries and feedback analysis
- Google Maps Autocomplete API queries for location search
- Custom WordPress action hooks for dependent plugins:
  - `wecoza_core_loaded` - After plugin initialization
  - `wecoza_core_activated` - On plugin activation
  - `wecoza_core_deactivated` - On plugin deactivation

## Rate Limiting & Quotas

**OpenAI:**
- Max retry attempts: 3 (with configurable backoff in `AISummaryService`)
- Timeout: 30 seconds per request
- API rate limit handling: Relies on OpenAI service limits

**Trello:**
- Timeout: 15 seconds per request
- Board/list/member caching to reduce API calls

**Action Scheduler:**
- Handles background job execution via WooCommerce Action Scheduler 3.9.3
- Used for: Delayed notifications, batch processing of class changes

## Module Integration Points

**Learners Module:**
- `src/Learners/Services/ProgressionService` - LP assignment and tracking
- `src/Learners/Services/PortfolioUploadService` - File upload handling
- `src/Learners/Ajax/ProgressionAjaxHandlers` - Frontend communication

**Classes Module:**
- `src/Classes/Services/ScheduleService` - Class scheduling logic
- `src/Classes/Services/UploadService` - File uploads for classes
- `src/Classes/Services/FormDataProcessor` - Form submission handling
- Class event triggers send data to OpenAI for AI summaries

**Events/Feedback Module:**
- `src/Events/Services/AISummaryService` - AI summary generation
- `src/Feedback/Services/AIFeedbackService` - Feedback quality analysis
- `src/Feedback/Services/TrelloService` - Trello integration

---

*Integration audit: 2026-03-03*
