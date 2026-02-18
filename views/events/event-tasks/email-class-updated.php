<?php
/**
 * Email template: Class Updated (CLASS_UPDATE)
 *
 * Available variables via $payload:
 *   - new_row, old_row, diff: Class data and changes
 *   - summary: AI summary record {status, summary, ...}
 *   - row: {event_id, class_id, changed_at, event_type}
 *
 * Available via presenter:
 *   - $fieldLabels: Map of DB field names to human-readable labels
 */
$payload     = $payload ?? [];
$fieldLabels = $fieldLabels ?? [];
$diff        = $payload['diff'] ?? [];
$summary     = $payload['summary'] ?? [];
$status      = strtolower((string) ($summary['status'] ?? 'pending'));
$summaryText = trim((string) ($summary['summary'] ?? ''));
$newRow      = $payload['new_row'] ?? [];
$classCode   = $newRow['class_code'] ?? '';
$classSubject = $newRow['class_subject'] ?? '';

$classLabel = $classCode !== '' ? $classCode . ($classSubject !== '' ? " - {$classSubject}" : '') : '';
?>
<div style="font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.5; color: #1f2933;">
    <h1 style="font-size: 20px; margin-bottom: 16px;">Class Updated<?php if ($classLabel !== ''): ?>: <?php echo esc_html($classLabel); ?><?php endif; ?></h1>

    <?php if ($status === 'success' && $summaryText !== ''): ?>
        <section style="margin-bottom: 20px;">
            <h2 style="font-size: 16px; margin-bottom: 8px;">Summary</h2>
            <div style="background: #f3f4f6; padding: 12px; border-radius: 6px;">
                <?php echo nl2br(esc_html($summaryText)); ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($diff) && is_array($diff)): ?>
        <section style="margin-bottom: 20px;">
            <h2 style="font-size: 16px; margin-bottom: 8px;">Changes</h2>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <th style="text-align: left; padding: 6px; background: #e5e7eb; width: 160px;">Field</th>
                    <th style="text-align: left; padding: 6px; background: #e5e7eb;">Before</th>
                    <th style="text-align: left; padding: 6px; background: #e5e7eb;">After</th>
                </tr>
                <?php foreach ($diff as $field => $change): ?>
                    <?php
                    $label = $fieldLabels[$field]
                        ?? \WeCoza\Events\Views\Presenters\NotificationEmailPresenter::fieldLabel($field);
                    $oldVal = is_array($change) ? ($change['old'] ?? '') : '';
                    $newVal = is_array($change) ? ($change['new'] ?? '') : '';
                    // Stringify arrays/objects for display
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

    <p style="font-size: 12px; color: #6b7280;">This is an automated notification from WeCoza.</p>
</div>
