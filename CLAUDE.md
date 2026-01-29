# WeCoza Core Plugin

WordPress plugin providing core infrastructure: PostgreSQL database, MVC architecture, shared utilities.

## Architecture

```
core/           # Framework abstractions (Base*, PostgresConnection, Helpers)
src/
  Learners/     # Learner module (Controllers, Models, Repositories, Services)
  Classes/      # Classes module (same structure)
views/          # PHP templates (.view.php or .php)
config/         # Configuration files (app.php)
assets/js/      # Frontend JS
```

## Namespaces (PSR-4)

- `WeCoza\Core\` → `core/`
- `WeCoza\Learners\` → `src/Learners/`
- `WeCoza\Classes\` → `src/Classes/`

## Key Functions

- `wecoza_db()` - Get PostgreSQL connection
- `wecoza_view($path, $data)` - Render view template
- `wecoza_config($name)` - Load config file
- `wecoza_log($msg, $level)` - Debug logging

## Database

- PostgreSQL (not MySQL)
- Connection: `WeCoza\Core\Database\PostgresConnection::getInstance()`
- Password stored in WP option: `wecoza_postgres_password`

## Gotchas

- Plugin loads at priority 5 on `plugins_loaded`
- Dependent plugins should use priority 10+
- Views support both `.view.php` and `.php` extensions
- Requires `pdo_pgsql` PHP extension
