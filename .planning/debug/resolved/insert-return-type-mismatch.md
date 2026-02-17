---
status: resolved
trigger: "PostgresConnection::insert() declares return type string|bool but returns int on line 353, causing fatal error when creating a new location via wecoza_locations_capture shortcode"
created: 2026-02-17T00:00:00Z
updated: 2026-02-17T00:01:00Z
---

## Current Focus

hypothesis: CONFIRMED - insert() return type string|bool incompatible with int returned by fetchColumn() under strict_types=1
test: Read PostgresConnection.php insert() method and all callers
expecting: Type mismatch between declared return type and actual returned type
next_action: RESOLVED

## Symptoms

expected: Creating a new location via wecoza_locations_capture shortcode should save successfully and return the new location ID
actual: Fatal error - Uncaught Error: WeCoza\Core\Database\PostgresConnection::insert(): Return value must be of type string|bool, int returned
errors: Uncaught Error in /opt/lampp/htdocs/wecoza/wp-content/plugins/wecoza-core/core/Database/PostgresConnection.php on line 353
reproduction: Create a new location using the wecoza_locations_capture shortcode form
started: Likely broke during v4.0 technical debt refactoring (strict types added)

## Eliminated

## Evidence

- timestamp: 2026-02-17T00:01:00Z
  checked: core/Database/PostgresConnection.php line 314 and 353
  found: insert() declared as returning string|bool but PDOStatement::fetchColumn() returns an int for integer primary key columns because PDO::ATTR_EMULATE_PREPARES is false (line 129), causing PDO to return native PHP types from PostgreSQL
  implication: With declare(strict_types=1) on line 2, PHP 8 strict return type enforcement causes a fatal error when an int is returned from a method typed string|bool

- timestamp: 2026-02-17T00:01:00Z
  checked: All callers of wecoza_db()->insert() across src/
  found: LocationsModel.php casts result to int via (int)$locationId; SitesModel.php casts via (int)$insert; ClientsModel.php casts via (int)$insertId; AgentService.php and AgentRepository.php use truthy check only. BaseRepository::insert() uses its own direct SQL with RETURNING and casts to int itself.
  implication: All callers already handle int return value correctly via casting, so adding int to the return type union is safe

## Resolution

root_cause: PostgresConnection::insert() was declared as returning string|bool, but with PDO::ATTR_EMULATE_PREPARES=false, PostgreSQL returns integer primary key columns as PHP int from fetchColumn(). When declare(strict_types=1) was added during v4.0 tech debt refactoring, PHP 8 began enforcing the return type strictly, causing a fatal error on any insert into a table with an integer primary key.

fix: Changed return type declaration from string|bool to string|int|bool on line 314 of core/Database/PostgresConnection.php. Updated docblock to reflect that the return type depends on the column type in PostgreSQL.

verification: All callers cast the result to int themselves before use, so adding int to the union type does not break any existing code. The fix is minimal and targeted.

files_changed:
  - core/Database/PostgresConnection.php
