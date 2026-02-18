<?php
/**
 * Email template: Learner Added to Class (LEARNER_ADD)
 *
 * Available variables via $payload:
 *   - event_data: Raw event data {learner_id, action, class_id, metadata}
 *   - row: {event_id, class_id (actually learner_id for learner events), changed_at, event_type}
 *   - new_row: Class data (may be empty for learner events)
 */
$payload   = $payload ?? [];
$eventData = $payload['event_data'] ?? [];
$newRow    = $payload['new_row'] ?? [];

$learnerId  = $eventData['metadata']['learner_id'] ?? $eventData['learner_id'] ?? '';
$classId    = $eventData['class_id'] ?? $payload['row']['class_id'] ?? '';
$classCode  = $newRow['class_code'] ?? '';
$classSubject = $newRow['class_subject'] ?? '';
$classLabel = $classCode !== '' ? $classCode . ($classSubject !== '' ? " - {$classSubject}" : '') : "Class ID {$classId}";
?>
<div style="font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.5; color: #1f2933;">
    <h1 style="font-size: 20px; margin-bottom: 16px;">Learner Added to Class</h1>

    <section style="margin-bottom: 20px;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <th style="text-align: left; padding: 6px; background: #e5e7eb; width: 160px;">Class</th>
                <td style="padding: 6px; border-bottom: 1px solid #e5e7eb;"><?php echo esc_html($classLabel); ?></td>
            </tr>
            <tr>
                <th style="text-align: left; padding: 6px; background: #e5e7eb; width: 160px;">Learner ID</th>
                <td style="padding: 6px; border-bottom: 1px solid #e5e7eb;"><?php echo esc_html((string) $learnerId); ?></td>
            </tr>
        </table>
    </section>

    <p style="font-size: 12px; color: #6b7280;">This is an automated notification from WeCoza.</p>
</div>
