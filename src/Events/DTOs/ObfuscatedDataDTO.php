<?php

declare(strict_types=1);

namespace WeCoza\Events\DTOs;

if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
    exit;
}

/**
 * Data Transfer Object for obfuscation results.
 *
 * Wraps the result of obfuscating new_row, diff, and old_row data
 * during AI summary generation, including the alias mappings and
 * human-readable field labels.
 *
 * @see AISummaryService::generateSummary() lines 89-101 for original structure
 * @see DataObfuscator::obfuscatePayloadWithLabels() for obfuscation logic
 */
final class ObfuscatedDataDTO
{
    /**
     * @param array<string,mixed> $newRow Obfuscated new row payload
     * @param array<string,mixed> $diff Obfuscated diff payload (changes only)
     * @param array<string,mixed> $oldRow Obfuscated old row payload
     * @param array<string,string> $aliases Maps original identifiers to anonymized aliases
     * @param array<string,string> $fieldLabels Maps field keys to human-readable labels
     */
    public function __construct(
        public readonly array $newRow,
        public readonly array $diff,
        public readonly array $oldRow,
        public readonly array $aliases,
        public readonly array $fieldLabels,
    ) {}

    /**
     * Create an ObfuscatedDataDTO from obfuscation service results.
     *
     * Takes the raw results from three calls to obfuscatePayloadWithLabels()
     * and combines them into a single DTO.
     *
     * @param array{payload:array<string,mixed>,state:array<string,mixed>,field_labels:array<string,string>} $newRowResult
     * @param array{payload:array<string,mixed>,state:array<string,mixed>,field_labels:array<string,string>} $diffResult
     * @param array{payload:array<string,mixed>,state:array<string,mixed>,field_labels:array<string,string>} $oldRowResult
     */
    public static function fromResults(
        array $newRowResult,
        array $diffResult,
        array $oldRowResult
    ): self {
        return new self(
            newRow: (array) ($newRowResult['payload'] ?? []),
            diff: (array) ($diffResult['payload'] ?? []),
            oldRow: (array) ($oldRowResult['payload'] ?? []),
            aliases: (array) ($oldRowResult['state']['aliases'] ?? []),
            fieldLabels: \array_merge(
                (array) ($newRowResult['field_labels'] ?? []),
                (array) ($diffResult['field_labels'] ?? []),
                (array) ($oldRowResult['field_labels'] ?? [])
            ),
        );
    }

    /**
     * Create an empty ObfuscatedDataDTO.
     *
     * Useful for cases where obfuscation was skipped or unnecessary.
     */
    public static function empty(): self
    {
        return new self(
            newRow: [],
            diff: [],
            oldRow: [],
            aliases: [],
            fieldLabels: [],
        );
    }

    /**
     * Create an ObfuscatedDataDTO from an array.
     *
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            newRow: (array) ($data['new_row'] ?? []),
            diff: (array) ($data['diff'] ?? []),
            oldRow: (array) ($data['old_row'] ?? []),
            aliases: (array) ($data['aliases'] ?? []),
            fieldLabels: (array) ($data['field_labels'] ?? []),
        );
    }

    /**
     * Convert to array for storage or API response.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'new_row' => $this->newRow,
            'diff' => $this->diff,
            'old_row' => $this->oldRow,
            'aliases' => $this->aliases,
            'field_labels' => $this->fieldLabels,
        ];
    }

    /**
     * Convert to EmailContextDTO format.
     *
     * Creates the email_context structure expected by AISummaryService.
     */
    public function toEmailContext(): EmailContextDTO
    {
        return new EmailContextDTO(
            aliasMap: $this->aliases,
            fieldLabels: $this->fieldLabels,
            obfuscated: [
                'new_row' => $this->newRow,
                'diff' => $this->diff,
                'old_row' => $this->oldRow,
            ],
        );
    }

    /**
     * Check if any data was obfuscated.
     */
    public function isEmpty(): bool
    {
        return empty($this->newRow) && empty($this->diff) && empty($this->oldRow);
    }
}
