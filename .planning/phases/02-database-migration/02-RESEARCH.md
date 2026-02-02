# Phase 2: Database Migration - Research

**Researched:** 2026-02-02
**Domain:** PostgreSQL schema migration (triggers, functions, constraints)
**Confidence:** HIGH

## Summary

Phase 2 involves migrating PostgreSQL database artifacts (triggers, functions, views) from the standalone wecoza-events-plugin into wecoza-core's unified schema. The primary technical challenge is moving triggers and functions from the `wecoza_events` schema to the `public` schema, fixing broken column references (`delivery_date`), ensuring idempotent migrations, and validating that triggers fire correctly after migration.

This is a well-established domain in PostgreSQL migrations. Modern PostgreSQL (14+) supports `CREATE OR REPLACE TRIGGER` for idempotent migrations, and trigger functions use standard PL/pgSQL patterns with special variables (NEW, OLD, TG_OP). The main risks are cascading dependencies from dropped columns, schema qualification conflicts, trigger execution order, and ensuring JSONB-based change logging performs acceptably in production.

**Primary recommendation:** Use idempotent SQL migration scripts with `CREATE OR REPLACE` patterns, migrate all database artifacts at once (not piecemeal), validate trigger execution with test data, and remove all `delivery_date` references before deploying triggers that depend on classes table structure.

## Standard Stack

The established libraries/tools for this domain:

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PostgreSQL | 16+ | Database engine | Project requirement, supports CREATE OR REPLACE TRIGGER (14+), advanced JSONB operations |
| PL/pgSQL | built-in | Trigger function language | Native to PostgreSQL, no external dependencies, transactional |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| pg_dump | built-in | Schema export | Backup before migration, compare before/after |
| pgTAP | 1.3+ | Unit testing | Test trigger functions, validate schema changes |
| psql | built-in | Migration execution | Run migration scripts transactionally |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Idempotent SQL scripts | Flyway/Liquibase | SQL scripts are simpler for single migration, no Java dependency, team already knows SQL |
| PL/pgSQL triggers | Application-level event handlers | Triggers guarantee consistency, survive direct SQL updates, no deployment coupling |
| Manual verification | Automated tests (pgTAP) | Manual faster for one-time migration, pgTAP better for ongoing validation |

**Installation:**
```bash
# pgTAP for testing (optional)
sudo apt-get install postgresql-16-pgtap  # Ubuntu/Debian
# or
brew install pgtap  # macOS

# Already available in PostgreSQL installation
psql --version  # PostgreSQL client
pg_dump --version  # Schema backup tool
```

## Architecture Patterns

### Recommended Project Structure
```
schema/
├── wecoza_db_schema_bu_jan_29.sql      # Existing backup (reference only)
├── migrations/                          # NEW: Migration scripts directory
│   ├── 001-migrate-triggers.sql        # Trigger migration
│   ├── 002-migrate-functions.sql       # Function migration
│   ├── 003-fix-delivery-date-refs.sql  # Remove delivery_date column refs
│   └── README.md                        # Execution order, rollback notes
└── tests/                               # NEW: Optional test directory
    └── triggers/
        └── test-class-change-log.sql   # pgTAP tests for triggers
```

**Critical:** Migration scripts must be idempotent, versioned, and executable in order. Rollback scripts are optional but recommended for critical triggers.

### Pattern 1: Idempotent Trigger Migration (CREATE OR REPLACE)
**What:** Use `CREATE OR REPLACE TRIGGER` to create triggers that can be re-run safely without errors.
**When to use:** For all trigger migrations where PostgreSQL 14+ is available.
**Example:**
```sql
-- Source: https://www.postgresql.org/docs/current/sql-createtrigger.html
-- Idempotent trigger creation
CREATE OR REPLACE TRIGGER classes_log_insert_update
    AFTER INSERT OR UPDATE ON public.classes
    FOR EACH ROW
    EXECUTE FUNCTION public.log_class_change();

-- If trigger doesn't exist, it's created
-- If trigger exists, it's replaced with new definition
-- Safe to run multiple times
```

