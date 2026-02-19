---
status: resolved
trigger: "class-backup-agents-not-saved"
created: 2026-02-19T14:00:00Z
updated: 2026-02-19T14:30:00Z
---

## Current Focus

hypothesis: CONFIRMED - agent_replacements table was dropped; loadAgentReplacements() and saveAgentReplacements() in ClassModel reference it. backup_agent_ids saves correctly via JSONB already.
test: Completed - traced full save/load paths
expecting: Remove all agent_replacements table references from ClassModel, ClassRepository, FormDataProcessor
next_action: Apply fix - remove dead agent_replacements table code

## Symptoms

expected: Backup agent data (agent_id + date pairs) should be saved when creating a class
actual: Data is captured in the form but the agent_replacements table no longer exists, causing a DB error on both save and load
errors: [19-Feb-2026 14:53:33 UTC] WeCoza Core: Error loading agent replacements: SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "agent_replacements" does not exist LINE 3: FROM agent_replacements
reproduction: Create a new class with a backup agent assigned (date + agent). The form submits successfully but the backup agent data is lost.
timeline: Started after commit 96b2645 which dropped 23 legacy tables including agent_replacements

## Eliminated

(none yet)

## Evidence

- timestamp: 2026-02-19T14:00:00Z
  checked: debug.log error message
  found: Error loading agent replacements from agent_replacements table (relation does not exist)
  implication: Table was dropped but code still references it in both save and load paths

- timestamp: 2026-02-19T14:00:00Z
  checked: class JSON data dump
  found: backup_agent_ids:[{"date":"2026-03-12","agent_id":2}] is captured in form
  implication: Frontend captures data correctly; issue is backend persistence

- timestamp: 2026-02-19T14:10:00Z
  checked: ClassModel.php hydrate() + save() + update() methods
  found: hydrate() calls loadAgentReplacements() (SELECT FROM agent_replacements) on every class load. save()/update() call saveAgentReplacements() which fires DELETE/INSERT on agent_replacements table.
  implication: Every class load triggers the error. Saves fail silently for agent_replacements only (saveAgentReplacements returns true when $agentReplacements is empty, so class save still succeeds).

- timestamp: 2026-02-19T14:10:00Z
  checked: classes table schema (db/wecoza_db_full_dump_text.sql)
  found: classes table has backup_agent_ids JSONB column but NO agent_replacements column. agent_replacements was a separate table (dropped in 96b2645).
  implication: backup_agent_ids already saves correctly to classes table. agent_replacements is dead code.

- timestamp: 2026-02-19T14:12:00Z
  checked: ClassRepository getAllowedInsertColumns()
  found: 'agent_replacements' is listed as an allowed insert column but classes table has no such column.
  implication: This won't cause errors because ClassModel.save() doesn't include agent_replacements in the data array passed to repository. But it's misleading dead code.

- timestamp: 2026-02-19T14:12:00Z
  checked: FormDataProcessor.php lines 144-159
  found: Processes replacement_agent_ids[] and replacement_agent_dates[] form fields into $processed['agent_replacements'] array
  implication: Data is processed but has nowhere to go since the table is dropped. Can be removed.

- timestamp: 2026-02-19T14:13:00Z
  checked: Agent Replacements UI in update-class.php
  found: Uses replacement_agent_ids[]/replacement_agent_dates[] form fields (different from backup_agent_ids[]/backup_agent_dates[])
  implication: Two separate features: "Backup Agents" (works via JSONB) vs "Agent Replacements" (broken, table gone). Agent Replacements UI section is non-functional.

## Resolution

root_cause: ClassModel.loadAgentReplacements() and saveAgentReplacements() reference the dropped agent_replacements table. These methods fire on every class load/save. The backup_agent_ids feature is unaffected and works correctly - it saves to classes.backup_agent_ids JSONB column. The two features were confused: "Backup Agents" (JSONB, working) vs "Agent Replacements" (separate table, dropped).
fix: Removed all dead agent_replacements table references: deleted loadAgentReplacements() and saveAgentReplacements() from ClassModel, removed agentReplacements property and its getter/setter, removed agent_replacements from ClassRepository whitelist and formatForDisplay(), removed agent_replacements processing block from FormDataProcessor, removed setAgentReplacements() call from populateClassModel(). PHP lint passes on all 3 files.
verification: PHP lint passes. No remaining agent_replacements references in src/. backup_agent_ids save path (line 161 in ClassModel.save()) and repository whitelist are confirmed intact.
files_changed:
  - src/Classes/Models/ClassModel.php
  - src/Classes/Repositories/ClassRepository.php
  - src/Classes/Services/FormDataProcessor.php
  - views/classes/components/class-capture-partials/update-class.php (comment updated)
