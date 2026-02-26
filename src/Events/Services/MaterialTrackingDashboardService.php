<?php
declare(strict_types=1);

namespace WeCoza\Events\Services;

if (!defined('ABSPATH')) {
    exit;
}

use WeCoza\Core\Abstract\AppConstants;
use WeCoza\Events\Repositories\MaterialTrackingRepository;

/**
 * Service for managing material tracking dashboard
 */
final class MaterialTrackingDashboardService
{
    public function __construct(
        private readonly MaterialTrackingRepository $repository
    ) {
    }

    /**
     * Get dashboard data with optional filters
     *
     * @param array<string, mixed> $filters Array with keys: limit, status, search
     * @return array<int, array<string, mixed>> Array of tracking records
     */
    public function getDashboardData(array $filters = []): array
    {
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : AppConstants::DEFAULT_PAGE_SIZE;
        $limit = max(1, min(200, $limit)); // Enforce 1-200 range

        $status = $filters['status'] ?? null;
        if ($status !== null) {
            // Map old 'delivered' to new 'completed' for backward compat
            if ($status === 'delivered') {
                $status = 'completed';
            }
            if (!in_array($status, ['pending', 'completed'], true)) {
                $status = null;
            }
        }

        $search = isset($filters['search']) ? trim((string) $filters['search']) : null;
        if ($search === '') {
            $search = null;
        }

        return $this->repository->getTrackingDashboardData($limit, $status, $search);
    }

    /**
     * Get tracking statistics
     *
     * @return array<string, int> Statistics array
     */
    public function getStatistics(): array
    {
        return $this->repository->getTrackingStatistics();
    }

    /**
     * Mark materials as delivered for a class
     *
     * @param int $classId The class ID
     * @param int $eventIndex The index of the delivery event in event_dates JSONB array
     * @return bool True on success, false on failure
     */
    public function markAsDelivered(int $classId, int $eventIndex): bool
    {
        if (!$this->canManageMaterialTracking()) {
            return false;
        }

        try {
            $this->repository->markDelivered($classId, $eventIndex);
            return true;
        } catch (\Throwable $e) {
            error_log('Material Tracking: Failed to mark delivered - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if current user can view material tracking dashboard
     *
     * @return bool True if user has permission
     */
    public function canViewDashboard(): bool
    {
        return is_user_logged_in();
    }

    /**
     * Check if current user can manage material tracking
     *
     * @return bool True if user has permission
     */
    public function canManageMaterialTracking(): bool
    {
        return is_user_logged_in();
    }
}
