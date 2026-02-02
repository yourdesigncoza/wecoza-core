<?php

declare(strict_types=1);

namespace WeCoza\Events\DTOs;

if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
    exit;
}

/**
 * Data Transfer Object for email context in AI summaries.
 *
 * Contains alias mappings for anonymization and obfuscated payload data
 * used when sending summary notifications via email.
 *
 * @see AISummaryService::generateSummary() for the original array structure
 */
final class EmailContextDTO
{
    /**
     * @param array<string,string> $aliasMap Maps original identifiers to anonymized aliases
     * @param array<string,string> $fieldLabels Maps field keys to human-readable labels
     * @param array<string,array<string,mixed>> $obfuscated Contains obfuscated new_row, diff, old_row data
     */
    public function __construct(
        public readonly array $aliasMap,
        public readonly array $fieldLabels,
        public readonly array $obfuscated,
    ) {}

    /**
     * Create an EmailContextDTO from an array.
     *
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            aliasMap: (array) ($data['alias_map'] ?? []),
            fieldLabels: (array) ($data['field_labels'] ?? []),
            obfuscated: (array) ($data['obfuscated'] ?? []),
        );
    }

    /**
     * Create an empty EmailContextDTO with no data.
     *
     * Useful for successful lookups that don't need context
     * or error cases where obfuscation wasn't performed.
     */
    public static function empty(): self
    {
        return new self(
            aliasMap: [],
            fieldLabels: [],
            obfuscated: [],
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
            'alias_map' => $this->aliasMap,
            'field_labels' => $this->fieldLabels,
            'obfuscated' => $this->obfuscated,
        ];
    }

    /**
     * Check if this context has any data.
     */
    public function isEmpty(): bool
    {
        return empty($this->aliasMap) && empty($this->fieldLabels) && empty($this->obfuscated);
    }

    /**
     * Get the obfuscated new row data.
     *
     * @return array<string,mixed>
     */
    public function getObfuscatedNewRow(): array
    {
        return (array) ($this->obfuscated['new_row'] ?? []);
    }

    /**
     * Get the obfuscated diff data.
     *
     * @return array<string,mixed>
     */
    public function getObfuscatedDiff(): array
    {
        return (array) ($this->obfuscated['diff'] ?? []);
    }

    /**
     * Get the obfuscated old row data.
     *
     * @return array<string,mixed>
     */
    public function getObfuscatedOldRow(): array
    {
        return (array) ($this->obfuscated['old_row'] ?? []);
    }
}
