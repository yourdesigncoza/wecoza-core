# NLQ (Natural Language Query) Module

> **Status:** Phase 1 & 2 Complete · Phase 3 Planned  
> **Created:** March 11, 2026  
> **Module Path:** `src/NLQ/`  
> **Shortcodes:** `[wecoza_nlq_input]`, `[wecoza_nlq_manager]`, `[wecoza_nlq_table query_id="X"]`  
> **Live Page:** `/dynamic-data/`

---

## Overview

The NLQ module allows users to query the WeCoza database using natural language. The system converts plain English questions into safe, read-only SQL queries via OpenAI GPT-4.1, executes them against PostgreSQL, and displays the results. Users can save queries and embed them anywhere on the site via shortcodes.

### User Workflow

```
1. User types: "Show me all agents who are active with their email and phone"
        ↓
2. AI generates SQL: SELECT agent_id, first_name, surname, email_address, tel_number 
                      FROM agents WHERE status = 'active' LIMIT 100
        ↓
3. SQLSandbox validates: ✅ READ-ONLY, no dangerous patterns
        ↓
4. Query executes on PostgreSQL → results displayed in Phoenix-styled DataTable
        ↓
5. User can:
   a) Refine with AI ("also sort by surname, remove the limit")
   b) Edit SQL manually
   c) Preview again
        ↓
6. Save Query → gets ID #42 and shortcode [wecoza_nlq_table query_id="42"]
        ↓
7. Shortcode can be placed on any WordPress page to display that dataset
```

---

## Architecture

### File Structure

```
src/NLQ/
  Ajax/
    NLQAjaxHandler.php          # 10 AJAX endpoints with nonce + capability checks
  Repositories/
    SavedQueryRepository.php     # PostgreSQL CRUD, column whitelisting, slug generation
  Services/
    NLQService.php               # Orchestrator: validate → execute → save
    SQLSandbox.php               # Read-only SQL enforcement & injection prevention
    SQLGeneratorService.php      # OpenAI GPT-4.1 integration for NL→SQL
  Shortcodes/
    NLQInputShortcode.php        # [wecoza_nlq_input] — AI query builder
    NLQManagerShortcode.php      # [wecoza_nlq_manager] — query CRUD management
    NLQTableShortcode.php        # [wecoza_nlq_table] — display saved query results

views/nlq/components/
    nlq-input.view.php           # AI query builder UI (Phoenix-styled)
    nlq-manager.view.php         # Query management UI (Phoenix-styled)
    nlq-table-display.view.php   # Query result table UI (Phoenix-styled)

assets/js/nlq/
    nlq-input.js                 # AI generate → preview → refine → save workflow
    nlq-manager.js               # Query list, edit, delete, copy shortcode
    nlq-table.js                 # DataTable init + CSV export

schema/
    saved_queries.sql            # PostgreSQL table definition
```

### Database Schema

**Table:** `saved_queries` (PostgreSQL)

| Column | Type | Description |
|---|---|---|
| `id` | SERIAL PK | Auto-increment ID |
| `query_name` | VARCHAR(255) | Human-readable name |
| `query_slug` | VARCHAR(255) UNIQUE | URL-friendly identifier |
| `description` | TEXT | What the query shows |
| `natural_language` | TEXT | Original NL prompt from user |
| `sql_query` | TEXT | The SQL (SELECT only) |
| `category` | VARCHAR(100) | Organizational category (Agents, Learners, etc.) |
| `is_active` | BOOLEAN | Soft-delete flag |
| `created_by` | INTEGER | WordPress user ID |
| `updated_by` | INTEGER | Last editor's WP user ID |
| `created_at` | TIMESTAMP | Creation timestamp |
| `updated_at` | TIMESTAMP | Last update timestamp |
| `last_executed` | TIMESTAMP | Last time query was run |
| `execution_count` | INTEGER | Total execution count |

**Indexes:** slug, category, is_active, created_by

### Namespace

