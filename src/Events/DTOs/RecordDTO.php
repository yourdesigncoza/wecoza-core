<?php

declare(strict_types=1);

namespace WeCoza\Events\DTOs;

if (!defined('ABSPATH') && php_sapi_name() !== 'cli') {
    exit;
}

/**
 * Data Transfer Object for AI summary tracking record.
 *
 * Represents the state of an AI-generated summary including status,
 * error information, metrics, and viewing state.
 *
 * @see AISummaryService::normaliseRecord() for the original array structure
 */
final class RecordDTO
{
    public function __construct(
        public readonly ?string $summary,
        public readonly string $status,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
        public readonly int $attempts,
        public readonly bool $viewed,
        public readonly ?string $viewedAt,
        public readonly ?string $generatedAt,
        public readonly ?string $model,
        public readonly int $tokensUsed,
        public readonly int $processingTimeMs,
    ) {}

    /**
     * Create a RecordDTO from an array (e.g., from database or API).
     *
     * Uses same defaults as AISummaryService::normaliseRecord().
     *
     * @param array<string,mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        if ($data === null) {
            return new self(
                summary: null,
                status: 'pending',
                errorCode: null,
                errorMessage: null,
                attempts: 0,
                viewed: false,
                viewedAt: null,
                generatedAt: null,
                model: null,
                tokensUsed: 0,
                processingTimeMs: 0,
            );
        }

        return new self(
            summary: isset($data['summary']) ? (string) $data['summary'] : null,
            status: (string) ($data['status'] ?? 'pending'),
            errorCode: isset($data['error_code']) ? (string) $data['error_code'] : null,
            errorMessage: isset($data['error_message']) ? (string) $data['error_message'] : null,
            attempts: \max(0, (int) ($data['attempts'] ?? 0)),
            viewed: (bool) ($data['viewed'] ?? false),
            viewedAt: isset($data['viewed_at']) ? (string) $data['viewed_at'] : null,
            generatedAt: isset($data['generated_at']) ? (string) $data['generated_at'] : null,
            model: isset($data['model']) ? (string) $data['model'] : null,
            tokensUsed: isset($data['tokens_used']) ? (int) $data['tokens_used'] : 0,
            processingTimeMs: isset($data['processing_time_ms']) ? (int) $data['processing_time_ms'] : 0,
        );
    }

    /**
     * Convert to array for database storage or API response.
     *
     * Keys match AISummaryService::normaliseRecord() output.
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'status' => $this->status,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'attempts' => $this->attempts,
            'viewed' => $this->viewed,
            'viewed_at' => $this->viewedAt,
            'generated_at' => $this->generatedAt,
            'model' => $this->model,
            'tokens_used' => $this->tokensUsed,
            'processing_time_ms' => $this->processingTimeMs,
        ];
    }

    /**
     * Create a copy with updated status.
     */
    public function withStatus(string $status): self
    {
        return new self(
            summary: $this->summary,
            status: $status,
            errorCode: $this->errorCode,
            errorMessage: $this->errorMessage,
            attempts: $this->attempts,
            viewed: $this->viewed,
            viewedAt: $this->viewedAt,
            generatedAt: $this->generatedAt,
            model: $this->model,
            tokensUsed: $this->tokensUsed,
            processingTimeMs: $this->processingTimeMs,
        );
    }

    /**
     * Create a copy with updated summary and status set to 'success'.
     */
    public function withSummary(string $summary): self
    {
        return new self(
            summary: $summary,
            status: 'success',
            errorCode: null,
            errorMessage: null,
            attempts: $this->attempts,
            viewed: $this->viewed,
            viewedAt: $this->viewedAt,
            generatedAt: $this->generatedAt,
            model: $this->model,
            tokensUsed: $this->tokensUsed,
            processingTimeMs: $this->processingTimeMs,
        );
    }

    /**
     * Create a copy with error information.
     */
    public function withError(?string $code, ?string $message): self
    {
        return new self(
            summary: $this->summary,
            status: $this->status,
            errorCode: $code,
            errorMessage: $message,
            attempts: $this->attempts,
            viewed: $this->viewed,
            viewedAt: $this->viewedAt,
            generatedAt: $this->generatedAt,
            model: $this->model,
            tokensUsed: $this->tokensUsed,
            processingTimeMs: $this->processingTimeMs,
        );
    }

    /**
     * Create a copy with incremented attempt count.
     */
    public function incrementAttempts(): self
    {
        return new self(
            summary: $this->summary,
            status: $this->status,
            errorCode: $this->errorCode,
            errorMessage: $this->errorMessage,
            attempts: $this->attempts + 1,
            viewed: $this->viewed,
            viewedAt: $this->viewedAt,
            generatedAt: $this->generatedAt,
            model: $this->model,
            tokensUsed: $this->tokensUsed,
            processingTimeMs: $this->processingTimeMs,
        );
    }

    /**
     * Create a copy with generation metadata.
     */
    public function withGenerationMeta(string $generatedAt, ?string $model, int $tokensUsed, int $processingTimeMs): self
    {
        return new self(
            summary: $this->summary,
            status: $this->status,
            errorCode: $this->errorCode,
            errorMessage: $this->errorMessage,
            attempts: $this->attempts,
            viewed: $this->viewed,
            viewedAt: $this->viewedAt,
            generatedAt: $generatedAt,
            model: $model,
            tokensUsed: $tokensUsed,
            processingTimeMs: $processingTimeMs,
        );
    }

    /**
     * Create a copy marked as viewed.
     */
    public function markViewed(string $viewedAt): self
    {
        return new self(
            summary: $this->summary,
            status: $this->status,
            errorCode: $this->errorCode,
            errorMessage: $this->errorMessage,
            attempts: $this->attempts,
            viewed: true,
            viewedAt: $viewedAt,
            generatedAt: $this->generatedAt,
            model: $this->model,
            tokensUsed: $this->tokensUsed,
            processingTimeMs: $this->processingTimeMs,
        );
    }
}
