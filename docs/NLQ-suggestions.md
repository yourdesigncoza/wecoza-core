# Improvements for WeCoza NLQ → PostgreSQL SQL Generator

This document consolidates architectural and prompt-engineering suggestions for improving the reliability, safety, and accuracy of the WeCoza natural-language-to-SQL system.

---

# 1. Add Table Aliases in Reformulated Queries

Current reformulation example:

Use Tables: agents

Improved:

Use Tables: agents (alias a)

For multi-table joins:

Use Tables: agent_orders (alias ao), agents (alias a), classes (alias c)

Why: LLMs learn alias patterns from examples, which improves SQL consistency.

---

# 2. Explicitly Teach Join Keys in Reformulation

Example improvement:

List agent_orders joined with agents and classes.

Join agent_orders ao → agents a ON ao.agent_id = a.agent_id  
Join agent_orders ao → classes c ON ao.class_id = c.class_id

Use Tables: agent_orders (ao), agents (a), classes (c)

Why: Prevents the model from guessing incorrect joins.

---

# 3. Include One Failure Example

INPUT:
Show agent subject

BAD SQL:
SELECT subject FROM classes

CORRECT SQL:
SELECT class_subject FROM classes

Why: Prevents hallucinated column names.

---

# 4. Add JSONB Examples

Example:

SELECT class_id, session_date
FROM class_attendance_sessions
WHERE jsonb_array_length(learner_data) > 10
LIMIT 100;

Why: Teaches correct JSONB operations.

---

# 5. Prefer SQL Standard <> Instead of !=

Example:

WHERE cmt.delivery_status <> 'delivered'

Why: <> is SQL standard.

---

# 6. Improve Schema With Foreign Keys

Example:

'foreign_keys' => [
    'preferred_working_area_1' => 'locations.location_id',
    'preferred_working_area_2' => 'locations.location_id',
    'preferred_working_area_3' => 'locations.location_id'
]

Why: Enables the model to infer joins directly.

---

# 7. Add Column Types to Schema

Example:

'columns' => [
    'agent_id' => ['type' => 'integer', 'description' => 'Primary key'],
    'first_name' => ['type' => 'text', 'description' => 'Agent first name'],
    'sace_registration_date' => ['type' => 'date', 'description' => 'SACE registration date']
]

Why: Helps generate correct SQL functions and comparisons.

---

# 8. Convert Relationship Strings to Structured Format

Current:

classes.client_id → clients.client_id

Improved:

[
  'from_table' => 'classes',
  'from_column' => 'client_id',
  'to_table' => 'clients',
  'to_column' => 'client_id'
]

Why: Easier for LLMs to interpret relationships.

---

# 9. Add Relationship Type Metadata

Example:

'type' => 'many_to_one'

Why: Helps the model understand table cardinality.

---

# 10. Define Global Table Aliases

Example:

'aliases' => [
  'classes' => 'c',
  'agents' => 'a',
  'clients' => 'cl',
  'sites' => 's'
]

Why: Produces consistent SQL.

---

# 11. Add SQL Validation Before Execution

Example logic:

- Reject INSERT/UPDATE/DELETE
- Reject unknown tables
- Reject unknown columns

Why: Prevents hallucinated or malicious queries.

---

# 12. Two-Stage SQL Generation

Stage 1: Determine relevant tables and columns

Stage 2: Generate SQL using only those columns

Why: Dramatically reduces hallucinated fields.

---

# 13. Optional Architecture Improvement

Recommended pipeline:

User Question  
↓  
LLM selects relevant tables  
↓  
Send only those tables to SQL generator  
↓  
Generate SQL

Benefits:
- lower token usage
- faster responses
- fewer hallucinations

---

# 14. Current System Strengths

The system already includes strong design practices:

- schema grounding
- few-shot SQL examples
- join relationship context
- SELECT-only enforcement
- LIMIT safeguards
- structured JSON responses

These practices align with enterprise NLQ SQL systems.

---

# Final Recommendation

The highest-impact improvements:

1. Structured relationships
2. Column types in schema
3. Explicit join hints
4. SQL validation layer
5. Two-stage SQL generation
