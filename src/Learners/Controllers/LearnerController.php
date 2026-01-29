<?php
/**
 * WeCoza Core - Learner Controller
 *
 * Controller for handling learner-related operations.
 * Uses LearnerModel for data access (MVC pattern).
 *
 * @package WeCoza\Learners\Controllers
 * @since 1.0.0
 */

namespace WeCoza\Learners\Controllers;

use WeCoza\Core\Abstract\BaseController;
use WeCoza\Learners\Models\LearnerModel;
use WeCoza\Learners\Repositories\LearnerRepository;

if (!defined('ABSPATH')) {
    exit;
}

class LearnerController extends BaseController
{
    /**
     * Repository instance
     */
    private ?LearnerRepository $repository = null;

    /**
     * Register WordPress hooks
     */
    protected function registerHooks(): void
    {
        add_action('init', [$this, 'registerShortcodes']);

        // Register AJAX handlers
        add_action('wp_ajax_wecoza_get_learner', [$this, 'ajaxGetLearner']);
        add_action('wp_ajax_nopriv_wecoza_get_learner', [$this, 'ajaxGetLearner']);

        add_action('wp_ajax_wecoza_get_learners', [$this, 'ajaxGetLearners']);
        add_action('wp_ajax_nopriv_wecoza_get_learners', [$this, 'ajaxGetLearners']);

        add_action('wp_ajax_wecoza_update_learner', [$this, 'ajaxUpdateLearner']);
        add_action('wp_ajax_wecoza_delete_learner', [$this, 'ajaxDeleteLearner']);
    }

    /**
     * Register shortcodes
     */
    public function registerShortcodes(): void
    {
        add_shortcode('wecoza_learner_capture', [$this, 'renderCaptureForm']);
        add_shortcode('wecoza_learner_display', [$this, 'renderLearnerList']);
        add_shortcode('wecoza_learner_update', [$this, 'renderUpdateForm']);
    }

