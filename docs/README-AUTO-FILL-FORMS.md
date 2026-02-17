# Dev Test Toolbar — Quick Reference

## Prerequisites

- `WP_DEBUG` must be `true` in `wp-config.php`
- Logged in as admin (`manage_options` capability)

## Toolbar

A floating dark toolbar appears at bottom-right of any page. Three buttons:

| Button | Action |
|--------|--------|
| **Fill** | Populates the current form with realistic SA test data |
| **Fill + Submit** | Fills all fields then auto-clicks submit |
| **Wipe All** | Truncates 34 transactional PG tables, preserves reference data, clears WP transients |

Toolbar is draggable and collapsible. Shows which form was detected.

## Seeding Order (Dependency Chain)

```
1. Locations     → /locations-capture page
2. Clients       → /clients-capture page (needs locations for Province/Town/Suburb)
3. Learners      → /learners-form page
4. Agents        → /agents-capture page
5. Classes       → /classes-capture page (needs clients, locations, learners, agents)
```

**Recommended:** Fill + Submit 3-5 times per form before moving to the next.

## Per-Form Notes

### Location Form
- Fill populates street, suburb, town, province, postal code, lat/lng
- Auto-clicks "Check Duplicates" to reveal the submit button
- Submit button only appears after duplicate check completes

### Client Form
- Cascading dropdowns (Province → Town → Suburb) are synchronous — no wait needed
- Postal code and street address auto-populate from suburb selection
- Sub-client checkbox left unchecked by default

### Learner Form
- Dropdowns load via AJAX on page load — toolbar waits for them automatically
- Sets ID type to "SA ID" and fills a valid 13-digit number (Luhn checksum)
- Assessment status set to "Assessed" to reveal conditional fields
- Employment status randomized; if "Employed", employer dropdown is filled

### Agent Form
- 40+ fields including banking details, SACE, quantum scores
- File uploads (criminal record, signed agreement) are **skipped** — fill manually
- Province is a static dropdown (no cascading)

### Class Form
- Client → Site is the **only async cascade** (AJAX). Toolbar waits for it
- Class Type → Subject also cascades. Toolbar waits and enables the subject dropdown
- Schedule pattern and day checkboxes are filled randomly
- Post-creation fields (learners, QA visits, notes, agents) require manual entry after save

## Wipe Behaviour

**Truncated (34 tables):** locations, agents, agent_meta, agent_notes, agent_absences, agent_orders, agent_replacements, clients, client_communications, learners, learner_*, classes, class_*, sites, attendance_registers, collections, deliveries, employers, exams, exam_results, files, history, latest_document, progress_reports, qa_visits

**Preserved (reference data):** class_types, class_type_subjects, products, user_roles, user_permissions, users, sites_migration_backup, sites_address_audit

Sequences reset to 1 (`RESTART IDENTITY CASCADE`). WP transients with `wecoza_` prefix cleared.

## File Structure

```
assets/js/dev/
  dev-toolbar.js                    # Toolbar UI + form detection
  form-fillers/
    data-pools.js                   # SA data arrays
    generators.js                   # SA ID, phone, email, date generators
    location-filler.js              # Location form
    client-filler.js                # Client form (sync cascade)
    learner-filler.js               # Learner form (AJAX dropdowns)
    agent-filler.js                 # Agent form (40+ fields)
    class-filler.js                 # Class form (async cascades)

src/Dev/
  DevToolbarController.php          # Script enqueue (WP_DEBUG only)
  WipeDataHandler.php               # AJAX truncate handler
```

## Disabling

Set `WP_DEBUG` to `false`. No scripts load, no AJAX endpoints respond.