### Pattern 2: Idempotent Function Migration (CREATE OR REPLACE)
**What:** PostgreSQL functions support `CREATE OR REPLACE` natively - use it for all function migrations.
**When to use:** For all function migrations.
**Example:**
```sql
-- Source: https://www.postgresql.org/docs/current/plpgsql-trigger.html
CREATE OR REPLACE FUNCTION public.log_class_change() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    op TEXT := TG_OP;
    event_time TIMESTAMP WITHOUT TIME ZONE := NOW();
    new_data JSONB := to_jsonb(NEW);
    old_data JSONB := CASE WHEN TG_OP = 'UPDATE' THEN to_jsonb(OLD) ELSE NULL END;
    diff JSONB := '{}'::jsonb;
BEGIN
    IF op = 'UPDATE' THEN
        -- Compute diff between old and new rows
        diff := (
            SELECT COALESCE(
                jsonb_object_agg(key, jsonb_build_object('old', old_data -> key, 'new', new_data -> key)),
                '{}'::jsonb
            )
            FROM (
                SELECT key FROM jsonb_object_keys(new_data) AS new_keys(key)
                UNION
                SELECT key FROM jsonb_object_keys(COALESCE(old_data, '{}'::jsonb)) AS old_keys(key)
            ) AS keys(key)
            WHERE (old_data -> key) IS DISTINCT FROM (new_data -> key)
        );
    ELSE
        diff := new_data;
    END IF;

    -- Insert change log
    INSERT INTO public.class_change_logs (class_id, operation, changed_at, new_row, old_row, diff)
    VALUES (NEW.class_id, op, event_time, new_data, old_data, diff);

    -- Send notification
    PERFORM pg_notify(
        'class_change_channel',
        json_build_object(
            'operation', op,
            'class_id', NEW.class_id,
            'class_code', NEW.class_code,
            'class_subject', NEW.class_subject,
            'changed_at', event_time,
            'diff', diff
        )::text
    );

    RETURN NEW;
END;
$$;
```

### Pattern 3: Schema-Qualified vs Unqualified Object Names
**What:** Remove schema qualification (`wecoza_events.`) and rely on `search_path` defaults.
**When to use:** When consolidating schemas into `public` schema.
**Example:**
```sql
-- BEFORE (events plugin - wecoza_events schema)
CREATE FUNCTION wecoza_events.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$;

CREATE TRIGGER update_dashboard_status_updated_at
    BEFORE UPDATE ON wecoza_events.dashboard_status
    FOR EACH ROW
    EXECUTE FUNCTION wecoza_events.update_updated_at_column();

-- AFTER (wecoza-core - public schema, no qualification needed)
CREATE OR REPLACE FUNCTION public.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$;

-- If dashboard_status moves to public schema:
CREATE OR REPLACE TRIGGER update_dashboard_status_updated_at
    BEFORE UPDATE ON public.dashboard_status
    FOR EACH ROW
    EXECUTE FUNCTION public.update_updated_at_column();

-- Or keep in wecoza_events schema but use public function:
CREATE OR REPLACE TRIGGER update_dashboard_status_updated_at
    BEFORE UPDATE ON wecoza_events.dashboard_status
    FOR EACH ROW
    EXECUTE FUNCTION public.update_updated_at_column();
```

### Pattern 4: Transactional Migration Execution
**What:** Wrap migration in transaction with BEGIN/COMMIT to ensure atomicity.
**When to use:** For all migration scripts.
**Example:**
```sql
-- Migration script template
BEGIN;

-- Step 1: Create/replace functions first (triggers depend on them)
CREATE OR REPLACE FUNCTION public.log_class_change() RETURNS trigger
    LANGUAGE plpgsql
    AS $$ ... $$;

-- Step 2: Create/replace triggers
CREATE OR REPLACE TRIGGER classes_log_insert_update
    AFTER INSERT OR UPDATE ON public.classes
    FOR EACH ROW
    EXECUTE FUNCTION public.log_class_change();

-- Step 3: Verify (optional - check pg_trigger catalog)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_trigger
        WHERE tgname = 'classes_log_insert_update'
        AND tgrelid = 'public.classes'::regclass
    ) THEN
        RAISE EXCEPTION 'Trigger classes_log_insert_update was not created';
    END IF;
END $$;

COMMIT;
-- If any step fails, entire transaction rolls back
```

