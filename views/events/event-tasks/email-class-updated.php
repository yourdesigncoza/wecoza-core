<?php
/**
 * Email template: Class Updated (CLASS_UPDATE)
 *
 * Renders a human-readable change summary — no raw JSON.
 *
 * Available variables via $payload:
 *   - new_row, old_row, diff: Class data and changes
 *   - summary: AI summary record {status, summary, ...}
 *   - row: {event_id, class_id, changed_at, event_type}
 *   - resolved_names: Display names resolved from IDs
 *
 * Available via presenter:
 *   - $fieldLabels: Map of DB field names to human-readable labels
 */
$payload      = $payload ?? [];
$fieldLabels  = $fieldLabels ?? [];
$diff         = $payload['diff'] ?? [];
$summary      = $payload['summary'] ?? [];
$status       = strtolower((string) ($summary['status'] ?? 'pending'));
$summaryText  = trim((string) ($summary['summary'] ?? ''));
$newRow       = $payload['new_row'] ?? [];
$oldRow       = $payload['old_row'] ?? [];
$resolvedNames = $payload['resolved_names'] ?? [];
$classCode    = $newRow['class_code'] ?? '';
$classSubject = $newRow['class_subject'] ?? '';
$classLabel   = $classCode !== '' ? $classCode . ($classSubject !== '' ? " - {$classSubject}" : '') : '';
$changedAt    = $payload['row']['changed_at'] ?? '';
$clientName   = $resolvedNames['client_name'] ?? ($newRow['client_name'] ?? '');

// ── Fields to never show in email ──
$skipFields = [
    'updated_at',
    'backup_agent_ids',
    'exam_learners',    // redundant with learner_ids
];

// ── Formatters for complex fields ──

/**
 * Format learner roster diff: show added/removed learners by name.
 */
function _wecoza_email_format_learners(array $oldLearners, array $newLearners): ?string
{
    $oldById = [];
    $newById = [];
    foreach ($oldLearners as $l) {
        $oldById[(int) ($l['id'] ?? 0)] = $l['name'] ?? "Learner #{$l['id']}";
    }
    foreach ($newLearners as $l) {
        $newById[(int) ($l['id'] ?? 0)] = $l['name'] ?? "Learner #{$l['id']}";
    }

    $added   = array_diff_key($newById, $oldById);
    $removed = array_diff_key($oldById, $newById);

    // Check for status/level changes among existing learners
    $statusChanges = [];
    $oldByIdFull = array_column($oldLearners, null, 'id');
    $newByIdFull = array_column($newLearners, null, 'id');
    foreach ($newByIdFull as $id => $newL) {
        if (!isset($oldByIdFull[$id])) {
            continue;
        }
        $oldL = $oldByIdFull[$id];
        $changes = [];
        if (($oldL['level'] ?? '') !== ($newL['level'] ?? '')) {
            $changes[] = "level: {$oldL['level']} → {$newL['level']}";
        }
        if (($oldL['status'] ?? '') !== ($newL['status'] ?? '')) {
            $changes[] = "status: {$oldL['status']} → {$newL['status']}";
        }
        if (!empty($changes)) {
            $statusChanges[] = ($newL['name'] ?? "Learner #{$id}") . ' (' . implode(', ', $changes) . ')';
        }
    }

    if (empty($added) && empty($removed) && empty($statusChanges)) {
        return null; // No meaningful change
    }

    $parts = [];
    if (!empty($added)) {
        $parts[] = '<strong>Added:</strong> ' . esc_html(implode(', ', $added));
    }
    if (!empty($removed)) {
        $parts[] = '<strong>Removed:</strong> ' . esc_html(implode(', ', $removed));
    }
    if (!empty($statusChanges)) {
        $parts[] = '<strong>Updated:</strong> ' . esc_html(implode('; ', $statusChanges));
    }
    return implode('<br>', $parts);
}