`WeCoza\NLQ\` → `src/NLQ/` (PSR-4, registered in `wecoza-core.php`)

---

## Shortcodes

### `[wecoza_nlq_input]` — AI Query Builder

The main user-facing interface. Users type a question, AI generates SQL, preview results, refine, and save.

**Features:**
- Natural language textarea with placeholder examples
- Module auto-detection (Agents, Learners, Classes, Clients) or manual selection
- AI generates SQL with explanation
- Editable SQL textarea (user can tweak before executing)
- "Preview Results" renders results in a Phoenix DataTable
- "Refine with AI" for iterative query improvement
- Save form with auto-populated name and category
- Outputs shortcode on save for embedding

**Permissions:** `manage_options` capability required

### `[wecoza_nlq_manager]` — Query Management

Admin interface for listing, editing, previewing, and deactivating saved queries.

**Features:**
- Tabbed UI: "Saved Queries" list + "Create Query" form
- Phoenix-styled table with ID badges, category badges, execution counts
- Summary strip: Total Queries, Active, Total Executions, Categories
- Search, category filter, refresh
- Click-to-copy shortcode column
- Preview modal (executes query and shows results)
- Edit (loads into form), Deactivate (soft-delete)
- Manual query creation (SQL editor without AI)

**Permissions:** `manage_options` capability required

### `[wecoza_nlq_table query_id="X"]` — Display Results

Renders a saved query as a read-only Phoenix-styled DataTable. This is what end-users see.

**Attributes:**

| Attribute | Default | Description |
|---|---|---|
| `query_id` | *(required)* | Saved query ID |
| `title` | Query name | Override display title |
| `page_size` | `25` | DataTables page length |
| `show_sql` | `false` | Show SQL in collapsible accordion |
| `export` | `true` | Show CSV export button |

**Example:**
```
[wecoza_nlq_table query_id="1" title="Active Agents" page_size="50" export="true"]
```

**Permissions:** Any logged-in user can view

---

## Security Model

### SQL Sandbox (`SQLSandbox.php`)

Every query — whether AI-generated, manually entered, or loaded from storage — passes through the sandbox before execution.

**Allowed:** `SELECT`, `WITH` (CTEs)

**Blocked (18 keywords):** `INSERT`, `UPDATE`, `DELETE`, `DROP`, `ALTER`, `TRUNCATE`, `CREATE`, `REPLACE`, `GRANT`, `REVOKE`, `EXECUTE`, `COPY`, `VACUUM`, `REINDEX`, `CLUSTER`, `COMMENT`, `LOCK`, `NOTIFY`

**Pattern Detection:**
- Multi-statement injection (`;` followed by write keywords)
- File operations (`INTO OUTFILE`, `LOAD_FILE`, `pg_read_file`, `lo_export`)
- DoS (`pg_sleep`)
- External access (`dblink`)
- SQL comments (`--`, `/* */`)

**Smart False-Positive Handling:**
- Column names like `updated_at` don't trigger `UPDATE` block
- Keywords inside string literals are ignored

**Max query length:** 10,000 characters

### AJAX Security

All 10 AJAX endpoints enforce:
- **Nonce verification** via `AjaxSecurity::requireNonce('wecoza_nlq_nonce')`
- **Capability check** — write operations require `manage_options`
- **Input sanitization** — `sanitize_text_field()`, `sanitize_textarea_field()`, `wp_unslash()`

### Soft Deletes

Queries are never hard-deleted. `deactivate()` sets `is_active = FALSE`. Can be reactivated.

---

## AI Integration

### OpenAI Configuration (Reused — No Duplication)

| Component | Source | How Used |
|---|---|---|
| `OpenAIConfig` | `Events/Support/OpenAIConfig.php` | API key, URL, model settings |
| `SchemaContext` | `Feedback/Support/SchemaContext.php` | All table schemas for all 4 modules |
| `wecoza_openai_api_key` | WP option (Settings → WeCoza Settings) | Already configured |

### `SQLGeneratorService`

- **Model:** `gpt-4.1`
- **Temperature:** `0.1` (low for precise SQL)
- **Timeout:** 45 seconds
- **Max tokens:** 2048

**System prompt includes:**
- Full database schema from `SchemaContext`
- PostgreSQL-specific syntax rules (ILIKE, ::date, CURRENT_DATE, JSONB operators)
- Read-only enforcement rules
- Response format: JSON with `sql` and `explanation` fields

**Module auto-detection:** Keyword matching against question text
- "agent", "facilitator", "sace" → Agents module
- "learner", "student", "portfolio" → Learners module
- "class", "schedule", "exam" → Classes module
- "client", "company", "site" → Clients module

**Refinement:** Conversation-style follow-up where user says "also sort by X" and AI modifies the existing query.

---

## AJAX Endpoints

| Action | Method | Capability | Description |
|---|---|---|---|
| `wecoza_nlq_generate_sql` | POST | `manage_options` | NL→SQL via OpenAI |
| `wecoza_nlq_refine_sql` | POST | `manage_options` | Refine existing SQL with AI |
| `wecoza_nlq_preview_sql` | POST | `manage_options` | Execute SQL and return results |
| `wecoza_nlq_save_query` | POST | `manage_options` | Save a new query |
| `wecoza_nlq_update_query` | POST | `manage_options` | Update existing query |
| `wecoza_nlq_delete_query` | POST | `manage_options` | Soft-delete (deactivate) a query |
| `wecoza_nlq_get_query` | POST | logged-in | Fetch single query details |
| `wecoza_nlq_list_queries` | POST | logged-in | List all queries (with search) |
| `wecoza_nlq_execute_query` | POST | logged-in | Execute a saved query by ID |
| `wecoza_nlq_get_categories` | POST | logged-in | Get distinct category list |

---

## UI Design

All views follow the **Phoenix design system** used across the WeCoza application:

- **Card containers:** `card shadow-none border` with `card-header border-bottom`
- **Headers:** `h4.text-body` with Bootstrap Icons
- **Search:** Phoenix search-box with magnifying glass SVG icon
- **Tables:** `table table-hover table-sm fs-9` with `thead.border-bottom` (no dark header)
- **ID badges:** `badge fs-10 badge-phoenix badge-phoenix-secondary` (#1, #2...)
- **Status badges:** `badge-phoenix-success` (active/green), `badge-phoenix-warning` (inactive/orange), `badge-phoenix-info` (category)
- **Action buttons:** `btn btn-sm btn-outline-secondary border-0` with icon only
- **Summary strip:** Horizontal stat row with `text-body-tertiary` and `border-end` separators
- **Pagination:** Chevron icons, primary active page badge
- **Alerts:** `alert-subtle-*` variants with icon prefix
- **Toasts:** Fixed position top-right, auto-dismiss, Phoenix alert styling

---

## Migration from POC (wecoza_3 plugin)

| Aspect | POC (wecoza_3) | NLQ Module (wecoza-core) |
|---|---|---|
| **Query Storage** | Remote MySQL (`ydcoza_wecoza_logger`) | PostgreSQL (same DB as all other data) |
| **Encoding** | Base64 encoded SQL | Plain text |
| **NL→SQL** | Not implemented | OpenAI GPT-4.1 with schema context |
| **SQL Safety** | None — any SQL could run | SQLSandbox enforces READ-ONLY |
| **CSRF Protection** | None | Nonce on every AJAX call |
| **Capability Check** | `manage_options` on admin page only | On every write endpoint |
| **Inline Editing** | `contenteditable` + raw SQL UPDATE | Removed (read-only tables) |
| **SQL Injection** | Table name injected directly in query | Column whitelisting via BaseRepository |
| **UI** | Basic Bootstrap table + DataTables default | Phoenix design system |
| **Shortcode** | `[wecoza_dynamic_table sql_id="X"]` | `[wecoza_nlq_table query_id="X"]` |
| **Admin UI** | WP Admin backend page | Frontend shortcode `[wecoza_nlq_manager]` |
| **Credentials** | Hardcoded in source | Uses existing `wecoza_db()` singleton |

---

## Phase 3 — Planned Improvements

- [ ] **Query caching** — Cache results for expensive queries with configurable TTL
- [ ] **Categories management** — Dedicated CRUD for categories with colors/icons
- [ ] **Usage analytics** — Dashboard showing most-used queries, execution times
- [ ] **CSV/Excel export** — Server-side export for large datasets
- [ ] **Scheduled queries** — Run queries on a cron and email results
- [ ] **Query sharing** — Share read-only query links between users
- [ ] **Custom capability** — `manage_nlq_queries` instead of `manage_options`
- [ ] **Query versioning** — Track SQL changes over time
- [ ] **AI conversation history** — Multi-turn refinement with full context
- [ ] **Query templates** — Pre-built queries for common requests

---

## Testing

### Integration Test Results (Phase 1)

```
=== SQLSandbox Tests ===
Valid SELECT:       PASS
Block DELETE:       PASS
Block DROP:         PASS
Block multi-stmt:   PASS
Allow updated_at:   PASS   (no false positive on column name)
Allow WITH/CTE:     PASS

=== NLQService Tests ===
Save query:         PASS (ID: 1)
Execute by ID:      PASS (5 rows)
Preview SQL:        PASS
Block bad save:     PASS (DELETE blocked)
```

### Live Test Results (Phase 2)

```
Question: "Show me all agents with their names, email and phone number who are currently active"
AI Response: SELECT agent_id, first_name, surname, email_address, tel_number 
             FROM agents WHERE status = 'active' LIMIT 100
Module detected: Agents
Rows returned: 16
Save: PASS (query saved, shortcode generated)
```
