# External Integrations

**Analysis Date:** 2026-02-02

## APIs & External Services

**None Detected:**
- No external API integrations (Stripe, Slack, etc.)
- No SDKs beyond WordPress core

## Data Storage

**Databases:**
- PostgreSQL (managed)
  - Host: `102.141.145.117`
  - Port: `5432`
  - Database: `wecoza_db`
  - User: `John`
  - Connection: Singleton via `WeCoza\Core\Database\PostgresConnection::getInstance()`
  - Client: PDO with lazy loading (connects on first query, not instantiation)
  - Features: SSL support (sslmode: prefer), transaction support, prepared statements

**File Storage:**
- Local filesystem only via WordPress uploads
  - Portfolio files: Learner LP completion portfolios uploaded to `wp-content/uploads/`
  - URL accessed via `wp_upload_dir()['baseurl']` passed to frontend as `uploads_url`

**Caching:**
- WordPress transients (via WP object cache)
  - Configuration cache: 2 hours expiration (`config` group)
  - Query cache: 30 minutes expiration (`db_queries` group)
  - Default: 1 hour expiration
  - Implementation: `wecoza_config()` function caches config files in static variable

## Authentication & Identity

**Auth Provider:**
- WordPress native authentication
  - Implementation: Entire plugin requires WordPress login
  - Capability check: `manage_learners` capability (admin-only) for PII access
  - Nonce protection: `learners_nonce_action` for AJAX requests

**User Roles:**
- Administrator: Full access including learner PII
- Custom capability `manage_learners`: Assigned to administrators only on plugin activation

## Monitoring & Observability

**Error Tracking:**
- None - errors logged to PHP error log when `WP_DEBUG` enabled
- Connection failures: Logged to `error_log()` with context

**Logs:**
- PHP error log - All errors logged via `error_log()` with `[WeCoza Core]` prefix
- Database queries: Logged when `WP_DEBUG` enabled (SQL + parameters)
- Plugin events: Activation, deactivation via `do_action()` hooks

## CI/CD & Deployment

**Hosting:**
- DigitalOcean Managed PostgreSQL - Database infrastructure
- WordPress hosting - Plugin runs as standard WP plugin

**CI Pipeline:**
- None detected - Manual deployment via plugin installation

**Version Control:**
- Git repository - Located at `.git/` in plugin root
- Main branch for releases, feature branches for development

## Environment Configuration

**Required env vars (via WordPress Options):**
- `wecoza_postgres_password` - Critical (no default, warned if missing)
- `wecoza_postgres_host` - Defaults to `102.141.145.117`
- `wecoza_postgres_port` - Defaults to `5432`
- `wecoza_postgres_dbname` - Defaults to `wecoza_db`
- `wecoza_postgres_user` - Defaults to `John`

**Secrets location:**
- WordPress Options table (stored in database)
- Password NOT stored in code or config files
- Warning displayed in admin if password not configured

## Webhooks & Callbacks

**Incoming:**
- No external webhooks received

**Outgoing:**
- Custom WordPress action hooks available:
  - `wecoza_core_loaded` - Fired after plugin initialization (for dependent plugins)
  - `wecoza_core_activated` - Fired on plugin activation
  - `wecoza_core_deactivated` - Fired on plugin deactivation
- AJAX endpoints for learner/class management via `admin-ajax.php`

## Module Communication

**Learners Module Integrations:**
- `ProgressionService` handles LP assignment, tracking learner progress through Learning Programmes
- `PortfolioUploadService` manages learner portfolio file uploads
- AJAX via `LearnerAjaxHandlers.php` for frontend communication

**Classes Module Integrations:**
- `ScheduleService` manages class scheduling
- `UploadService` handles file uploads
- `FormDataProcessor` handles class form submissions

## Frontend Data Transfer

**AJAX Localization:**
- Via `wp_localize_script('wecoza-learners-app', 'WeCozaLearners', [...])`
- Provides frontend JavaScript with:
  - `ajax_url` - WordPress AJAX endpoint
  - `nonce` - CSRF token for AJAX requests
  - `plugin_url` - Plugin base URL
  - `uploads_url` - WordPress uploads directory
  - `home_url` - Site home URL
  - Shortcode URLs for navigation

---

*Integration audit: 2026-02-02*
