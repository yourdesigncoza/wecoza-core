# S03 — Class & Agent History UI

**Goal:** Add collapsible history sections to single class and single agent display pages. Data loaded via AJAX using the `wecoza_get_entity_history` endpoint from S02.

**Demo:** On single class page, a "Relationship History" accordion loads timeline tables for agents, learners, QA visits, events, and notes. On single agent page, similar accordion for classes, subjects, QA visits, and absences.

## Design Decisions
- D020: Clean Bootstrap tables/lists, NOT interactive timelines
- History section uses collapsible accordion cards matching existing page style
- AJAX-loaded on accordion open (lazy load, not on page load)
- Reusable JS module for both entity types

## Tasks

- [x] T01: History UI components — PHP views and JS for class and agent history sections
- [x] T02: Wire history sections into existing single-class and single-agent display views

## Verification

- PHP syntax check on all new files
- Visual check of rendered HTML structure (tables, accordions)
- JS file syntax validation
- Test suite still passing (101 + 43 checks)