    /**
     * Get repository instance
     */
    private function getRepository(): LearnerRepository
    {
        if ($this->repository === null) {
            $this->repository = new LearnerRepository();
        }
        return $this->repository;
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD Operations
    |--------------------------------------------------------------------------
    */

    /**
     * Get a single learner by ID
     */
    public function getLearner(int $id): ?LearnerModel
    {
        return LearnerModel::getById($id);
    }

    /**
     * Get all learners with pagination
     */
    public function getLearners(int $limit = 50, int $offset = 0): array
    {
        return LearnerModel::getAll($limit, $offset);
    }

    /**
     * Get all learners with full data (qualifications, locations mapped)
     */
    public function getLearnersWithMappings(): array
    {
        return LearnerModel::getAllWithMappings();
    }

    /**
     * Get total learner count
     */
    public function getLearnerCount(): int
    {
        return LearnerModel::count();
    }

    /**
     * Create a new learner
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
    | Form Data
    |--------------------------------------------------------------------------
    */

    /**
     * Get dropdown data for forms
     */
    public function getDropdownData(): array
    {
        $repo = $this->getRepository();

        $locations = $repo->getLocations();
        $qualifications = $repo->getQualifications();
        $employers = $repo->getEmployers();
        $placementLevels = $repo->getPlacementLevels();

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

    /**
     * Save portfolio files for a learner
     */
    public function savePortfolios(int $learnerId, array $files): array
    {
        return $this->getRepository()->savePortfolios($learnerId, $files);
    }

    /**
     * Delete a portfolio file
     */
    public function deletePortfolio(int $portfolioId): bool
    {
        return $this->getRepository()->deletePortfolio($portfolioId);
    }

    /*
    |--------------------------------------------------------------------------
    | AJAX Handlers
    |--------------------------------------------------------------------------
    */

    /**
     * AJAX: Get single learner
     */
    public function ajaxGetLearner(): void
    {
        $id = $this->input('id', 'int') ?? $this->query('id', 'int');

        if (!$id) {
            $this->sendError('Invalid learner ID');
            return;
        }

        $learner = $this->getLearner($id);

        if ($learner) {
            $this->sendSuccess($learner->toArray());
        } else {
            $this->sendError('Learner not found');
        }
    }

    /**
     * AJAX: Get learners list with pagination
     */
    public function ajaxGetLearners(): void
    {
        $limit = $this->query('limit', 'int') ?? 50;
        $offset = $this->query('offset', 'int') ?? 0;
        $withMappings = $this->query('mappings', 'bool') ?? false;

        if ($withMappings) {
            $learners = $this->getLearnersWithMappings();
        } else {
            $learners = $this->getLearners($limit, $offset);
        }

        $data = array_map(fn($l) => $l->toArray(), $learners);

        $this->sendSuccess([
            'learners' => $data,
            'total' => $this->getLearnerCount(),
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * AJAX: Update learner
     */
    public function ajaxUpdateLearner(): void
    {
        $this->requireNonce('learners_nonce_action');

        $id = $this->input('id', 'int');

        if (!$id) {
            $this->sendError('Invalid learner ID');
            return;
        }

        $data = $this->sanitizeLearnerInput($_POST);

        if ($this->updateLearner($id, $data)) {
            $this->sendSuccess([], 'Learner updated successfully');
        } else {
            $this->sendError('Failed to update learner');
        }
    }

    /**
     * AJAX: Delete learner
     */
    public function ajaxDeleteLearner(): void
    {
        $this->requireNonce('learners_nonce_action');

        $id = $this->input('id', 'int');

        if (!$id) {
            $this->sendError('Invalid learner ID');
            return;
        }

        if ($this->deleteLearner($id)) {
            $this->sendSuccess([], 'Learner deleted successfully');
        } else {
            $this->sendError('Failed to delete learner');
        }
    }

    /**
     * Sanitize learner input data
     */
    private function sanitizeLearnerInput(array $input): array
    {
        return $this->sanitizeArray($input, [
            'title' => 'string',
            'first_name' => 'string',
            'second_name' => 'string',
            'initials' => 'string',
            'surname' => 'string',
            'gender' => 'string',
            'race' => 'string',
            'sa_id_no' => 'string',
            'passport_number' => 'string',
            'tel_number' => 'string',
            'alternative_tel_number' => 'string',
            'email_address' => 'email',
            'address_line_1' => 'string',
            'address_line_2' => 'string',
            'postal_code' => 'string',
            'assessment_status' => 'string',
            'city_town_id' => 'int',
            'province_region_id' => 'int',
            'highest_qualification' => 'int',
            'numeracy_level' => 'int',
            'communication_level' => 'int',
            'employer_id' => 'int',
            'employment_status' => 'bool',
            'disability_status' => 'bool',
            'placement_assessment_date' => 'string',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Shortcode Renderers
    |--------------------------------------------------------------------------
    */

    /**
     * Render capture form shortcode
     */
    public function renderCaptureForm(array $atts = []): string
    {
        $atts = shortcode_atts([
            'form_id' => 'wecoza_learner_form',
            'redirect' => ''
        ], $atts);

        ob_start();
        ?>
        <div class="wecoza-learner-capture-form" id="<?php echo esc_attr($atts['form_id']); ?>-container">
            <p class="text-muted">
                <small>MVC Controller - For full form use: <code>[wecoza_learners_form]</code></small>
            </p>
            <form id="<?php echo esc_attr($atts['form_id']); ?>" method="post" class="needs-validation" novalidate>
                <?php wp_nonce_field('learners_nonce_action', 'nonce'); ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="first_name" name="first_name" required>
                    </div>
                    <div class="col-md-6">
                        <label for="surname" class="form-label">Surname <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="surname" name="surname" required>
                    </div>
                    <div class="col-md-6">
                        <label for="email_address" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email_address" name="email_address">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Register Learner</button>
                    </div>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render learner list shortcode
     */
    public function renderLearnerList(array $atts = []): string
    {
        $atts = shortcode_atts([
            'limit' => 10,
            'show_pagination' => true
        ], $atts);

        $learners = $this->getLearners((int) $atts['limit']);
        $total = $this->getLearnerCount();

        ob_start();
        ?>
        <div class="wecoza-learner-list">
            <p class="text-muted">
                <small>MVC Controller (<?php echo $total; ?> total) - For full table use: <code>[wecoza_display_learners]</code></small>
            </p>

            <?php if (!empty($learners)): ?>
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Surname</th>
                            <th>Email</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($learners as $learner): ?>
                            <tr>
                                <td><?php echo esc_html($learner->getId()); ?></td>
                                <td><?php echo esc_html($learner->getFirstName()); ?></td>
                                <td><?php echo esc_html($learner->getSurname()); ?></td>
                                <td><?php echo esc_html($learner->getEmailAddress()); ?></td>
                                <td><?php echo esc_html($learner->getCreatedAt()); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="alert alert-info">No learners found.</div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render update form shortcode
     */
    public function renderUpdateForm(array $atts = []): string
    {
        $atts = shortcode_atts([
            'learner_id' => 0
        ], $atts);

        $learnerId = (int) $atts['learner_id'] ?: $this->query('learner_id', 'int') ?? 0;

        if (!$learnerId) {
            return '<div class="alert alert-warning">No learner ID specified.</div>';
        }

        $learner = $this->getLearner($learnerId);

        if (!$learner) {
            return '<div class="alert alert-danger">Learner not found.</div>';
        }

        ob_start();
        ?>
        <div class="wecoza-learner-update-form">
            <p class="text-muted">
                <small>MVC Controller - For full form use: <code>[wecoza_learners_update_form]</code></small>
            </p>
            <h4>Update: <?php echo esc_html($learner->getFullName()); ?></h4>
            <p>Learner ID: <?php echo esc_html($learner->getId()); ?></p>
            <p>Email: <?php echo esc_html($learner->getEmailAddress()); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }
}