### Anti-Patterns to Avoid
- **Non-idempotent migrations:** Don't use `CREATE TRIGGER` without `OR REPLACE` - it fails on re-run. Use `CREATE OR REPLACE` or `DROP IF EXISTS` + `CREATE`.
- **Function-then-trigger order violation:** Don't create triggers before their functions exist. Always create functions first.
- **Schema qualification inconsistency:** Don't mix qualified (`wecoza_events.`) and unqualified names in same database. Choose one pattern and stick to it.
- **Ignoring delivery_date dependencies:** Don't migrate triggers that reference `delivery_date` column without first removing those references from queries.
- **Silent errors in triggers:** Don't use triggers without proper error handling. Use `RAISE EXCEPTION` with meaningful ERRCODE and hints.

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Migration versioning | Custom tracking table | Numbered migration files (001-, 002-) | Simple, visible in filesystem, Git-friendly, no DB coupling before migration |
| Trigger testing | Manual INSERT/UPDATE tests | pgTAP test framework | Automated, repeatable, TAP output integrates with CI/CD, tests schema structure + logic |
| Schema comparison | Manual inspection | `pg_dump --schema-only` diff | Captures all objects (triggers, functions, constraints), version controllable |
| JSONB diff calculation | Custom PL/pgSQL logic | Use existing pattern from `log_class_change()` | Handles nested objects, null values, type differences, proven in production |
| Notification payloads | Full row serialization | Send only IDs + critical fields | pg_notify has 8000 byte limit, listeners can query for full data if needed |

**Key insight:** PostgreSQL has mature migration patterns (idempotent DDL, transactional execution, built-in testing tools). The migration should use these native capabilities rather than introducing external frameworks for a one-time schema consolidation.

## Common Pitfalls

### Pitfall 1: Dropped Column Cascading to Triggers
**What goes wrong:** Dropping `delivery_date` column from queries breaks triggers/functions that reference it, causing PostgreSQL errors on INSERT/UPDATE.
**Why it happens:** Triggers fire automatically on table changes, and if trigger function queries reference missing columns, they fail silently or loudly depending on error handling.
**How to avoid:**
1. Search ALL SQL in Events module for `delivery_date` references: `grep -r "delivery_date" src/Events/ --include="*.php"`
2. Remove `delivery_date` from SELECT statements in repositories before testing triggers
3. Update FieldMapper to remove delivery_date mapping
4. Test INSERT and UPDATE on classes table after column removal to catch trigger errors
**Warning signs:** PostgreSQL errors like "column `delivery_date` does not exist" during INSERT/UPDATE operations, triggers failing silently

### Pitfall 2: Schema Qualification Confusion
**What goes wrong:** Triggers reference `wecoza_events.function_name()` but function was created in `public` schema, or vice versa. Trigger creation fails with "function does not exist."
**Why it happens:** PostgreSQL resolves unqualified names using `search_path` (default: `"$user", public`). Schema-qualified names ignore search_path.
**How to avoid:**
1. Decide schema location for each object type:
   - Core triggers/functions → `public` schema (shared by all modules)
   - Events-specific tables → Keep in `wecoza_events` OR move to `public`
2. Use fully qualified names in all CREATE TRIGGER statements: `public.log_class_change()` not `log_class_change()`
3. Document schema decisions in migration README
**Warning signs:** "function does not exist" errors even though function was created, triggers not firing after migration

### Pitfall 3: Trigger Function Return Value Mismatch
**What goes wrong:** BEFORE trigger returns NULL unexpectedly, blocking INSERT/UPDATE. Or AFTER trigger returns wrong type, causing errors.
**Why it happens:** PL/pgSQL trigger return value semantics vary by timing and level:
- BEFORE ROW: NULL = skip operation, record = proceed with that record
- AFTER ROW: return value ignored
- BEFORE STATEMENT: return value ignored
**How to avoid:**
1. For AFTER triggers (like `log_class_change`), always `RETURN NEW` or `RETURN OLD` even though ignored (best practice)
2. For BEFORE triggers, return NULL only to intentionally cancel operation
3. Never return undefined/uninitialized record variables
**Warning signs:** INSERTs/UPDATEs silently fail with no error, or errors like "control reached end of trigger procedure without RETURN"

### Pitfall 4: pg_notify Payload Size Exceeded
**What goes wrong:** Trigger with `pg_notify` fails when `diff` JSONB exceeds 8000 bytes. Class updates with large text changes crash the trigger.
**Why it happens:** PostgreSQL `NOTIFY` has hard 8000-byte payload limit. Large class updates (descriptions, notes, etc.) generate large diff JSONs.
**How to avoid:**
1. Send minimal payload: class_id, operation, changed_at, small identifier fields only
2. Listeners can query full details using class_id if needed
3. For debugging, truncate diff to summary: `(diff::text)[1:7900]` to prevent overflow
4. Monitor: Add exception handler to log payload size on failure
**Warning signs:** Trigger errors "payload string too long" on UPDATE operations with large text fields

