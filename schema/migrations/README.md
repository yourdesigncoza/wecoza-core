# Database Migrations

This directory contains SQL migration scripts for wecoza-core database schema.

## Migration Files

| File | Purpose | Idempotent |
|------|---------|------------|
| 001-verify-triggers.sql | Class change logging triggers | Yes |

## Trigger Infrastructure

### class_change_logs Trigger

The `classes_log_insert_update` trigger automatically logs all INSERT and UPDATE operations on the `public.classes` table.

**Trigger:** `classes_log_insert_update`
- **Table:** `public.classes`
- **Events:** AFTER INSERT OR UPDATE
- **Function:** `public.log_class_change()`

**Function:** `public.log_class_change()`
- Converts row data to JSONB
- Computes diff for UPDATE operations (only changed fields)
- Inserts record into `public.class_change_logs`
- Sends pg_notify to `class_change_channel` for real-time listeners

**Table:** `public.class_change_logs`
| Column | Type | Description |
|--------|------|-------------|
| log_id | BIGSERIAL | Primary key |
| class_id | INTEGER | FK to classes.class_id |
| operation | TEXT | 'INSERT' or 'UPDATE' |
| changed_at | TIMESTAMP | When change occurred |
| new_row | JSONB | Full row data after change |
| old_row | JSONB | Full row data before change (UPDATE only) |
| diff | JSONB | Only changed fields with old/new values |

### Running Migrations

Migrations are idempotent and can be run multiple times safely:

```bash
# Connect to database and run migration
psql -h localhost -U John -d wecoza -f schema/migrations/001-verify-triggers.sql
```

### Verifying Triggers

Check trigger exists:
```sql
SELECT tgname, tgrelid::regclass, tgfoid::regproc
FROM pg_trigger
WHERE tgname = 'classes_log_insert_update';
```

Test trigger fires on INSERT:
```sql
-- Insert test class
INSERT INTO public.classes (class_code, class_subject, original_start_date)
VALUES ('TEST-MIGRATION', 'Test Subject', '2026-12-01')
RETURNING class_id;

-- Verify log entry created
SELECT * FROM public.class_change_logs
WHERE (new_row->>'class_code') = 'TEST-MIGRATION'
ORDER BY changed_at DESC LIMIT 1;

-- Cleanup test data
DELETE FROM public.class_change_logs WHERE (new_row->>'class_code') = 'TEST-MIGRATION';
DELETE FROM public.classes WHERE class_code = 'TEST-MIGRATION';
```

## Notes

- All migrations use `CREATE OR REPLACE` for idempotency
- Migrations are wrapped in transactions (BEGIN/COMMIT)
- Verification checks run at end of each migration
- pg_notify has 8000 byte payload limit - large diffs may be truncated