/**
 * Format schedule diff: show day/time changes and exception updates.
 */
function _wecoza_email_format_schedule(array $oldSched, array $newSched): ?string
{
    $parts = [];

    // Days changed
    $oldDays = $oldSched['selectedDays'] ?? [];
    $newDays = $newSched['selectedDays'] ?? [];
    sort($oldDays);
    sort($newDays);
    if ($oldDays !== $newDays) {
        $parts[] = '<strong>Days:</strong> ' . esc_html(implode(', ', $oldDays)) . ' → ' . esc_html(implode(', ', $newDays));
    }

    // Date range changed
    if (($oldSched['startDate'] ?? '') !== ($newSched['startDate'] ?? '')) {
        $parts[] = '<strong>Start date:</strong> ' . esc_html($oldSched['startDate'] ?? '–') . ' → ' . esc_html($newSched['startDate'] ?? '–');
    }
    if (($oldSched['endDate'] ?? '') !== ($newSched['endDate'] ?? '')) {
        $parts[] = '<strong>End date:</strong> ' . esc_html($oldSched['endDate'] ?? '–') . ' → ' . esc_html($newSched['endDate'] ?? '–');
    }

    // Per-day time changes
    $oldTimes = $oldSched['timeData']['perDayTimes'] ?? [];
    $newTimes = $newSched['timeData']['perDayTimes'] ?? [];
    $allDays  = array_unique(array_merge(array_keys($oldTimes), array_keys($newTimes)));
    sort($allDays);
    foreach ($allDays as $day) {
        $oldD = _wecoza_email_extract_duration($oldTimes[$day] ?? []);
        $newD = _wecoza_email_extract_duration($newTimes[$day] ?? []);
        $oldStart = _wecoza_email_extract_start($oldTimes[$day] ?? []);
        $newStart = _wecoza_email_extract_start($newTimes[$day] ?? []);
        $oldEnd   = _wecoza_email_extract_end($oldTimes[$day] ?? []);
        $newEnd   = _wecoza_email_extract_end($newTimes[$day] ?? []);

        if ($oldD !== $newD || $oldStart !== $newStart || $oldEnd !== $newEnd) {
            $oldStr = $oldStart && $oldEnd ? "{$oldStart}–{$oldEnd} ({$oldD}h)" : ($oldD ? "{$oldD}h" : '–');
            $newStr = $newStart && $newEnd ? "{$newStart}–{$newEnd} ({$newD}h)" : ($newD ? "{$newD}h" : '–');
            $parts[] = '<strong>' . esc_html($day) . ':</strong> ' . esc_html($oldStr) . ' → ' . esc_html($newStr);
        }
    }

    // Exception dates changed
    $oldExc = $oldSched['exceptionDates'] ?? [];
    $newExc = $newSched['exceptionDates'] ?? [];
    $oldExcDates = array_column($oldExc, 'reason', 'date');
    $newExcDates = array_column($newExc, 'reason', 'date');
    if ($oldExcDates !== $newExcDates) {
        $addedExc   = array_diff_key($newExcDates, $oldExcDates);
        $removedExc = array_diff_key($oldExcDates, $newExcDates);
        foreach ($addedExc as $date => $reason) {
            $parts[] = '<strong>Exception added:</strong> ' . esc_html($date) . ' (' . esc_html($reason) . ')';
        }
        foreach ($removedExc as $date => $reason) {
            $parts[] = '<strong>Exception removed:</strong> ' . esc_html($date) . ' (' . esc_html($reason) . ')';
        }
    }

    if (empty($parts)) {
        return null; // Only metadata changed (timestamps) — skip
    }
    return implode('<br>', $parts);
}

function _wecoza_email_extract_duration(array $dayData): string
{
    if (isset($dayData['duration'])) {
        return (string) $dayData['duration'];
    }
    if (isset($dayData['intervals'][0]['duration'])) {
        return (string) $dayData['intervals'][0]['duration'];
    }
    return '';
}

