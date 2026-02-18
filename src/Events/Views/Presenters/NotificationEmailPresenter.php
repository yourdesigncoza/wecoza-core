<?php
declare(strict_types=1);

namespace WeCoza\Events\Views\Presenters;

use function esc_html;
use function file_exists;
use function is_string;
use function ob_get_clean;
use function ob_start;
use function sprintf;
use function strtolower;
use function strtoupper;
use function str_replace;
use function ucwords;
use function trim;
use function wecoza_plugin_path;
use function wp_json_encode;
use function wp_strip_all_tags;
use const JSON_PRETTY_PRINT;

final class NotificationEmailPresenter
{
    /** Map DB field names to human-readable labels for diff tables */
    private const FIELD_LABELS = [
        'class_status'       => 'Status',
        'start_date'         => 'Start Date',
        'end_date'           => 'End Date',
        'original_start_date'=> 'Original Start Date',
        'schedule_pattern'   => 'Schedule Pattern',
        'event_dates'        => 'Event Dates',
        'learner_ids'        => 'Learner Roster',
        'class_facilitator'  => 'Facilitator',
        'class_coach'        => 'Coach',
        'class_assessor'     => 'Assessor',
        'client_id'          => 'Client',
        'class_type'         => 'Class Type',
        'class_subject'      => 'Subject',
        'class_code'         => 'Class Code',
    ];

    /** Map event_type to template file */
    private const TEMPLATE_MAP = [
        'CLASS_INSERT'   => 'email-new-class.php',
        'CLASS_UPDATE'   => 'email-class-updated.php',
        'CLASS_DELETE'   => 'email-class-deleted.php',
        'LEARNER_ADD'    => 'email-learner-added.php',
        'LEARNER_REMOVE' => 'email-learner-removed.php',
        'STATUS_CHANGE'  => 'email-status-change.php',
    ];

    /**
     * @param array<string,mixed> $context
     * @return array{subject:string,body:string,headers:array<int,string>}
     */
    public function present(array $context): array
    {
        $eventType = strtoupper((string) ($context['event_type'] ?? ''));
        $operation = strtoupper((string) ($context['operation'] ?? ''));
        $newRow    = $context['new_row'] ?? [];
        $classId   = (string) ($context['row']['class_id'] ?? '');
        $classCode = (string) ($newRow['class_code'] ?? '');
        $classSubject = (string) ($newRow['class_subject'] ?? '');

        $classLabel = $classCode !== ''
            ? $classCode . ($classSubject !== '' ? " - {$classSubject}" : '')
            : "ID {$classId}";

        $subject = $this->buildSubject($eventType, $operation, $classLabel, $classId, $context);

        $html  = $this->renderHtml($eventType, $context);
        $body  = $html;

        return [
            'subject' => $subject,
            'body'    => $body,
            'headers' => ['Content-Type: text/html; charset=UTF-8'],
        ];
    }

    /**
     * Build the email subject line based on event type.
     */
    private function buildSubject(
        string $eventType,
        string $operation,
        string $classLabel,
        string $classId,
        array $context
    ): string {
        return match ($eventType) {
            'CLASS_INSERT'   => sprintf('[WeCoza] New Class: %s', $classLabel),
            'CLASS_UPDATE'   => sprintf('[WeCoza] Class Updated: %s', $classLabel),
            'CLASS_DELETE'   => sprintf('[WeCoza] Class Deleted: %s', $classLabel),
            'LEARNER_ADD'    => sprintf('[WeCoza] Learner Added to %s', $classLabel),
            'LEARNER_REMOVE' => sprintf('[WeCoza] Learner Removed from %s', $classLabel),
            'STATUS_CHANGE'  => sprintf('[WeCoza] Status Changed: %s', $classLabel),
            default => match ($operation) {
                'INSERT' => sprintf('[WeCoza] New Class: %s', $classLabel),
                'UPDATE' => sprintf('[WeCoza] Class Updated: %s', $classLabel),
                'DELETE' => sprintf('[WeCoza] Class Deleted: %s', $classLabel),
                default  => sprintf('[WeCoza] Class %s: %s', strtolower($operation), $classId),
            },
        };
    }

    /**
     * Resolve which template file to use for the given event type.
     */
    private function resolveTemplate(string $eventType): string
    {
        $file = self::TEMPLATE_MAP[$eventType] ?? null;

        if ($file !== null) {
            $path = wecoza_plugin_path("views/events/event-tasks/{$file}");
            if (file_exists($path)) {
                return $path;
            }
        }

        // Fallback to generic template
        return wecoza_plugin_path('views/events/event-tasks/email-summary.php');
    }

    /**
     * Render the HTML email body using the appropriate template.
     */
    private function renderHtml(string $eventType, array $context): string
    {
        $template = $this->resolveTemplate($eventType);
        if (!file_exists($template)) {
            return wp_json_encode($context, JSON_PRETTY_PRINT);
        }

        ob_start();
        $payload     = $context;
        $fieldLabels = self::FIELD_LABELS;
        include $template;
        $output = ob_get_clean();

        return is_string($output) ? $output : '';
    }

    /**
     * Get a human-readable label for a database field name.
     *
     * Public so templates can call it directly if needed.
     */
    public static function fieldLabel(string $field): string
    {
        if (isset(self::FIELD_LABELS[$field])) {
            return self::FIELD_LABELS[$field];
        }

        // Convert snake_case to Title Case as fallback
        return ucwords(str_replace('_', ' ', $field));
    }
}
