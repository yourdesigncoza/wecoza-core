# WeCoza Core

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://php.net/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-Required-336791.svg)](https://www.postgresql.org/)
[![License](https://img.shields.io/badge/License-Proprietary-red.svg)]()

Core infrastructure plugin for WeCoza - providing PostgreSQL database connectivity, MVC architecture, and shared utilities for learner and class management.

## Table of Contents

- [Overview](#overview)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Architecture](#architecture)
- [Modules](#modules)
  - [Learners Module](#learners-module)
  - [Classes Module](#classes-module)
  - [Events Module](#events-module)
- [Shortcodes](#shortcodes)
- [Helper Functions](#helper-functions)
- [Security](#security)
- [Hooks & Actions](#hooks--actions)
- [WP-CLI Commands](#wp-cli-commands)
- [Debugging](#debugging)
- [Development](#development)
- [License](#license)

## Overview

WeCoza Core is a WordPress plugin that serves as the foundational infrastructure for the WeCoza learning management system. It provides:

- **PostgreSQL Database Layer** - Full PostgreSQL support with lazy-loaded connections
- **MVC Architecture** - Clean separation with BaseController, BaseModel, and BaseRepository abstractions
- **Learner Management** - Complete CRUD operations for learner records with PII protection
- **Class Management** - Training class scheduling, QA visits, and agent assignments
- **Events Module** - Event task tracking, material management, and AI-powered summaries
- **Security First** - Column whitelisting, capability checks, CSRF protection, and input sanitization

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.0 or higher |
| WordPress | 6.0 or higher |
| PostgreSQL | 12.0 or higher (recommended) |
| PHP Extensions | `pdo_pgsql` |

## Installation

### Manual Installation

1. Download or clone the repository to your WordPress plugins directory:

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
        'host' => 'localhost',
        'port' => 5432,
        'name' => 'wecoza',
        'user' => 'wecoza_user',
        // Password retrieved from wp_options
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
├── core/                      # Framework abstractions
│   ├── Abstract/              # Base classes
│   │   ├── BaseController.php
│   │   ├── BaseModel.php
│   │   └── BaseRepository.php
│   ├── Database/              # Database layer
│   │   └── PostgresConnection.php
│   └── Helpers/               # Utility functions
│       ├── functions.php
│       └── AjaxSecurity.php
├── src/                       # Application modules
│   ├── Learners/              # Learner management
│   │   ├── Ajax/
│   │   ├── Controllers/
│   │   ├── Models/
│   │   ├── Repositories/
│   │   ├── Services/
│   │   └── Shortcodes/
│   ├── Classes/               # Class management
│   │   ├── Controllers/
│   │   ├── Models/
│   │   ├── Repositories/
│   │   └── Services/
│   └── Events/                # Event tracking
│       ├── Admin/
│       ├── CLI/
│       ├── Controllers/
│       ├── Models/
│       ├── Services/
│       └── Shortcodes/
├── views/                     # PHP templates
│   ├── components/            # Reusable partials
│   ├── classes/               # Class-related views
│   └── learners/              # Learner-related views
├── assets/                    # Frontend assets
│   ├── css/
│   └── js/
│       ├── classes/
│       └── learners/
├── config/                    # Configuration files
│   └── app.php
├── schema/                    # Database schemas
│   └── migrations/
└── wecoza-core.php           # Plugin entry point
```

### Namespaces (PSR-4)

| Namespace | Directory |
|-----------|-----------|
| `WeCoza\Core\` | `core/` |
| `WeCoza\Learners\` | `src/Learners/` |
| `WeCoza\Classes\` | `src/Classes/` |
| `WeCoza\Events\` | `src/Events/` |

## Modules

### Learners Module

Manages learner records including personal information, qualifications, and employment details.

**Features:**
- CRUD operations for learner records
- LP (Learning Programme) progression tracking
- Portfolio upload management
- Hours tracking (trained, present, absent)

**Key Classes:**
- `LearnerController` - Request handling and shortcode registration
- `LearnerModel` - Data model with validation
- `LearnerRepository` - Database operations with column whitelisting
- `ProgressionService` - LP progression and hours tracking

### Classes Module

Manages training classes, schedules, and QA visits.

**Features:**
- Class creation and scheduling
- Agent assignment and replacements
- QA visit tracking
- Public holiday awareness
- Stop/restart date management

**Key Classes:**
- `ClassController` - Class management endpoints
- `ClassModel` - Class data model
- `ClassRepository` - Secure database operations
- `ScheduleService` - Schedule calculations
- `QAController` - QA visit management

### Events Module

Handles event-related tasks, material tracking, and AI summaries.

**Features:**
- Event task management
- Material tracking with notifications
- AI-powered event summarization
- Email notification system

**Key Classes:**
- `TaskController` - Event task AJAX handlers
- `MaterialTrackingController` - Material status tracking
- `MaterialNotificationService` - Automated notifications
- `AISummaryService` - AI-generated summaries

## Shortcodes

### Learners Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[wecoza_display_learners]` | Display paginated list of all learners |
| `[wecoza_learners_form]` | Learner registration/creation form |
| `[wecoza_single_learner_display]` | Display individual learner details |
| `[wecoza_learners_update_form]` | Edit existing learner form |

### Classes Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[wecoza_capture_class]` | Create or edit a class |
| `[wecoza_display_classes]` | Display list of classes |
| `[wecoza_display_single_class]` | Display single class details |

### Events Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[wecoza_event_tasks]` | Event task management interface |
| `[wecoza_material_tracking]` | Material tracking dashboard |
| `[wecoza_ai_summary]` | AI-generated event summary display |

## Helper Functions

### Database & Configuration

```php
// Get PostgreSQL connection
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

// Render a component
wecoza_component('pagination', ['page' => 1, 'total' => 100]);
```

### Asset URLs

```php
// Get asset URL
$url = wecoza_asset_url('images/logo.png');

// Get CSS URL
$css = wecoza_css_url('learners-style.css');

// Get JavaScript URL
$js = wecoza_js_url('learners/learners-app.js');
```

### Paths

```php
// Get plugin path
$path = wecoza_plugin_path('views/learners/list.php');

// Get core directory path
$corePath = wecoza_core_path('Abstract/BaseModel.php');
```

### Environment Detection

```php
// Check if in WordPress admin (excluding AJAX)
if (wecoza_is_admin_area()) { ... }

// Check if AJAX request
if (wecoza_is_ajax()) { ... }

// Check if REST API request
if (wecoza_is_rest()) { ... }
```

### Utilities

```php
// Debug logging (only when WP_DEBUG is enabled)
wecoza_log('Processing learner data', 'info');

// Sanitize input values
$email = wecoza_sanitize_value($input, 'email');
$id = wecoza_sanitize_value($input, 'int');
$date = wecoza_sanitize_value($input, 'date');

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

```php
// Custom capabilities registered on activation
'manage_learners'         // Access to learner PII data
'view_material_tracking'  // View material tracking
'manage_material_tracking' // Modify material tracking
```

Only Administrators receive these capabilities by default.

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

    protected function getAllowedInsertColumns(): array
    {
        return ['first_name', 'surname', 'email_address', ...];
    }

    protected function getAllowedUpdateColumns(): array
    {
        return ['first_name', 'surname', 'email_address', ...];
    }
}
```

### CSRF Protection

All AJAX handlers require nonce verification:

```php
// In controller
$this->requireNonce('learners_nonce_action');

// Or using AjaxSecurity helper
AjaxSecurity::requireNonce('learners_nonce_action');
```

### Input Sanitization

Use the `AjaxSecurity` helper for comprehensive input handling:

```php
use WeCoza\Core\Helpers\AjaxSecurity;

// Verify nonce
AjaxSecurity::verifyNonce('action_name');

// Check capability
AjaxSecurity::checkCapability('manage_learners');

// Sanitize array of inputs
$clean = AjaxSecurity::sanitizeArray($_POST, [
    'id' => 'int',
    'email' => 'email',
    'name' => 'string',
]);

// Validate file upload
AjaxSecurity::validateUploadedFile($file, ['pdf', 'doc', 'docx']);
```

## Hooks & Actions

### Plugin Lifecycle

```php
// Fired when plugin is fully loaded
do_action('wecoza_core_loaded');

// Fired on plugin activation
do_action('wecoza_core_activated');

// Fired on plugin deactivation
do_action('wecoza_core_deactivated');
```

### Extending the Plugin

```php
// Wait for WeCoza Core before initializing dependent plugins
add_action('wecoza_core_loaded', function() {
    // Your plugin initialization code
}, 10);
```

### Cron Events

| Hook | Frequency | Description |
|------|-----------|-------------|
| `wecoza_material_notifications_check` | Daily | Check and send material notifications |
| `wecoza_email_notifications_process` | Hourly | Process queued email notifications |

## WP-CLI Commands

```bash
# Test PostgreSQL connection
wp wecoza test-db

# Show plugin version
wp wecoza version

# AI Summary status (Events module)
wp wecoza-events ai-summary-status
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
// Log with different levels
wecoza_log('Information message', 'info');
wecoza_log('Warning message', 'warning');
wecoza_log('Error message', 'error');

// Only outputs when WP_DEBUG is true
```

### Database Debugging

```php
// Test connection via WP-CLI
wp wecoza test-db

// Or programmatically
$db = wecoza_db();
if ($db->testConnection()) {
    echo 'Connected! Version: ' . $db->getVersion();
}
```

## Development

### Plugin Loading Priority

WeCoza Core loads at priority `5` on the `plugins_loaded` hook. Dependent plugins should use priority `10` or higher:

```php
add_action('plugins_loaded', function() {
    // Dependent plugin code
}, 10);
```

### View File Extensions

Views support both naming conventions:
- `.view.php` (Classes module style)
- `.php` (Learners module style)

### Adding New Modules

1. Create directory structure under `src/YourModule/`
2. Add namespace to `composer.json` and main plugin file
3. Create Controller, Model, Repository, and Service classes
4. Register controllers in `wecoza-core.php`

### Code Standards

- PHP 8.0+ features (typed properties, match expressions, named arguments)
- PSR-4 autoloading
- WordPress Coding Standards for hooks and filters
- Column whitelisting for all database operations

## License

This plugin is proprietary software. All rights reserved.

**Author:** [YourDesign.co.za](https://yourdesign.co.za/)

---

For support or inquiries, please contact [info@yourdesign.co.za](mailto:info@yourdesign.co.za)