function _wecoza_email_extract_start(array $dayData): string
{
    return (string) ($dayData['start_time'] ?? $dayData['startTime'] ?? $dayData['intervals'][0]['startTime'] ?? '');
}

function _wecoza_email_extract_end(array $dayData): string
{
    return (string) ($dayData['end_time'] ?? $dayData['endTime'] ?? $dayData['intervals'][0]['endTime'] ?? '');
}

/**
 * Format event dates diff: show added/removed/changed events.
 */
function _wecoza_email_format_event_dates(array $oldDates, array $newDates): ?string
{
    $oldByDate = array_column($oldDates, null, 'date');
    $newByDate = array_column($newDates, null, 'date');

    $parts = [];
    $added   = array_diff_key($newByDate, $oldByDate);
    $removed = array_diff_key($oldByDate, $newByDate);

    // Status/description changes
    $changed = [];
    foreach ($newByDate as $date => $newEvt) {
        if (!isset($oldByDate[$date])) {
            continue;
        }
        $oldEvt = $oldByDate[$date];
        if (($oldEvt['status'] ?? '') !== ($newEvt['status'] ?? '')) {
            $changed[] = esc_html($date) . ': status ' . esc_html($oldEvt['status'] ?? '–') . ' → ' . esc_html($newEvt['status'] ?? '–');
        }
    }

    if (empty($added) && empty($removed) && empty($changed)) {
        return null;
    }

    if (!empty($added)) {
        foreach ($added as $date => $evt) {
            $parts[] = '<strong>Added:</strong> ' . esc_html($date) . ' — ' . esc_html($evt['type'] ?? '') . ': ' . esc_html($evt['description'] ?? '');
        }
    }
    if (!empty($removed)) {
        foreach ($removed as $date => $evt) {
            $parts[] = '<strong>Removed:</strong> ' . esc_html($date) . ' — ' . esc_html($evt['type'] ?? '') . ': ' . esc_html($evt['description'] ?? '');
        }
    }
    if (!empty($changed)) {
        foreach ($changed as $line) {
            $parts[] = '<strong>Changed:</strong> ' . $line;
        }
    }

    return implode('<br>', $parts);
}

// ── Build the formatted changes ──

$formattedChanges = [];

