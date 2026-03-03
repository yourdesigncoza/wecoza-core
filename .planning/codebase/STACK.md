# Technology Stack

**Analysis Date:** 2026-03-03

## Languages

**Primary:**
- PHP 8.0+ - Backend logic, controllers, models, services, repositories
- JavaScript (Vanilla) - Client-side UI interactions, AJAX handlers, form management, calendar integration

**Secondary:**
- SQL (PostgreSQL dialect) - Database queries via PDO prepared statements, schema definitions

## Runtime

**Environment:**
- WordPress 6.0+ - Application framework and plugin environment
- PHP 8.0+ - Required minimum version

**Package Manager:**
- Composer - PHP dependency management
- Lockfile: `composer.lock` present

## Frameworks

**Core:**
- WordPress 6.0+ - Plugin environment and core functions (add_action, wp_enqueue_script, etc.)
- woocommerce/action-scheduler 3.9.3 - Scheduled background tasks and recurring jobs

**Frontend Libraries:**
- Chart.js 4.4.0 - Data visualization (CDN: cdn.jsdelivr.net) - Used in `src/Classes/Controllers/QAController.php`
- FullCalendar 6.1.17 - Calendar view for classes and events (CDN: cdn.jsdelivr.net) - Used in `src/Classes/Controllers/ClassController.php`
- HTML2Canvas 1.4.1 - Screenshot/export functionality (CDN: cdnjs.cloudflare.com) - Used in feedback widget
- Google Maps API - Location display and place search (via googleapis.com) - Used in `src/Agents/Controllers/AgentsController.php` and `src/Clients/Controllers/LocationsController.php`
- jQuery - WordPress-bundled library for DOM manipulation

**Architecture:**
- MVC Pattern - Controllers in `src/*/Controllers/`, Models in `src/*/Models/`, Repositories in `src/*/Repositories/`
- Service Layer - Business logic in `src/*/Services/` (e.g., `ProgressionService`, `AISummaryService`, `NotificationDashboardService`)
- PSR-4 Autoloader - Configured in `composer.json` and `wecoza-core.php`

**Testing:**
- PHPUnit - Integration tests in `tests/` directory

**Build/Dev:**
- Composer PSR-4 autoloading with `optimize-autoloader` enabled

## Key Dependencies

**Critical:**
- woocommerce/action-scheduler 3.9.3 - Handles background task scheduling, Action Scheduler for WordPress
- PDO (PHP Data Objects) - Database abstraction layer for PostgreSQL connectivity
- pdo_pgsql (PHP extension) - PostgreSQL driver required

**Infrastructure:**
- None beyond standard PHP extensions

## Configuration

**Environment Variables:**
All configuration stored in WordPress options (wp_options table) rather than .env:

**Database Configuration:**
- `wecoza_postgres_host` - PostgreSQL host (default: 102.141.145.117)
- `wecoza_postgres_port` - Database port (default: 5432)
- `wecoza_postgres_dbname` - Database name (default: wecoza_db)
- `wecoza_postgres_user` - Database user (default: John)
- `wecoza_postgres_password` - Database password (required, no default)

**External API Keys:**
- `wecoza_openai_api_key` - OpenAI API key (sk-* format) for AI services
- `wecoza_openai_api_url` - OpenAI endpoint (default: https://api.openai.com/v1/chat/completions)
- `wecoza_openai_model` - OpenAI model name (default: gpt-4o-mini)
- `wecoza_google_maps_api_key` - Google Maps API key for location services
- `wecoza_trello_api_key` - Trello API key for feedback integration
- `wecoza_trello_api_token` - Trello API token
- `wecoza_trello_board_id` - Trello board ID (supports both short and full 24-char hex format)

**Application Config (config/app.php):**
```php
'database' => [
    'use_postgresql' => true,
    'sslmode' => 'prefer', // 'require' for DigitalOcean Managed Database
    'defaults' => [
        'host' => '102.141.145.117',
        'port' => '5432',
        'dbname' => 'wecoza_db',
        'user' => 'John',
    ],
],
'cache' => [
    'default_expiration' => 3600,
    'groups' => [
        'db_queries' => 1800,
        'config' => 7200,
    ],
]
```

**Build:**
- `composer.json` - Dependency manifest with PSR-4 autoload configuration

## Platform Requirements

**Development:**
- PHP 8.0+ with:
  - pdo_pgsql extension (PostgreSQL driver)
  - Standard PHP extensions (PDO, json, OpenSSL for HTTPS APIs)
- WordPress 6.0+
- PostgreSQL database
- Local file storage for uploads (learner portfolios, etc.)

**Production:**
- PHP 8.0+
- WordPress 6.0+ environment
- PostgreSQL database (DigitalOcean Managed Database or self-hosted)
- SSL/TLS support for database (sslmode configurable)
- Outbound HTTPS connectivity for:
  - OpenAI API (api.openai.com)
  - Trello API (api.trello.com)
  - Google Maps API (maps.googleapis.com)
  - CDN libraries (cdn.jsdelivr.net, cdnjs.cloudflare.com)
- File storage for learner uploads

## Database

**Type:** PostgreSQL (strict requirement, not MySQL-compatible)

**Connection Details:**
- Singleton pattern: `PostgresConnection::getInstance()` in `core/Database/PostgresConnection.php`
- Lazy-loaded connection (defers until first query, not at plugin load)
- PDO with SSL mode support (required for DigitalOcean Managed Database)
- Error mode: PDO::ERRMODE_EXCEPTION
- Default fetch mode: PDO::FETCH_ASSOC

**Key Tables:**
- agents - Agent records
- agent_meta - Agent metadata
- agent_notes - Agent notes
- agent_absences - Agent absence tracking
- class_events - Event/change log tracking
- class_attendance_sessions - Attendance session data
- learner_progressions - Learning programme progress
- WordPress standard tables (posts, postmeta, options, etc.)

---

*Stack analysis: 2026-03-03*
