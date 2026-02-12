# WeCoza Core

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://php.net/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-Required-336791.svg)](https://www.postgresql.org/)
[![License](https://img.shields.io/badge/License-Proprietary-red.svg)]()

Unified WordPress plugin for the WeCoza learning management system. Provides PostgreSQL database connectivity, MVC architecture, and all domain modules: Agents, Clients, Classes, Events, and Learners.

## Table of Contents

- [Overview](#overview)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Architecture](#architecture)
- [Modules](#modules)
  - [Agents Module](#agents-module)
  - [Clients Module](#clients-module)
  - [Classes Module](#classes-module)
  - [Events Module](#events-module)
  - [Learners Module](#learners-module)
  - [Settings Module](#settings-module)
  - [Shortcode Inspector](#shortcode-inspector)
- [Shortcodes](#shortcodes)
- [AJAX Endpoints](#ajax-endpoints)
- [Helper Functions](#helper-functions)
- [Security](#security)
- [Hooks & Actions](#hooks--actions)
- [Cron Jobs](#cron-jobs)
- [WP-CLI Commands](#wp-cli-commands)
- [Database Schema](#database-schema)
- [Testing](#testing)
- [Debugging](#debugging)
- [Development](#development)
- [License](#license)

## Overview

WeCoza Core is the single, unified WordPress plugin that powers the entire WeCoza learning management system. All domain modules (previously separate plugins) are consolidated here:

- **PostgreSQL Database Layer** - Full PostgreSQL support via PDO with lazy-loaded singleton connection
- **MVC Architecture** - Clean separation with BaseController, BaseModel, and BaseRepository abstractions
- **Agents Module** - Agent (facilitator) management with metadata, notes, and absence tracking
- **Clients Module** - Client organisation management with locations, sites, and hierarchical sub-sites
- **Classes Module** - Training class scheduling, QA visits, public holidays, and agent assignments
- **Events Module** - Event task tracking, material delivery management, AI-powered summaries, and email notifications
- **Learners Module** - Learner records with LP progression tracking, portfolio uploads, and hours tracking
- **Action Scheduler** - Async processing for email notifications and AI enrichment

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.0 or higher |
| WordPress | 6.0 or higher |
| PostgreSQL | 12.0 or higher (recommended) |
| PHP Extensions | `pdo_pgsql` |

## Installation

### Manual Installation

1. Clone the repository to your WordPress plugins directory:

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/yourdesigncoza/wecoza-core.git
```

2. Ensure the PostgreSQL PDO extension is installed:

```bash
# Ubuntu/Debian
sudo apt-get install php-pgsql

# CentOS/RHEL
sudo yum install php-pgsql

# Verify installation
php -m | grep pdo_pgsql
```

3. Activate the plugin through WordPress admin or WP-CLI:

```bash
wp plugin activate wecoza-core
```

### Composer (Optional)

If using Composer for autoloading:

```bash
composer dump-autoload --optimize
```

## Configuration

### Database Connection

Set the PostgreSQL password in WordPress options:

```php
// Via wp-config.php (recommended for security)
define('WECOZA_POSTGRES_PASSWORD', 'your_secure_password');

// Or via WordPress option
update_option('wecoza_postgres_password', 'your_secure_password');
```

Database configuration is stored in `config/app.php`:

```php
return [
    'database' => [
        'use_postgresql' => true,
        'sslmode' => 'prefer',
        'defaults' => [
            'host' => 'your-host',
            'port' => '5432',
            'dbname' => 'wecoza_db',
            'user' => 'your_user',
        ],
    ],
];
```

### Environment Settings

Enable debugging in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

## Architecture

```
wecoza-core/
├── core/                          # Framework abstractions
│   ├── Abstract/                  # Base classes
│   │   ├── BaseController.php
│   │   ├── BaseModel.php
│   │   └── BaseRepository.php
│   ├── Database/                  # Database layer
│   │   └── PostgresConnection.php # Singleton, lazy-loaded PDO
│   └── Helpers/                   # Utility functions
│       ├── functions.php          # Global helper functions
│       └── AjaxSecurity.php       # CSRF, capability, sanitization
├── src/                           # Application modules
│   ├── Agents/                    # Agent (facilitator) management
│   │   ├── Ajax/                  # AgentsAjaxHandlers
│   │   ├── Controllers/           # AgentsController
│   │   ├── Helpers/               # FormHelpers
│   │   ├── Models/                # AgentModel (standalone)
│   │   ├── Repositories/          # AgentRepository (4 tables)
│   │   └── Services/
│   ├── Clients/                   # Client organisation management
│   │   ├── Ajax/                  # ClientAjaxHandlers
│   │   ├── Controllers/           # ClientsController, LocationsController
│   │   ├── Helpers/
│   │   ├── Models/
│   │   └── Repositories/
│   ├── Classes/                   # Training class management
│   │   ├── Controllers/           # ClassController, ClassAjaxController,
│   │   │                          # QAController, PublicHolidaysController
│   │   ├── Models/                # ClassModel, QAModel, QAVisitModel
│   │   ├── Repositories/
│   │   └── Services/              # ScheduleService, UploadService,
│   │                              # FormDataProcessor
│   ├── Events/                    # Event tracking & notifications
│   │   ├── Admin/                 # SettingsPage (WP admin)
│   │   ├── CLI/                   # AISummaryStatusCommand
│   │   ├── Controllers/           # TaskController, MaterialTrackingController
│   │   ├── DTOs/                  # Data transfer objects
│   │   ├── Enums/                 # Status enumerations
│   │   ├── Models/
│   │   ├── Repositories/
│   │   ├── Services/              # EventDispatcher, NotificationProcessor,
│   │   │                          # NotificationEnricher, NotificationEmailer,
│   │   │                          # NotificationDashboardService,
│   │   │                          # MaterialNotificationService,
│   │   │                          # AISummaryService,
│   │   │                          # MaterialTrackingDashboardService
│   │   ├── Shortcodes/            # EventTasksShortcode,
│   │   │                          # MaterialTrackingShortcode,
│   │   │                          # AISummaryShortcode
│   │   ├── Support/
│   │   └── Views/
│   ├── Learners/                  # Learner management
│   │   ├── Ajax/                  # LearnerAjaxHandlers
│   │   ├── Controllers/           # LearnerController
│   │   ├── Models/                # LearnerModel
│   │   ├── Repositories/          # LearnerRepository
│   │   ├── Services/              # ProgressionService, PortfolioUploadService
│   │   └── Shortcodes/
│   ├── Settings/                  # Plugin settings page
│   │   └── SettingsPage.php
│   └── ShortcodeInspector/        # Debug tool (Tools > WeCoza Shortcodes)
│       └── ShortcodeInspector.php
├── views/                         # PHP templates
│   ├── agents/                    # Agent views (display, components)
│   ├── classes/                   # Class views
│   ├── clients/                   # Client views (display, components)
│   ├── components/                # Shared reusable partials
│   ├── events/                    # Event views (tasks, material, AI summary)
│   └── learners/                  # Learner views
├── assets/                        # Frontend assets
│   ├── css/
│   └── js/
│       ├── agents/                # Agent management JS
│       ├── classes/               # 10+ JS files for class management
│       ├── clients/               # Client management JS
│       └── learners/              # 4 JS files for learner management
├── config/                        # Configuration files
│   └── app.php                    # Database, cache, path, AJAX config
├── schema/                        # Database schema backups
│   └── migrations/
├── tests/                         # Integration & security tests
│   ├── integration/               # Feature parity tests
│   ├── Events/                    # Event module tests
│   └── security-test.php          # Security audit test
├── vendor/                        # Composer dependencies
│   └── woocommerce/
│       └── action-scheduler/      # Async job processing
└── wecoza-core.php                # Plugin entry point
```

### Namespaces (PSR-4)

| Namespace | Directory |
|-----------|-----------|
| `WeCoza\Core\` | `core/` |
| `WeCoza\Agents\` | `src/Agents/` |
| `WeCoza\Clients\` | `src/Clients/` |
| `WeCoza\Classes\` | `src/Classes/` |
| `WeCoza\Events\` | `src/Events/` |
| `WeCoza\Learners\` | `src/Learners/` |
| `WeCoza\Settings\` | `src/Settings/` |
| `WeCoza\ShortcodeInspector\` | `src/ShortcodeInspector/` |

## Modules

### Agents Module

Manages agent (facilitator) records including personal details, banking, qualifications, and SACE registration.

**Features:**
- CRUD operations for agent records
- Metadata key-value store (`agent_meta` table)
- Agent notes tracking
- Absence recording and reporting
- Preferred working areas management

**Key Classes:**
- `AgentsController` - Shortcode registration, form rendering
- `AgentModel` - Standalone data model (not extending BaseModel) with validation
- `AgentRepository` - Database operations across 4 tables (agents, agent_meta, agent_notes, agent_absences)
- `AgentsAjaxHandlers` - Pagination and delete endpoints
- `FormHelpers` - Agent form field rendering utilities

### Clients Module

Manages client organisations with locations, head sites, and sub-site hierarchies.

**Features:**
- Client CRUD with branch/main client relationships
- Location management with duplicate detection
- Hierarchical site structure (head sites and sub-sites)
- Client search and export
- Main client grouping

**Key Classes:**
- `ClientsController` - Client shortcodes and form rendering
- `LocationsController` - Location capture, listing, and editing
- `ClientAjaxHandlers` - 15 AJAX endpoints for clients, locations, and sites

### Classes Module

Manages training classes, schedules, QA visits, and public holidays.

**Features:**
- Class creation and scheduling with agent assignment
- Agent replacement tracking
- QA visit recording and analytics
- Public holiday awareness for schedule calculations
- Class notes and file attachments
- Calendar event integration
- Stop/restart date management

**Key Classes:**
- `ClassController` - Class management and shortcodes
- `ClassAjaxController` - Class CRUD, calendar, notes, attachments AJAX
- `QAController` - QA visit CRUD, analytics, and reporting
- `PublicHolidaysController` - Public holiday data provider
- `ScheduleService` - Schedule calculations

### Events Module

Handles event-related tasks, material delivery tracking, AI-powered summaries, and email notifications.

**Features:**
- Event task management with status tracking
- Material delivery monitoring with colour-coded urgency (green/orange/red)
- AI-powered event summarisation via Action Scheduler
- Email notifications with async processing pipeline
- Notification dashboard with viewed/acknowledged states
- Admin settings page for notification configuration
- WP-CLI command for AI summary status

**Notification Pipeline:**
1. `EventDispatcher` captures class/learner changes as events
2. `NotificationProcessor` (cron) batches pending events
3. `NotificationEnricher` adds AI context per event
4. `NotificationEmailer` delivers emails per recipient

**Key Classes:**
- `TaskController` - Event task AJAX handlers
- `MaterialTrackingController` - Material delivery status
- `MaterialNotificationService` - Automated material deadline notifications
- `AISummaryService` - AI-generated event summaries
- `NotificationDashboardService` - Dashboard data retrieval
- `EventDispatcher` - Event capture and scheduling
- `NotificationProcessor` - Batch processing engine

### Learners Module

Manages learner records including personal information, qualifications, employment, and LP progression.

**Features:**
- CRUD operations for learner records
- LP (Learning Programme) progression tracking
- Portfolio upload management
- Hours tracking (trained, present, absent)
- Progress calculation from hours vs product duration
- Single active LP enforcement per learner

**LP Progression Statuses:** `in_progress`, `completed`, `on_hold`

**Key Classes:**
- `LearnerController` - Request handling and shortcode registration
- `LearnerModel` - Data model with validation
- `LearnerRepository` - Database operations with column whitelisting
- `ProgressionService` - `startLearnerProgression()`, `markLPComplete()`, `logHours()`
- `PortfolioUploadService` - File upload handling for LP completion

### Settings Module

Provides the WeCoza Settings page under the WordPress Settings menu.

### Shortcode Inspector

Debug utility available at **Tools > WeCoza Shortcodes** in WordPress admin. Lists all registered shortcodes and their handlers.

## Shortcodes

### Agents

| Shortcode | Description |
|-----------|-------------|
| `[wecoza_capture_agents]` | Agent create/edit form |
| `[wecoza_display_agents]` | Paginated agent list |
| `[wecoza_single_agent]` | Individual agent detail view |

### Clients

| Shortcode | Description |
|-----------|-------------|
| `[wecoza_capture_clients]` | Client create form |
| `[wecoza_display_clients]` | Client list view |
| `[wecoza_update_clients]` | Client edit form |
| `[wecoza_locations_capture]` | Location create form |
| `[wecoza_locations_list]` | Location list view |
| `[wecoza_locations_edit]` | Location edit form |

### Classes

| Shortcode | Description |
|-----------|-------------|
| `[wecoza_capture_class]` | Class create/edit form |
| `[wecoza_display_classes]` | Class list view |
| `[wecoza_display_single_class]` | Single class detail view |

### Events

| Shortcode | Description |
|-----------|-------------|
| `[wecoza_event_tasks]` | Event task management interface |
| `[wecoza_material_tracking]` | Material delivery tracking dashboard |
| `[wecoza_insert_update_ai_summary]` | AI-generated event summary display |

### Learners

| Shortcode | Description |
|-----------|-------------|
| `[wecoza_display_learners]` | Paginated learner list |
| `[wecoza_learners_form]` | Learner registration form |
| `[wecoza_single_learner_display]` | Individual learner detail view |
| `[wecoza_learners_update_form]` | Learner edit form |

## AJAX Endpoints

All endpoints require authentication (`wp_ajax_` prefix). Endpoints marked with `*` also have `nopriv` variants.

### Agents
| Action | Handler |
|--------|---------|
| `wecoza_agents_paginate` | Paginated agent list |
| `wecoza_agents_delete` | Delete agent |

### Clients
| Action | Handler |
|--------|---------|
| `wecoza_save_client` | Create/update client |
| `wecoza_get_client` | Get single client |
| `wecoza_get_client_details` | Get client with full details |
| `wecoza_delete_client` | Delete client |
| `wecoza_search_clients` | Search clients |
| `wecoza_get_branch_clients` | Get branch clients |
| `wecoza_export_clients` | Export clients |
| `wecoza_get_main_clients` | Get main/parent clients |
| `wecoza_get_locations` | List locations |
| `wecoza_save_location` | Create/update location |
| `wecoza_check_location_duplicates` | Check for duplicate locations |
| `wecoza_save_sub_site` | Create sub-site |
| `wecoza_get_head_sites` | List head sites |
| `wecoza_get_sub_sites` | List sub-sites |
| `wecoza_delete_sub_site` | Delete sub-site |
| `wecoza_get_sites_hierarchy` | Get full site hierarchy |

### Classes
| Action | Handler |
|--------|---------|
| `save_class` | Create/update class |
| `delete_class` | Delete class |
| `get_calendar_events` | Calendar event data |
| `get_class_subjects` | Class subjects `*` |
| `get_class_notes` | Get class notes |
| `save_class_note` | Create class note |
| `delete_class_note` | Delete class note |
| `upload_attachment` | Upload file attachment |
| `get_public_holidays` | Public holiday data `*` |

### QA Visits
| Action | Handler |
|--------|---------|
| `get_qa_analytics` | QA analytics data `*` |
| `get_qa_summary` | QA summary `*` |
| `get_qa_visits` | List QA visits `*` |
| `create_qa_visit` | Create QA visit `*` |
| `export_qa_reports` | Export QA reports `*` |
| `delete_qa_report` | Delete QA report `*` |
| `get_class_qa_data` | Class QA data `*` |
| `submit_qa_question` | Submit QA question `*` |

### Events
| Action | Handler |
|--------|---------|
| `wecoza_events_task_update` | Update event task |
| `wecoza_mark_material_delivered` | Mark material as delivered |
| `wecoza_mark_notification_viewed` | Mark notification viewed |
| `wecoza_mark_notification_acknowledged` | Mark notification acknowledged |
| `wecoza_send_test_notification` | Send test notification (admin) |

### Learners
| Action | Handler |
|--------|---------|
| `wecoza_get_learner` | Get single learner |
| `wecoza_get_learners` | List learners |
| `wecoza_update_learner` | Update learner |
| `wecoza_delete_learner` | Delete learner |
| `fetch_learners_data` | DataTables-compatible learner data `*` |
| `fetch_learners_dropdown_data` | Learner dropdown options `*` |
| `update_learner` | Legacy update endpoint |
| `delete_learner` | Legacy delete endpoint |
| `delete_learner_portfolio` | Delete portfolio file |

## Helper Functions

### Database & Configuration

```php
// Get PostgreSQL connection (lazy-loaded singleton)
$db = wecoza_db();

// Load configuration file (cached)
$config = wecoza_config('app');
```

### View Rendering

```php
// Render a view template
wecoza_view('learners/list', ['learners' => $data]);

// Render with return instead of echo
$html = wecoza_view('learners/list', ['learners' => $data], true);

// Render a reusable component
wecoza_component('pagination', ['page' => 1, 'total' => 100]);
```

### Asset URLs

```php
$url = wecoza_asset_url('images/logo.png');
$css = wecoza_css_url('learners-style.css');
$js = wecoza_js_url('learners/learners-app.js');
```

### Paths

```php
$path = wecoza_plugin_path('views/learners/list.php');
$corePath = wecoza_core_path('Abstract/BaseModel.php');
```

### Environment Detection

```php
if (wecoza_is_admin_area()) { ... } // WP admin (excluding AJAX)
if (wecoza_is_ajax()) { ... }       // AJAX request
if (wecoza_is_rest()) { ... }       // REST API request
```

### Utilities

```php
// Debug logging (only when WP_DEBUG is enabled)
wecoza_log('Processing learner data', 'info');
wecoza_log('Something went wrong', 'error');

// Sanitize input values
$email = wecoza_sanitize_value($input, 'email');
$id    = wecoza_sanitize_value($input, 'int');
$date  = wecoza_sanitize_value($input, 'date');
$json  = wecoza_sanitize_value($input, 'json');
$bool  = wecoza_sanitize_value($input, 'bool');

// Dot notation array access
$value = wecoza_array_get($array, 'nested.key.value', 'default');

// Case conversion
$camelCase = wecoza_snake_to_camel('my_variable_name'); // myVariableName
$snakeCase = wecoza_camel_to_snake('myVariableName');   // my_variable_name
```

## Security

### Authentication

The entire WeCoza application requires WordPress authentication. Unauthenticated users cannot access any pages or AJAX endpoints.

### Capability-Based Access Control

| Capability | Purpose | Default Role |
|------------|---------|-------------|
| `manage_learners` | Access to learner PII data | Administrator |
| `view_material_tracking` | View material tracking dashboard | Administrator |
| `manage_material_tracking` | Modify material delivery status | Administrator |
| `manage_wecoza_clients` | Client and location management | Administrator |

### SQL Injection Prevention

Repositories implement column whitelisting to prevent SQL injection via column name manipulation:

```php
class LearnerRepository extends BaseRepository
{
    protected function getAllowedOrderColumns(): array
    {
        return ['id', 'first_name', 'surname', 'created_at'];
    }

    protected function getAllowedFilterColumns(): array
    {
        return ['id', 'email_address', 'employer_id'];
    }

    protected function getAllowedInsertColumns(): array { ... }
    protected function getAllowedUpdateColumns(): array { ... }
}
```

### CSRF Protection

All AJAX handlers require nonce verification:

```php
AjaxSecurity::requireNonce('learners_nonce_action');
```

### Input Sanitization

```php
use WeCoza\Core\Helpers\AjaxSecurity;

AjaxSecurity::verifyNonce('action_name');
AjaxSecurity::checkCapability('manage_learners');

$clean = AjaxSecurity::sanitizeArray($_POST, [
    'id'    => 'int',
    'email' => 'email',
    'name'  => 'string',
]);

AjaxSecurity::validateUploadedFile($file, ['pdf', 'doc', 'docx']);
```

## Hooks & Actions

### Plugin Lifecycle

```php
// Fired when plugin is fully loaded (priority 5 on plugins_loaded)
do_action('wecoza_core_loaded');

// Fired on plugin activation
do_action('wecoza_core_activated');

// Fired on plugin deactivation
do_action('wecoza_core_deactivated');
```

### Extending the Plugin

Dependent code should hook into `wecoza_core_loaded` or use `plugins_loaded` at priority 10+:

```php
add_action('wecoza_core_loaded', function() {
    // WeCoza Core classes and functions are now available
}, 10);
```

### Action Scheduler Hooks

| Hook | Purpose |
|------|---------|
| `wecoza_process_event` | AI enrichment per event (async) |
| `wecoza_send_notification_email` | Email delivery per recipient (async) |

## Cron Jobs

| Hook | Frequency | Description |
|------|-----------|-------------|
| `wecoza_material_notifications_check` | Daily | Check material delivery deadlines, send orange (7-day) and red (5-day) notifications |
| `wecoza_process_notifications` | Hourly | Batch process pending notification events |

## WP-CLI Commands

```bash
# Test PostgreSQL connection
wp wecoza test-db

# Show plugin version
wp wecoza version

# AI Summary status (Events module)
wp wecoza ai-summary status
```

## Database Schema

All tables are in PostgreSQL. Connection via `wecoza_db()` singleton.

### Agent Tables

| Table | Purpose |
|-------|---------|
| `agents` | Core agent records (personal info, banking, SACE, qualifications) |
| `agent_meta` | Key-value metadata store per agent |
| `agent_notes` | Timestamped notes per agent |
| `agent_absences` | Absence records with dates and reasons |

### Events Tables

| Table | Purpose |
|-------|---------|
| `class_events` | Event tracking for class/learner changes, notification state |

### Other Tables

Learner, class, client, and location tables are managed by the respective modules. See `schema/` for full backup schemas.

## Testing

Test files are in the `tests/` directory:

```bash
# Security audit
wp eval-file tests/security-test.php

# Feature parity tests (agents migration)
wp eval-file tests/integration/agents-feature-parity.php

# Feature parity tests (clients migration)
wp eval-file tests/integration/clients-feature-parity.php

# Events module tests
wp eval-file tests/Events/MaterialTrackingTest.php
wp eval-file tests/Events/EmailNotificationTest.php
wp eval-file tests/Events/TaskManagementTest.php
wp eval-file tests/Events/AISummarizationTest.php
wp eval-file tests/Events/PIIDetectorTest.php
```

## Debugging

### Debug Log Location

```
/wp-content/debug.log
```

### Enabling Debug Mode

In `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Using the Logger

```php
wecoza_log('Information message', 'info');
wecoza_log('Warning message', 'warning');
wecoza_log('Error message', 'error');
// Only outputs when WP_DEBUG is true
```

### Database Connection Test

```bash
wp wecoza test-db
```

```php
$db = wecoza_db();
if ($db->testConnection()) {
    echo 'Connected! Version: ' . $db->getVersion();
}
```

### Shortcode Inspector

Navigate to **Tools > WeCoza Shortcodes** in WordPress admin to see all registered shortcodes and verify they are loading correctly.

## Development

### Plugin Loading Priority

WeCoza Core loads at priority `5` on `plugins_loaded`. Dependent plugins should use priority `10` or higher.

### View File Extensions

Views support both naming conventions:
- `.view.php` (preferred for newer modules)
- `.php` (legacy)

### Adding New Modules

1. Create directory structure under `src/YourModule/`
2. Add namespace mapping to `wecoza-core.php` autoloader
3. Create Controller, Model, Repository, and Service classes extending the base abstractions
4. Register controllers in the `plugins_loaded` callback in `wecoza-core.php`
5. Add views to `views/yourmodule/`
6. Add JS assets to `assets/js/yourmodule/`

### Code Standards

- PHP 8.0+ features (typed properties, match expressions, named arguments, union types)
- PSR-4 autoloading with custom `spl_autoload_register`
- WordPress Coding Standards for hooks and filters
- Column whitelisting for all repository database operations
- Nonce verification for all AJAX handlers

### Action Scheduler

The plugin bundles [Action Scheduler](https://actionscheduler.org/) for async processing:

```php
// Enqueue an async job
as_enqueue_async_action('wecoza_process_event', ['event_id' => $id], 'wecoza-notifications');
```

Configuration tuning:
- Queue runner time limit: 60 seconds (default 30)
- Batch size: 50 items per run

## License

This plugin is proprietary software. All rights reserved.

**Author:** [YourDesign.co.za](https://yourdesign.co.za/)

---

For support or inquiries, please contact [info@yourdesign.co.za](mailto:info@yourdesign.co.za)