### Pitfall 5: Function Dependencies Not Created First
**What goes wrong:** `CREATE TRIGGER` fails because the function it references doesn't exist yet.
**Why it happens:** Triggers depend on functions, but migration scripts may be written in wrong order or partially executed.
**How to avoid:**
1. Structure migration files: `001-functions.sql` before `002-triggers.sql`
2. Within single file: functions before triggers
3. For shared functions (like `update_updated_at_column`), create once, reference many times
4. Test migration on fresh database to catch dependency order issues
**Warning signs:** "function public.log_class_change() does not exist" when creating trigger

### Pitfall 6: JSONB Performance on Large Tables
**What goes wrong:** `to_jsonb(NEW)` and JSONB diff calculation slow down INSERT/UPDATE operations as classes table grows.
**Why it happens:** Converting entire row to JSONB and computing diffs has computational cost. With 10k+ classes, trigger overhead becomes noticeable.
**How to avoid:**
1. Monitor trigger execution time: Enable `log_duration` in PostgreSQL config during testing
2. Index JSONB columns used in queries: `CREATE INDEX idx_diff_class_code ON class_change_logs USING GIN ((new_row->'class_code'))`
3. Consider selective diff: Only track specific important fields instead of entire row
4. Benchmark: Test INSERT/UPDATE performance with and without trigger on realistic data volume
**Warning signs:** Slow INSERT/UPDATE operations (>100ms for single row), increasing response times as data grows

## Code Examples

Verified patterns from official sources and existing schema:

### Complete Trigger Function with Error Handling
```sql
-- Source: wecoza-core/schema/wecoza_db_schema_bu_jan_29.sql (line 100-144)
CREATE OR REPLACE FUNCTION public.log_class_change() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    op TEXT := TG_OP;
    event_time TIMESTAMP WITHOUT TIME ZONE := NOW();
    new_data JSONB := to_jsonb(NEW);
    old_data JSONB := CASE WHEN TG_OP = 'UPDATE' THEN to_jsonb(OLD) ELSE NULL END;
    diff JSONB := '{}'::jsonb;
BEGIN
    -- Compute differential changes for UPDATE operations
    IF op = 'UPDATE' THEN
        diff := (
            SELECT COALESCE(
                jsonb_object_agg(key, jsonb_build_object('old', old_data -> key, 'new', new_data -> key)),
                '{}'::jsonb
            )
            FROM (
                SELECT key FROM jsonb_object_keys(new_data) AS new_keys(key)
                UNION
                SELECT key FROM jsonb_object_keys(COALESCE(old_data, '{}'::jsonb)) AS old_keys(key)
            ) AS keys(key)
            WHERE (old_data -> key) IS DISTINCT FROM (new_data -> key)
        );
    ELSE
        -- For INSERT, entire row is the "diff"
        diff := new_data;
    END IF;

    -- Persist change log
    INSERT INTO public.class_change_logs (class_id, operation, changed_at, new_row, old_row, diff)
    VALUES (NEW.class_id, op, event_time, new_data, old_data, diff);

    -- Send async notification (8000 byte limit - keep minimal)
    PERFORM pg_notify(
        'class_change_channel',
        json_build_object(
            'operation', op,
            'class_id', NEW.class_id,
            'class_code', NEW.class_code,
            'class_subject', NEW.class_subject,
            'changed_at', event_time,
            'diff', diff
        )::text
    );

    RETURN NEW;
END;
$$;

-- Attach trigger to classes table
CREATE OR REPLACE TRIGGER classes_log_insert_update
    AFTER INSERT OR UPDATE ON public.classes
    FOR EACH ROW
    EXECUTE FUNCTION public.log_class_change();
```

### Simple updated_at Trigger Pattern
```sql
-- Source: wecoza-core/schema/wecoza_db_schema_bu_jan_29.sql (line 154-161)
-- Reusable function for automatic updated_at timestamp
CREATE OR REPLACE FUNCTION public.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$;

-- Apply to multiple tables
CREATE OR REPLACE TRIGGER update_agents_updated_at
    BEFORE UPDATE ON public.agents
    FOR EACH ROW
    EXECUTE FUNCTION public.update_updated_at_column();

CREATE OR REPLACE TRIGGER update_material_tracking_updated_at
    BEFORE UPDATE ON public.class_material_tracking
    FOR EACH ROW
    EXECUTE FUNCTION public.update_updated_at_column();
```

