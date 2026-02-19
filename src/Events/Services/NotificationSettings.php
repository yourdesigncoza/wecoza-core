<?php
declare(strict_types=1);

namespace WeCoza\Events\Services;

if (!defined('ABSPATH')) {
    exit;
}

use WeCoza\Events\Enums\EventType;

use function get_option;
use function update_option;
use function is_email;
use function is_string;
use function is_array;
use function trim;
use function strtoupper;
use function array_filter;
use function array_map;
use function array_values;
use function wecoza_log;

/**
 * Notification recipient settings service
 *
 * Manages email recipients per event type with support for:
 * - Multiple recipients per event type
 * - Legacy operation mapping (INSERT/UPDATE/DELETE)
 * - Email validation
 * - Backward compatibility with single-recipient options
 *
 * @since 1.2.0
 */
final class NotificationSettings
{
    /**
     * Option key for multi-recipient settings
     */
    private const RECIPIENTS_OPTION = 'wecoza_notification_recipients';

    /**
     * Legacy option keys for backward compatibility
     */
    private const LEGACY_OPTIONS = [
        'INSERT' => 'wecoza_notification_class_created',
        'UPDATE' => 'wecoza_notification_class_updated',
        'DELETE' => 'wecoza_notification_class_deleted',
    ];

    /**
     * Get recipients for a given event type
     *
     * @param EventType $type Event type
     * @return array<int, string> Array of validated email addresses
     */
    public function getRecipientsByEventType(EventType $type): array
    {
        $eventTypeValue = $type->value;
        $recipients = $this->getAllRecipientSettings();

        if (isset($recipients[$eventTypeValue]) && is_array($recipients[$eventTypeValue])) {
            return $this->filterValidEmails($recipients[$eventTypeValue]);
        }

        // Fallback to legacy option lookup
        $operation = $this->mapEventTypeToOperation($type);
        $legacyEmail = $this->resolveLegacyRecipient($operation);

        if ($legacyEmail !== null) {
            return [$legacyEmail];
        }

        // Log warning if notifications enabled but no recipients
        if (defined('WP_DEBUG') && WP_DEBUG) {
            wecoza_log("NotificationSettings: No recipients configured for event type {$eventTypeValue}", 'warning');
        }

        return [];
    }

    /**
     * Get recipients for an event type string (supports EventType values and legacy operations)
     *
     * @param string $eventType Event type value or legacy operation (INSERT/UPDATE/DELETE)
     * @return array<int, string> Array of validated email addresses
     */
    public function getRecipientsForEventType(string $eventType): array
    {
        $eventType = strtoupper($eventType);

        // Try as EventType value first
        $type = EventType::tryFrom($eventType);
        if ($type !== null) {
            return $this->getRecipientsByEventType($type);
        }

        // Map legacy operation to EventType
        $mappedType = $this->mapOperationToEventType($eventType);
        if ($mappedType !== null) {
            return $this->getRecipientsByEventType($mappedType);
        }

        return [];
    }

    /**
     * Backward compatible single-recipient lookup (deprecated)
     *
     * @deprecated Use getRecipientsForEventType() instead
     * @param string $operation Legacy operation (INSERT/UPDATE/DELETE)
     * @return string|null First recipient email or null
     */
    public function getRecipientForOperation(string $operation): ?string
    {
        $operation = strtoupper($operation);

        // Try mapped EventType first
        $type = $this->mapOperationToEventType($operation);
        if ($type !== null) {
            $recipients = $this->getRecipientsByEventType($type);
            return $recipients[0] ?? null;
        }

        // Fallback to legacy option
        return $this->resolveLegacyRecipient($operation);
    }

    /**
     * Set recipients for a specific event type
     *
     * @param EventType $type Event type
     * @param array<int, string> $emails Array of email addresses
     * @return bool Success
     */
    public function setRecipientsForEventType(EventType $type, array $emails): void
    {
        $recipients = $this->getAllRecipientSettings();
        $recipients[$type->value] = $this->filterValidEmails($emails);
        update_option(self::RECIPIENTS_OPTION, $recipients);
    }

    /**
     * Get all recipient settings
     *
     * @return array<string, array<int, string>> Map of event type to email array
     */
    public function getAllRecipientSettings(): array
    {
        $option = get_option(self::RECIPIENTS_OPTION, []);

        if (!is_array($option)) {
            return [];
        }

        return $option;
    }

    /**
     * Map legacy operation to EventType
     *
     * @param string $operation Legacy operation (INSERT/UPDATE/DELETE)
     * @return EventType|null Mapped event type or null
     */
    public function mapOperationToEventType(string $operation): ?EventType
    {
        $operation = strtoupper($operation);

        return match ($operation) {
            'INSERT' => EventType::CLASS_INSERT,
            'UPDATE' => EventType::CLASS_UPDATE,
            'DELETE' => EventType::CLASS_DELETE,
            default => null,
        };
    }

    /**
     * Map EventType to legacy operation string
     *
     * @param EventType $type Event type
     * @return string Operation string (INSERT/UPDATE/DELETE)
     */
    private function mapEventTypeToOperation(EventType $type): string
    {
        return match ($type) {
            EventType::CLASS_INSERT, EventType::LEARNER_ADD => 'INSERT',
            EventType::CLASS_UPDATE, EventType::LEARNER_UPDATE, EventType::STATUS_CHANGE => 'UPDATE',
            EventType::CLASS_DELETE, EventType::LEARNER_REMOVE => 'DELETE',
        };
    }

    /**
     * Validate email address
     *
     * @param string $email Email to validate
     * @return bool True if valid
     */
    public function validateEmail(string $email): bool
    {
        $email = trim($email);
        return $email !== '' && is_email($email) !== false;
    }

    /**
     * Filter array to valid emails only
     *
     * @param array<int, mixed> $emails Array of potential emails
     * @return array<int, string> Array of validated emails
     */
    private function filterValidEmails(array $emails): array
    {
        $filtered = array_filter($emails, function ($email): bool {
            if (!is_string($email)) {
                return false;
            }
            return $this->validateEmail($email);
        });

        return array_values(array_map('trim', $filtered));
    }

    /**
     * Resolve recipient from legacy option
     *
     * @param string $operation Legacy operation
     * @return string|null Email or null
     */
    private function resolveLegacyRecipient(string $operation): ?string
    {
        $optionKey = self::LEGACY_OPTIONS[$operation] ?? null;

        if ($optionKey === null) {
            return null;
        }

        $option = get_option($optionKey, '');

        if (is_string($option)) {
            $option = trim($option);
            if ($option !== '' && is_email($option)) {
                return $option;
            }
        }

        return null;
    }
}
