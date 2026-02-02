<?php
declare(strict_types=1);

namespace WeCoza\Events\Services;

if (!defined('ABSPATH')) {
    exit;
}

use WeCoza\Events\Repositories\ClassChangeLogRepository;

final class AISummaryDisplayService
{
    private ClassChangeLogRepository $repository;

    public function __construct(?ClassChangeLogRepository $repository = null)
    {
        $this->repository = $repository ?? new ClassChangeLogRepository();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSummaries(int $limit, ?int $classId, ?string $operation): array
    {
        return $this->repository->getLogsWithAISummary($limit, $classId, $operation);
    }
}
