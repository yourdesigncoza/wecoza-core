<?php
declare(strict_types=1);

/**
 * WeCoza Core - Learner Service
 *
 * Business logic service for learner CRUD operations, dropdown data assembly,
 * and portfolio/sponsor management. Establishes the service layer pattern.
 *
 * @package WeCoza\Learners\Services
 * @since 4.0.0
 */

namespace WeCoza\Learners\Services;

use WeCoza\Learners\Models\LearnerModel;
use WeCoza\Learners\Repositories\LearnerRepository;

if (!defined('ABSPATH')) {
    exit;
}

class LearnerService
{
    private LearnerRepository $repository;

    public function __construct()
    {
        $this->repository = new LearnerRepository();
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD Operations
    |--------------------------------------------------------------------------
    */

    /**
     * Get a single learner by ID
     *
     * @param int $id Learner ID
     * @return LearnerModel|null Learner model or null if not found
     */
    public function getLearner(int $id): ?LearnerModel
    {
        return LearnerModel::getById($id);
    }

    /**
     * Get all learners with pagination
     *
     * @param int $limit Maximum number of learners to return
     * @param int $offset Number of learners to skip
     * @return array Array of LearnerModel instances
     */
    public function getLearners(int $limit = 50, int $offset = 0): array
    {
        return LearnerModel::getAll($limit, $offset);
    }

    /**
     * Get all learners with full data (qualifications, locations mapped)
     *
     * @return array Array of LearnerModel instances with joined data
     */
    public function getLearnersWithMappings(): array
    {
        return LearnerModel::getAllWithMappings();
    }

    /**
     * Get total learner count
     *
     * @return int Total number of learners
     */
    public function getLearnerCount(): int
    {
        return LearnerModel::count();
    }

    /**
     * Create a new learner
     *
     * @param array $data Learner data
     * @return LearnerModel|null Created learner model or null on failure
     */
    public function createLearner(array $data): ?LearnerModel
    {
        $learner = new LearnerModel($data);

        if ($learner->save()) {
            return $learner;
        }

        return null;
    }

    /**
     * Update an existing learner
     *
     * @param int $id Learner ID
     * @param array $data Updated learner data
     * @return bool True on success, false on failure
     */
    public function updateLearner(int $id, array $data): bool
    {
        $learner = LearnerModel::getById($id);

        if (!$learner) {
            return false;
        }

        $learner->hydrate($data);
        return $learner->update();
    }

    /**
     * Delete a learner
     *
     * @param int $id Learner ID
     * @return bool True on success, false on failure
     */
    public function deleteLearner(int $id): bool
    {
        $learner = LearnerModel::getById($id);

        if (!$learner) {
            return false;
        }

        return $learner->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Dropdown Data Assembly
    |--------------------------------------------------------------------------
    */

    /**
     * Get dropdown data for forms
     *
     * Assembles all reference data needed for learner forms:
     * - Locations (cities and provinces)
     * - Qualifications
     * - Employers
     * - Placement levels (split into numeracy N* and communication C*)
     *
     * @return array Dropdown data with keys: cities, provinces, qualifications, employers, numeracy_levels, communication_levels
     */
    public function getDropdownData(): array
    {
        $locations = $this->repository->getLocations();
        $qualifications = $this->repository->getQualifications();
        $employers = $this->repository->getEmployers();
        $placementLevels = $this->repository->getPlacementLevels();

        // Split placement levels into numeracy (N*) and communication (C*)
        $numeracyLevels = [];
        $communicationLevels = [];

        foreach ($placementLevels as $level) {
            $levelName = $level['level'] ?? '';
            if (str_starts_with($levelName, 'N')) {
                $numeracyLevels[] = $level;
            } elseif (str_starts_with($levelName, 'C')) {
                $communicationLevels[] = $level;
            }
        }

        return [
            'cities' => $locations['cities'] ?? [],
            'provinces' => $locations['provinces'] ?? [],
            'qualifications' => $qualifications,
            'employers' => $employers,
            'numeracy_levels' => $numeracyLevels,
            'communication_levels' => $communicationLevels,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Portfolio Management
    |--------------------------------------------------------------------------
    */

    /**
     * Save portfolio files for a learner
     *
     * @param int $learnerId Learner ID
     * @param array $files Uploaded files array from $_FILES
     * @return array Result array with success, message, paths, skipped count
     */
    public function savePortfolios(int $learnerId, array $files): array
    {
        return $this->repository->savePortfolios($learnerId, $files);
    }

    /**
     * Delete a portfolio file
     *
     * @param int $portfolioId Portfolio ID
     * @return bool True on success, false on failure
     */
    public function deletePortfolio(int $portfolioId): bool
    {
        return $this->repository->deletePortfolio($portfolioId);
    }

    /*
    |--------------------------------------------------------------------------
    | Sponsor Management
    |--------------------------------------------------------------------------
    */

    /**
     * Get sponsor employer_ids for a learner
     *
     * @param int $learnerId Learner ID
     * @return array Array of employer_id integers
     */
    public function getSponsors(int $learnerId): array
    {
        return $this->repository->getSponsors($learnerId);
    }

    /**
     * Save sponsors for a learner (replace all existing)
     *
     * @param int $learnerId Learner ID
     * @param array $employerIds Array of employer_id integers
     * @return bool True on success, false on failure
     */
    public function saveSponsors(int $learnerId, array $employerIds): bool
    {
        return $this->repository->saveSponsors($learnerId, $employerIds);
    }

    /*
    |--------------------------------------------------------------------------
    | Table Row Generation (Presentation Logic)
    |--------------------------------------------------------------------------
    */

    /**
     * Generate HTML table rows for learners data
     *
     * This is presentation-adjacent business logic — handles field mapping,
     * URL generation, and button HTML for the learner display table.
     *
     * @param array $learners Array of learner objects (stdClass from toDbArray)
     * @return string HTML string of table rows
     */
    public function generateTableRowsHtml(array $learners): string
    {
        $rows = '';
        foreach ($learners as $learner) {
            $buttons = sprintf(
                '<div class="btn-group btn-group-sm" role="group">
                    <a href="%s" class="btn bg-discovery-subtle">View</a>
                    <a href="%s" class="btn bg-warning-subtle">Edit</a>
                    <button class="btn btn-sm bg-danger-subtle delete-learner-btn" data-id="%s">Delete</button>
                </div>',
                esc_url(home_url('/app/view-learner/?learner_id=' . ($learner->id ?? ''))),
                esc_url(home_url('/app/update-learners/?learner_id=' . ($learner->id ?? ''))),
                esc_attr($learner->id ?? '')
            );

            // Create full name with title
            $title_with_period = !empty($learner->title) ? $learner->title . '. ' : '';
            $full_name = trim($title_with_period . ($learner->first_name ?? '') . ' ' . ($learner->surname ?? ''));

            $rows .= sprintf(
                '<tr>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td class="text-nowrap text-center">%s</td>
                </tr>',
                esc_html($full_name),
                esc_html($learner->surname ?? ''),
                esc_html($learner->gender ?? ''),
                esc_html($learner->race ?? ''),
                esc_html($learner->tel_number ?? ''),
                esc_html($learner->email_address ?? ''),
                esc_html($learner->city_town_name ?? ''),
                esc_html($learner->employment_status ?? ''),
                $buttons
            );
        }
        return $rows;
    }
}
