# Technology Stack

**Analysis Date:** 2026-02-02

## Languages

**Primary:**
- PHP 8.0+ - All backend code, controllers, models, repositories
- JavaScript (vanilla) - Frontend interactivity and AJAX handlers in `assets/js/`

**Secondary:**
- HTML/CSS - Template rendering via PHP views in `views/` directory
- SQL (PostgreSQL dialect) - Database queries via PDO prepared statements

## Runtime

**Environment:**
- WordPress 6.0+ - CMS platform hosting the plugin
- PHP 8.0+ (minimum requirement enforced at activation)
- Apache/LAMP stack (deployment uses XAMPP)

**Package Manager:**
- Composer - PHP dependency management
- Lockfile: Not present (no dependencies beyond PHP itself)

## Frameworks

**Core:**
- WordPress Plugin Framework - Standard WP hooks, filters, actions
- PDO (PHP Data Objects) - Database abstraction layer

**Architecture:**
- MVC Pattern - Controllers in `src/*/Controllers/`, Models in `src/*/Models/`, Repositories in `src/*/Repositories/`
- Service Layer - Business logic in `src/*/Services/`
- PSR-4 Autoloader - Manual implementation in `wecoza-core.php` for `WeCoza\Core\`, `WeCoza\Learners\`, `WeCoza\Classes\` namespaces

**Frontend:**
- Vanilla JavaScript (no framework) - Plain DOM manipulation with jQuery dependency from WordPress
- Bootstrap CSS classes - Form elements use `form-control`, `form-select`, `form-check-input` classes

## Key Dependencies

**Critical:**
- PDO PostgreSQL extension (`pdo_pgsql`) - Required at activation, enables PostgreSQL connectivity
- WordPress Core Functions - Global functions like `wp_enqueue_script`, `wp_localize_script`, `add_action`, `do_action`

**Infrastructure:**
- None - Plugin is self-contained with no external package dependencies

## Configuration

**Environment:**
- WordPress Options for PostgreSQL credentials:
  - `wecoza_postgres_host` (default: `102.141.145.117`)
  - `wecoza_postgres_port` (default: `5432`)
  - `wecoza_postgres_dbname` (default: `wecoza_db`)
  - `wecoza_postgres_user` (default: `John`)
  - `wecoza_postgres_password` (required, no default)
- Config file: `config/app.php` - Application settings cached in memory

**Build:**
- No build tools (webpack, vite, esbuild) - JavaScript and CSS served as-is
- PHP Optimization: `optimize-autoloader` enabled in `composer.json`

## Platform Requirements

**Development:**
- PHP 8.0+
- PostgreSQL support via PDO extension
- WordPress 6.0+ environment

**Production:**
- DigitalOcean Managed Database (PostgreSQL) - Database hosted at `102.141.145.117:5432`
- SSL mode configured as `prefer` (or `require` for SSL-only)
- WordPress hosting with plugin system enabled
- File uploads supported (learner portfolio uploads)

---

*Stack analysis: 2026-02-02*
