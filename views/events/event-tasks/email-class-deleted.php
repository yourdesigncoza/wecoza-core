<?php
/**
 * Email template: Class Deleted (CLASS_DELETE)
 *
 * Available variables via $payload:
 *   - new_row: Final class state at deletion
 *   - row: {event_id, class_id, changed_at, event_type}
 */
$payload       = $payload ?? [];
$newRow        = $payload['new_row'] ?? [];
$resolvedNames = $payload['resolved_names'] ?? [];

$details = [
    'Class Code'   => $newRow['class_code'] ?? '',
    'Subject'      => $newRow['class_subject'] ?? '',
    'Class Type'   => $newRow['class_type'] ?? '',
    'Status'       => $newRow['class_status'] ?? '',
    'Start Date'   => $newRow['start_date'] ?? '',
    'End Date'     => $newRow['end_date'] ?? '',
    'Schedule'     => $newRow['schedule_pattern'] ?? '',
    'Client'       => $resolvedNames['client_name'] ?? '',
    'Site'         => $resolvedNames['site_name'] ?? '',
    'Site Address' => $resolvedNames['site_address'] ?? '',
    'Agent'        => $resolvedNames['agent_name'] ?? '',
];

// Count learners
$learnerIds = $newRow['learner_ids'] ?? [];
if (is_string($learnerIds)) {
    $decoded = json_decode($learnerIds, true);
    $learnerIds = is_array($decoded) ? $decoded : [];
}
$learnerCount = count($learnerIds);
?>
<div style="font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.5; color: #1f2933;">
    <h1 style="font-size: 20px; margin-bottom: 16px; color: #991b1b;">Class Deleted</h1>

    <section style="margin-bottom: 20px;">
        <h2 style="font-size: 16px; margin-bottom: 8px;">Final Class State</h2>
        <table style="width: 100%; border-collapse: collapse;">
            <?php foreach ($details as $label => $value): ?>
                <?php if ($value === '' || $value === null) { continue; } ?>
                <tr>
                    <th style="text-align: left; padding: 6px; background: #e5e7eb; width: 160px;"><?php echo esc_html($label); ?></th>
                    <td style="padding: 6px; border-bottom: 1px solid #e5e7eb;"><?php echo esc_html((string) $value); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($learnerCount > 0): ?>
                <tr>
                    <th style="text-align: left; padding: 6px; background: #e5e7eb; width: 160px;">Learner Count</th>
                    <td style="padding: 6px; border-bottom: 1px solid #e5e7eb;"><?php echo esc_html((string) $learnerCount); ?></td>
                </tr>
            <?php endif; ?>
        </table>
    </section>

    <p style="margin-bottom: 16px; color: #991b1b;">This class has been removed from the system.</p>
    <p style="font-size: 12px; color: #6b7280;">This is an automated notification from WeCoza.</p>
</div>