foreach ($diff as $field => $change) {
    if (in_array($field, $skipFields, true)) {
        continue;
    }

    $label  = $fieldLabels[$field]
        ?? \WeCoza\Events\Views\Presenters\NotificationEmailPresenter::fieldLabel($field);
    $oldVal = is_array($change) ? ($change['old'] ?? '') : '';
    $newVal = is_array($change) ? ($change['new'] ?? '') : '';

    // Complex field formatters
    if ($field === 'learner_ids' && is_array($oldVal) && is_array($newVal)) {
        $formatted = _wecoza_email_format_learners($oldVal, $newVal);
        if ($formatted !== null) {
            $formattedChanges[] = ['label' => $label, 'html' => $formatted, 'type' => 'html'];
        }
        continue;
    }

    if ($field === 'schedule_data' && is_array($oldVal) && is_array($newVal)) {
        $formatted = _wecoza_email_format_schedule($oldVal, $newVal);
        if ($formatted !== null) {
            $formattedChanges[] = ['label' => 'Schedule', 'html' => $formatted, 'type' => 'html'];
        }
        continue;
    }

    if ($field === 'event_dates' && is_array($oldVal) && is_array($newVal)) {
        $formatted = _wecoza_email_format_event_dates($oldVal, $newVal);
        if ($formatted !== null) {
            $formattedChanges[] = ['label' => $label, 'html' => $formatted, 'type' => 'html'];
        }
        continue;
    }

    // Skip any remaining arrays we don't have a formatter for
    if (is_array($oldVal) || is_array($newVal)) {
        continue;
    }

    // Simple scalar change
    $oldStr = (string) $oldVal;
    $newStr = (string) $newVal;
    if ($oldStr === $newStr) {
        continue;
    }

    $formattedChanges[] = [
        'label' => $label,
        'old'   => $oldStr,
        'new'   => $newStr,
        'type'  => 'scalar',
    ];
}
?>
<div style="font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.6; color: #1f2933; max-width: 640px;">
    <h1 style="font-size: 20px; margin-bottom: 4px;">Class Updated<?php if ($classLabel !== ''): ?>: <?php echo esc_html($classLabel); ?><?php endif; ?></h1>

    <?php if ($clientName !== ''): ?>
        <p style="margin: 0 0 4px 0; color: #6b7280;"><strong>Client:</strong> <?php echo esc_html($clientName); ?></p>
    <?php endif; ?>
    <?php if ($changedAt !== ''): ?>
        <p style="margin: 0 0 16px 0; color: #6b7280;"><strong>Updated:</strong> <?php echo esc_html(wp_date('j M Y, H:i', strtotime($changedAt))); ?></p>
    <?php endif; ?>

    <?php if ($status === 'success' && $summaryText !== ''): ?>
        <?php
        // Convert markdown summary to HTML: escape first, then transform **bold** and list items
        $summaryHtml = esc_html($summaryText);
        $summaryHtml = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $summaryHtml);
        // Convert "- item" lines into <li> wrapped in <ul>
        $summaryLines = explode("\n", $summaryHtml);
        $inList = false;
        $summaryHtml = '';
        foreach ($summaryLines as $line) {
            $trimmed = ltrim($line);
            if (preg_match('/^- (.+)$/', $trimmed, $m)) {
                if (!$inList) {
                    $summaryHtml .= '<ul style="margin: 4px 0; padding-left: 20px;">';
                    $inList = true;
                }
                $summaryHtml .= '<li style="margin-bottom: 2px;">' . $m[1] . '</li>';
            } else {
                if ($inList) {
                    $summaryHtml .= '</ul>';
                    $inList = false;
                }
                if (trim($line) !== '') {
                    $summaryHtml .= '<p style="margin: 4px 0;">' . $line . '</p>';
                }
            }
        }
        if ($inList) {
            $summaryHtml .= '</ul>';
        }
        ?>
        <section style="margin-bottom: 20px;">
            <h2 style="font-size: 16px; margin-bottom: 8px;">Summary</h2>
            <div style="background: #f3f4f6; padding: 12px; border-radius: 6px;">
                <?php echo $summaryHtml; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($formattedChanges)): ?>
        <section style="margin-bottom: 20px;">
            <h2 style="font-size: 16px; margin-bottom: 8px;">Changes</h2>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <th style="text-align: left; padding: 8px; background: #e5e7eb; width: 140px; vertical-align: top;">Field</th>
                    <th style="text-align: left; padding: 8px; background: #e5e7eb;">Details</th>
                </tr>
                <?php foreach ($formattedChanges as $change): ?>
                    <tr>
                        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: 600; vertical-align: top;"><?php echo esc_html($change['label']); ?></td>
                        <td style="padding: 8px; border-bottom: 1px solid #e5e7eb;">
                            <?php if ($change['type'] === 'scalar'): ?>
                                <span style="color: #991b1b; text-decoration: line-through;"><?php echo esc_html($change['old']); ?></span>
                                &nbsp;→&nbsp;
                                <span style="color: #166534;"><?php echo esc_html($change['new']); ?></span>
                            <?php else: ?>
                                <?php echo $change['html']; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </section>
    <?php elseif (empty($formattedChanges) && $summaryText === ''): ?>
        <p style="color: #6b7280;">Minor internal updates were made. No action required.</p>
    <?php endif; ?>

    <p style="font-size: 12px; color: #9ca3af; margin-top: 24px;">This is an automated notification from WeCoza. If you have questions, reply to this email.</p>
</div>