### Migration Script with Verification
```sql
-- Complete migration script template
BEGIN;

-- Set search_path to ensure consistent schema resolution
SET search_path TO public;

-- Step 1: Create functions (triggers depend on these)
CREATE OR REPLACE FUNCTION public.log_class_change() RETURNS trigger
    LANGUAGE plpgsql
    AS $func$
-- ... function body ...
$func$;

CREATE OR REPLACE FUNCTION public.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $func$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$func$;

-- Step 2: Create triggers
CREATE OR REPLACE TRIGGER classes_log_insert_update
    AFTER INSERT OR UPDATE ON public.classes
    FOR EACH ROW
    EXECUTE FUNCTION public.log_class_change();

CREATE OR REPLACE TRIGGER update_material_tracking_updated_at
    BEFORE UPDATE ON public.class_material_tracking
    FOR EACH ROW
    EXECUTE FUNCTION public.update_updated_at_column();

-- Step 3: Verification (optional but recommended)
DO $$
DECLARE
    trigger_count INT;
BEGIN
    -- Verify all expected triggers exist
    SELECT COUNT(*) INTO trigger_count
    FROM pg_trigger t
    JOIN pg_class c ON t.tgrelid = c.oid
    WHERE c.relname = 'classes'
      AND t.tgname = 'classes_log_insert_update';

    IF trigger_count = 0 THEN
        RAISE EXCEPTION 'Migration failed: classes_log_insert_update trigger not found';
    END IF;

    RAISE NOTICE 'Migration verification successful: % triggers verified', trigger_count;
END $$;

COMMIT;
```

