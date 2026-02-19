<?php
declare(strict_types=1);

namespace WeCoza\Feedback\Services;

use WeCoza\Feedback\Repositories\FeedbackRepository;

final class FeedbackSyncService
{
    private FeedbackRepository $repository;
    private LinearIntegrationService $linearService;

    public function __construct(
        ?FeedbackRepository $repository = null,
        ?LinearIntegrationService $linearService = null
    ) {
        $this->repository    = $repository ?? new FeedbackRepository();
        $this->linearService = $linearService ?? new LinearIntegrationService();
    }

    public function retryFailedSubmissions(): void
    {
        $pending = $this->repository->findPendingSync(5, 20);

        foreach ($pending as $record) {
            try {
                $result = $this->linearService->createIssue($record);

                if ($result['success']) {
                    $this->repository->markSynced(
                        (int) $record['id'],
                        $result['issue_id'],
                        $result['issue_url']
                    );
                    wecoza_log("Feedback #{$record['id']} synced to Linear: {$result['issue_id']}");
                } else {
                    $this->repository->markFailed((int) $record['id'], $result['error']);
                    wecoza_log("Feedback #{$record['id']} sync failed: {$result['error']}", 'error');
                }
            } catch (\Exception $e) {
                $this->repository->markFailed((int) $record['id'], $e->getMessage());
                wecoza_log("Feedback #{$record['id']} sync exception: {$e->getMessage()}", 'error');
            }
        }
    }
}
