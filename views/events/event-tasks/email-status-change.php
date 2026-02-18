<?php
/**
 * Email template: Class Status Changed (STATUS_CHANGE)
 *
 * Available variables via $payload:
 *   - new_row: Class data with new status
 *   - diff: {class_status: {old: '...', new: '...'}}
 *   - summary: AI summary record (optional)
 *   - event_data: Raw event data with metadata.status_transition
 *   - row: {event_id, class_id, changed_at, event_type}
 *
 * Available via presenter:
 *   - $fieldLabels: Map of DB field names to human-readable labels
 */
$payload     = $payload ?? [];
$fieldLabels = $fieldLabels ?? [];
$newRow      = $payload['new_row'] ?? [];
$diff        = $payload['diff'] ?? [];
$eventData   = $payload['event_data'] ?? [];
$summary     = $payload['summary'] ?? [];
$status      = strtolower((string) ($summary['status'] ?? 'pending'));
$summaryText = trim((string) ($summary['summary'] ?? ''));

$classCode    = $newRow['class_code'] ?? '';
$classSubject = $newRow['class_subject'] ?? '';
$classLabel   = $classCode !== '' ? $classCode . ($classSubject !== '' ? " - {$classSubject}" : '') : '';

// Extract status transition
$oldStatus = $diff['class_status']['old'] ?? $eventData['metadata']['old_status'] ?? '';
$newStatus = $diff['class_status']['new'] ?? $eventData['metadata']['new_status'] ?? $newRow['class_status'] ?? '';
?>
<div style="font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.5; color: #1f2933;">
    <h1 style="font-size: 20px; margin-bottom: 16px;">Class Status Changed<?php if ($classLabel !== ''): ?>: <?php echo esc_html($classLabel); ?><?php endif; ?></h1>

    <section style="margin-bottom: 20px;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <th style="text-align: left; padding: 6px; background: #e5e7eb; width: 160px;">Previous Status</th>
                <td style="padding: 6px; border-bottom: 1px solid #e5e7eb; color: #991b1b;"><?php echo esc_html((string) $oldStatus); ?></td>
            </tr>
            <tr>
                <th style="text-align: left; padding: 6px; background: #e5e7eb; width: 160px;">New Status</th>
                <td style="padding: 6px; border-bottom: 1px solid #e5e7eb; color: #166534; font-weight: 600;"><?php echo esc_html((string) $newStatus); ?></td>
            </tr>
        </table>
    </section>

    <?php if ($status === 'success' && $summaryText !== ''): ?>
        <section style="margin-bottom: 20px;">
            <h2 style="font-size: 16px; margin-bottom: 8px;">Summary</h2>
            <div style="background: #f3f4f6; padding: 12px; border-radius: 6px;">
                <?php echo nl2br(esc_html($summaryText)); ?>
            </div>
        </section>
    <?php elseif (!empty($diff) && is_array($diff)): ?>
        <?php
        // Show other changed fields (besides class_status which is already shown above)
        $otherChanges = $diff;
        unset($otherChanges['class_status']);
        ?>
        <?php if (!empty($otherChanges)): ?>
            <section style="margin-bottom: 20px;">
                <h2 style="font-size: 16px; margin-bottom: 8px;">Other Changes</h2>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <th style="text-align: left; padding: 6px; background: #e5e7eb; width: 160px;">Field</th>
                        <th style="text-align: left; padding: 6px; background: #e5e7eb;">Before</th>
                        <th style="text-align: left; padding: 6px; background: #e5e7eb;">After</th>
                    </tr>
                    <?php foreach ($otherChanges as $field => $change): ?>
                        <?php
                        $label = $fieldLabels[$field]
                            ?? \WeCoza\Events\Views\Presenters\NotificationEmailPresenter::fieldLabel($field);
                        $oldVal = is_array($change) ? ($change['old'] ?? '') : '';
                        $newVal = is_array($change) ? ($change['new'] ?? '') : '';
                        if (is_array($oldVal)) { $oldVal = wp_json_encode($oldVal); }
                        if (is_array($newVal)) { $newVal = wp_json_encode($newVal); }
                        ?>
                        <tr>
                            <td style="padding: 6px; border-bottom: 1px solid #e5e7eb; font-weight: 600;"><?php echo esc_html($label); ?></td>
                            <td style="padding: 6px; border-bottom: 1px solid #e5e7eb; color: #991b1b;"><?php echo esc_html((string) $oldVal); ?></td>
                            <td style="padding: 6px; border-bottom: 1px solid #e5e7eb; color: #166534;"><?php echo esc_html((string) $newVal); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </section>
        <?php endif; ?>
    <?php endif; ?>

    <p style="font-size: 12px; color: #6b7280;">This is an automated notification from WeCoza.</p>
</div>