### Testing Trigger Execution (pgTAP)
```sql
-- Source: https://pgtap.org/documentation.html
-- Test that trigger fires and logs changes
BEGIN;

-- Load pgTAP extension
CREATE EXTENSION IF NOT EXISTS pgtap;

-- Test trigger function exists
SELECT has_function(
    'public',
    'log_class_change',
    'Trigger function log_class_change should exist'
);

-- Test trigger exists on classes table
SELECT has_trigger(
    'public',
    'classes',
    'classes_log_insert_update',
    'Trigger classes_log_insert_update should exist on classes table'
);

-- Test trigger fires on INSERT
PREPARE test_insert AS
    INSERT INTO public.classes (class_code, class_subject, original_start_date)
    VALUES ('TEST-001', 'Test Subject', '2026-03-01')
    RETURNING class_id;

PREPARE verify_log AS
    SELECT COUNT(*) FROM public.class_change_logs
    WHERE operation = 'INSERT' AND (new_row->>'class_code') = 'TEST-001';

SELECT results_eq(
    'verify_log',
    ARRAY[1::BIGINT],
    'Trigger should log INSERT operation'
);

-- Cleanup
ROLLBACK;
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| DROP TRIGGER + CREATE TRIGGER | CREATE OR REPLACE TRIGGER | PostgreSQL 14 (Nov 2020) | Idempotent migrations, no error on re-run, safer production deployments |
| Manual SQL migration execution | Version-controlled migration files | Modern practice (2020s) | Auditable, reviewable, repeatable migrations, Git history |
| Application-level change tracking | Database triggers with JSONB | PostgreSQL 9.4+ (JSONB added 2014) | Guaranteed consistency, survives direct SQL updates, no deployment coupling |
| Limited 8KB NOTIFY payloads | Minimal payloads + query pattern | Current best practice (2025+) | Reliable notifications, listeners query for full data as needed |
| Separate wecoza_events schema | Consolidated public schema | wecoza-core architecture decision | Simpler queries, no schema qualification, unified plugin structure |

**Deprecated/outdated:**
- **Non-idempotent migrations:** `CREATE TRIGGER` (fails on re-run) replaced by `CREATE OR REPLACE TRIGGER`
- **wecoza_events schema for triggers:** Moving to `public` schema for core infrastructure
- **delivery_date column:** Removed from classes table, any trigger/function references must be cleaned up
- **Large NOTIFY payloads:** Don't serialize entire records, send IDs only (8000 byte limit)

## Open Questions

1. **Should wecoza_events schema tables move to public or stay separate?**
   - What we know: Schema has 5 tables (audit_log, dashboard_status, events_log, notification_queue, supervisors), functions reference these tables
   - What's unclear: Whether Events module tables should be schema-isolated or merged into public schema with other modules
   - Recommendation: Keep wecoza_events schema tables separate for now (preserves logical separation, avoids table naming conflicts). Move triggers/functions to public schema (shared infrastructure). Migration is simpler (only 3 functions + 3 triggers to move).

2. **How to handle delivery_date references in MaterialNotificationService queries?**
   - What we know: 5 files reference delivery_date: ClassTaskPresenter, FieldMapper, ClassTaskRepository, MaterialNotificationService (3 references)
   - What's unclear: Is delivery_date column actually dropped from database, or just missing from some queries?
   - Recommendation: Before Phase 2 execution, verify column existence with `\d classes` in psql. If dropped, remove all PHP references in one commit. If exists, coordinate drop with PHP cleanup to avoid breaking queries.

3. **Should pgTAP tests be written for triggers, or is manual verification sufficient?**
   - What we know: pgTAP provides automated testing for triggers, one-time migration may not justify test investment
   - What's unclear: Whether ongoing trigger maintenance warrants test framework setup
   - Recommendation: Skip pgTAP for Phase 2 (one-time migration, manual testing faster). Add pgTAP tests later if trigger logic becomes complex or frequently modified. Use manual verification: test INSERT/UPDATE on classes table, query class_change_logs, confirm rows logged.

4. **What is the pg_notify listener in production? Is it actively used?**
   - What we know: Trigger sends notifications to `class_change_channel`, but no listener code found in wecoza-core
   - What's unclear: Whether events plugin has external listener, or if NOTIFY was prototype/unused feature
   - Recommendation: Keep pg_notify in trigger (minimal overhead if no listeners). Document in migration notes. If no listener found, consider removing in future optimization.

## Sources

### Primary (HIGH confidence)
- [PostgreSQL Documentation: CREATE TRIGGER](https://www.postgresql.org/docs/current/sql-createtrigger.html) - Official syntax and constraints
- [PostgreSQL Documentation: PL/pgSQL Trigger Functions](https://www.postgresql.org/docs/current/plpgsql-trigger.html) - Trigger function requirements and special variables
- wecoza-core schema dump: `/schema/wecoza_db_schema_bu_jan_29.sql` - Existing trigger definitions
- wecoza-core codebase: `src/Events/Repositories/*` - Current PHP code using triggers

### Secondary (MEDIUM confidence)
- [PostgreSQL Triggers in 2026: Design, Performance, and Production Reality – TheLinuxCode](https://thelinuxcode.com/postgresql-triggers-in-2026-design-performance-and-production-reality/) - Modern trigger best practices
- [Idempotent SQL DDL | by Eric Hosick | Medium](https://medium.com/full-stack-architecture/idempotent-sql-ddl-ca354a1eee62) - Idempotent migration patterns
- [pgTAP: Unit Testing for PostgreSQL](https://pgtap.org/) - Testing framework documentation
- [PostgreSQL DROP COLUMN - Neon](https://neon.com/postgresql/postgresql-tutorial/postgresql-drop-column) - CASCADE behavior
- [How to avoid performance bottlenecks when using JSONB in PostgreSQL | Metis](https://www.metisdata.io/blog/how-to-avoid-performance-bottlenecks-when-using-jsonb-in-postgresql) - JSONB performance

### Tertiary (LOW confidence)
- [PostgreSQL LISTEN/NOTIFY: Real-Time Without the Message Broker - Pedro Alonso](https://www.pedroalonso.net/blog/postgres-listen-notify-real-time/) - pg_notify patterns
- [Understanding the Public Schema and Search Path in PostgreSQL | Medium](https://medium.com/@jramcloud1/understanding-the-public-schema-and-search-path-in-postgresql-a-practical-guide-b8b550fab9cc) - Schema qualification
- [Top Open Source Postgres Migration Tools in 2026](https://www.bytebase.com/blog/top-open-source-postgres-migration-tools/) - Migration tooling landscape

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - PostgreSQL native features, CREATE OR REPLACE is standard since PG14
- Architecture: HIGH - Migration patterns verified from official PostgreSQL docs, existing schema structure documented
- Pitfalls: HIGH - Based on common PostgreSQL migration issues + specific delivery_date/schema qualification issues found in codebase

**Research date:** 2026-02-02
**Valid until:** 2026-03-02 (30 days - PostgreSQL 16 stable, migration is one-time event, patterns don't change rapidly)
