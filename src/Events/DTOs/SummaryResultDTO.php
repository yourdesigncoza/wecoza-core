<?php

declare(strict_types=1);

namespace WeCoza\Events\DTOs;

if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
    exit;
}

/**
 * Data Transfer Object for AI summary generation results.
 *
 * Wraps the complete return value of AISummaryService::generateSummary(),
 * combining the record state, email context, and overall status.
 *
 * @see AISummaryService::generateSummary() for the original return structure
 */
final class SummaryResultDTO
{
    public function __construct(
        public readonly RecordDTO $record,
        public readonly EmailContextDTO $emailContext,
        public readonly string $status,
    ) {}

    /**
     * Create a successful result.
     *
     * Use when summary generation completed without errors.
     */
    public static function success(RecordDTO $record, EmailContextDTO $emailContext): self
    {
        return new self($record, $emailContext, 'success');
    }

    /**
     * Create a failed result.
     *
     * Use when summary generation failed after all retry attempts.
     */
    public static function failed(RecordDTO $record, EmailContextDTO $emailContext): self
    {
        return new self($record, $emailContext, 'failed');
    }

    /**
     * Create a pending result.
     *
     * Use when summary generation failed but can be retried.
     */
    public static function pending(RecordDTO $record, EmailContextDTO $emailContext): self
    {
        return new self($record, $emailContext, 'pending');
    }

    /**
     * Create a SummaryResultDTO from an array.
     *
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            record: RecordDTO::fromArray($data['record'] ?? null),
            emailContext: isset($data['email_context'])
                ? EmailContextDTO::fromArray($data['email_context'])
                : EmailContextDTO::empty(),
            status: (string) ($data['status'] ?? 'pending'),
        );
    }

    /**
     * Convert to array for storage or API response.
     *
     * Structure matches AISummaryService::generateSummary() return value.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'record' => $this->record->toArray(),
            'email_context' => $this->emailContext->toArray(),
            'status' => $this->status,
        ];
    }

    /**
     * Check if the generation was successful.
     */
    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if the generation failed (no more retries).
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the generation is pending (can retry).
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
