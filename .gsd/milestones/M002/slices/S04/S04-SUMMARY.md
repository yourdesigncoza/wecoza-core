---
id: S04
milestone: M002
provides:
  - Collapsible "Relationship History" accordion on single learner display page
  - Collapsible "Relationship History" accordion on client update form page
key_files:
  - src/Learners/Shortcodes/learner-single-display-shortcode.php
  - views/clients/components/client-update-form.view.php
  - src/Clients/Controllers/ClientsController.php
key_decisions:
  - No single-client display page exists — history added to client update form instead
  - Client history section placed after the form, outside the form tag
  - Learner history section placed before Documents section
completed_at: 2026-03-12
---

# S04: Learner & Client History UI

**Added history sections to single learner display and client update form — reusing entity-history.js from S03.**

## What Was Built

1. **Single learner display** (`learner-single-display-shortcode.php`):
   - History component added before Documents section
   - entity-history.js enqueued and localized with learner_id

2. **Client update form** (`client-update-form.view.php`):
   - No single-client display page exists — added after the form
   - entity-history.js enqueued in ClientsController when client_id is present

## Verification

- All PHP files pass syntax check
- 144 automated checks still passing
