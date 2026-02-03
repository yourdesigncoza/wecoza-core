<?php
declare(strict_types=1);

namespace WeCoza\Events\Services;

if (!defined('ABSPATH')) {
    exit;
}

use WeCoza\Events\Repositories\ClassTaskRepository;

use function count;

final class ClassTaskService
{
    private ClassTaskRepository $repository;
    private TaskManager $taskManager;

    public function __construct(
        ?ClassTaskRepository $repository = null,
        ?TaskManager $taskManager = null
    ) {
        $this->repository = $repository ?? new ClassTaskRepository();
        $this->taskManager = $taskManager ?? new TaskManager();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getClassTasks(int $limit, string $sortDirection, bool $prioritiseOpen, ?int $classIdFilter): array
    {
        $rows = $this->repository->fetchClasses($limit, $sortDirection, $classIdFilter);

        $items = [];
        foreach ($rows as $row) {
            // Build tasks from event_dates (no log_id needed)
            $tasks = $this->taskManager->buildTasksFromEvents($row);

            $items[] = [
                'row' => $row,
                'tasks' => $tasks,
                'class_id' => (int) $row['class_id'],
                'manageable' => true,  // All classes manageable now
                'open_count' => count($tasks->open()),
            ];
        }

        if ($prioritiseOpen) {
            [$open, $completed] = $this->partitionByOpenCount($items);
            $items = [...$open, ...$completed];
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function partitionByOpenCount(array $items): array
    {
        $open = [];
        $completed = [];

        foreach ($items as $item) {
            if (($item['open_count'] ?? 0) > 0) {
                $open[] = $item;
            } else {
                $completed[] = $item;
            }
        }

        return [$open, $completed];
    }
}
