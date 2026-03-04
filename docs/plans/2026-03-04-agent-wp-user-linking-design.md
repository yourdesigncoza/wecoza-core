# Agent → WordPress User Linking Design

**Date:** 2026-03-04
**Context:** Phase 54 (Agent Foundation) — ties into 54-02 Agent Data Layer + AJAX Guards
**Requirements:** AGT-01 through AGT-04

## Problem

Agents exist only in PostgreSQL (`agents` table). No WordPress user account is created, so agents cannot log in or use capability-gated features like attendance capture.

## Decision

**Approach B: Agent form is the single source of truth.**

- Admin controls agent email via `[wecoza_capture_agents]`
- WP user is auto-created on agent save, assigned `wp_agent` role
- Email field is hidden/disabled on WP profile for `wp_agent` users
- One-way sync: agent form → WP user (not reverse)

**Rationale:** Client manually creates company emails for agents. Admin must control the email, not the agent.

## Architecture

```
Admin creates/edits agent via [wecoza_capture_agents]
    ↓
AgentService::handleAgentFormSubmission()
    ├─ Save to agents table (existing)
    ├─ Create or update WP user
    │   ├─ wp_insert_user() with wp_agent role + random password
    │   ├─ wp_new_user_notification() → password reset email
    │   └─ Store wp_user_id in agents table
    └─ On email change: wp_update_user() syncs email to WP user

Agent logs in to WordPress
    ├─ Can change: password, display name
    ├─ BLOCKED: email field hidden/disabled
    └─ Uses capture_attendance capability
```

## Components

### 1. DDL — wp_user_id column

```sql
ALTER TABLE agents ADD COLUMN IF NOT EXISTS wp_user_id INTEGER;
CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS idx_agents_wp_user_id_unique
    ON agents (wp_user_id)
    WHERE wp_user_id IS NOT NULL AND status <> 'deleted';
```

### 2. AgentService — WP user lifecycle

On agent create:
- `wp_insert_user(email, random_password, role: wp_agent)`
- `wp_new_user_notification()` sends password reset email
- Store returned user ID as `wp_user_id`

On agent email change:
- `wp_update_user(wp_user_id, new_email)`

On agent deactivation/deletion:
- Remove `wp_agent` role from WP user (blocks attendance access)

### 3. WP Profile lockdown — wp_agent role

- Hide email field on profile page via `show_user_profile` / `edit_user_profile` hooks
- Safety net: `user_profile_update_errors` filter prevents email change via POST

### 4. Bulk migration — existing agents

One-time script (WP-CLI or admin action):
- Loop active agents where `wp_user_id IS NULL`
- Create WP user, assign `wp_agent` role, store `wp_user_id`
- **No email notifications** (current agents are demo data)
- Skip agents with duplicate/invalid emails, log warnings
- If email already exists as WP user: link to existing, add `wp_agent` role

## Edge Cases

| Scenario | Handling |
|----------|----------|
| Agent email already exists as WP user | Link to existing user, add wp_agent role |
| Agent deleted/deactivated | Remove wp_agent role from WP user |
| Agent has no email | Skip WP user creation, log warning |
| WP user manually deleted | wp_user_id becomes stale — recreate on next agent edit |
| Duplicate email across agents | Unique constraint on agents.email_address prevents this |

## Scope Impact on Phase 54

Phase 54-02 already includes:
- DDL for `wp_user_id` column (Task 3 — pending user DDL execution)
- `findByWpUserId()` in AgentRepository
- AJAX capability guards on attendance handlers

**New scope from this design** (beyond 54-02):
- Auto-create WP user in AgentService on agent save
- Email sync from agent form to WP user
- WP profile email lockdown for wp_agent role
- Bulk migration of existing agents
- Agent deactivation → role removal
