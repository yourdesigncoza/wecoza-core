# Notification Email Templates — Per-Event-Type Design

## Problem

All notification emails use a single `email-summary.php` template. This causes:
- "New Class Created" emails show "WeCoza Class Change Notification" heading
- Internal AI details (Learner Alias Mapping, generation metrics) leak to recipients
- No way to tailor content per event type

## Decision

Separate templates per event type, routed by the presenter.

## Template Routing

| Event Type | Template | Email Subject |
|---|---|---|
| CLASS_INSERT | email-new-class.php | [WeCoza] New Class: {code} - {subject} |
| CLASS_UPDATE | email-class-updated.php | [WeCoza] Class Updated: {code} - {subject} |
| CLASS_DELETE | email-class-deleted.php | [WeCoza] Class Deleted: {code} - {subject} |
| LEARNER_ADD | email-learner-added.php | [WeCoza] Learner Added to {code} - {subject} |
| LEARNER_REMOVE | email-learner-removed.php | [WeCoza] Learner Removed from {code} - {subject} |
| STATUS_CHANGE | email-status-change.php | [WeCoza] Status Changed: {code} - {subject} |
| (unknown) | email-summary.php (fallback) | [WeCoza] Class {op}: {id} |

## Template Content

### email-new-class.php (CLASS_INSERT)
- Heading: "New Class Created"
- Class details table: code, subject, status, start/end dates, schedule, learner count, agent, client
- No AI summary, no alias map, no metrics
- Footer

### email-class-updated.php (CLASS_UPDATE)
- Heading: "Class Updated"
- AI summary (if status=success, omitted entirely otherwise)
- Before/after diff table with human-readable field labels
- No alias map, no metrics
- Footer

### email-class-deleted.php (CLASS_DELETE)
- Heading: "Class Deleted"
- Final state table: code, subject, status, dates, learner count
- Note: "This class has been removed from the system."
- Footer

### email-learner-added.php (LEARNER_ADD)
- Heading: "Learner Added to Class"
- Class reference (code + subject, one-liner)
- Learner name + ID
- Footer

### email-learner-removed.php (LEARNER_REMOVE)
- Heading: "Learner Removed from Class"
- Class reference (code + subject)
- Learner name + ID
- Footer

### email-status-change.php (STATUS_CHANGE)
- Heading: "Class Status Changed"
- Class reference (code + subject)
- Status transition: "Previous → New"
- AI summary if available, diff table as fallback
- Footer

## Files to Modify

1. **NotificationEmailPresenter.php** — Add `resolveTemplate()`, update `present()`, add field label mapping, update subject lines for learner/status events
2. **NotificationProcessor.php** — Skip AI enrichment for CLASS_INSERT (schedule email directly)
3. **NotificationEmailer.php** — Pass `event_type` to presenter context

## Files to Create

6 new templates in `views/events/event-tasks/`

## Preserved

`email-summary.php` stays as-is (fallback for unknown event types).

## Shared Conventions

- Inline CSS (email client compatibility)
- Same footer across all templates
- Field label helper for diff tables (start_date → "Start Date")
