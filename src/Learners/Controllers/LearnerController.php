<?php
declare(strict_types=1);

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
use WeCoza\Learners\Services\LearnerService;

if (!defined('ABSPATH')) {
    exit;
}

class LearnerController extends BaseController
{
    /**
     * Service instance
     */
    private ?LearnerService $learnerService = null;

    /**
     * Register WordPress hooks
     */
    protected function registerHooks(): void
    {
        add_action('init', [$this, 'registerShortcodes']);
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
     * Get service instance
     */
    private function getLearnerService(): LearnerService
    {
        if ($this->learnerService === null) {
            $this->learnerService = new LearnerService();
        }
        return $this->learnerService;
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
        if (!current_user_can('manage_learners')) {
            $this->sendError('Insufficient permissions.', 403);
            return;
        }

        $this->requireNonce('learners_nonce');

        $id = $this->input('id', 'int') ?? $this->query('id', 'int');

        if (!$id) {
            $this->sendError('Invalid learner ID');
            return;
        }

        $learner = $this->getLearnerService()->getLearner($id);

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
        if (!current_user_can('manage_learners')) {
            $this->sendError('Insufficient permissions.', 403);
            return;
        }

        $this->requireNonce('learners_nonce');

        $limit = $this->query('limit', 'int') ?? 50;
        $offset = $this->query('offset', 'int') ?? 0;
        $withMappings = $this->query('mappings', 'bool') ?? false;

        $service = $this->getLearnerService();

        if ($withMappings) {
            $learners = $service->getLearnersWithMappings();
        } else {
            $learners = $service->getLearners($limit, $offset);
        }

        $data = array_map(fn($l) => $l->toArray(), $learners);

        $this->sendSuccess([
            'learners' => $data,
            'total' => $service->getLearnerCount(),
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * AJAX: Update learner
     */
    public function ajaxUpdateLearner(): void
    {
        if (!current_user_can('manage_learners')) {
            $this->sendError('Insufficient permissions.', 403);
            return;
        }

        $this->requireNonce('learners_nonce');

        $id = $this->input('id', 'int');

        if (!$id) {
            $this->sendError('Invalid learner ID');
            return;
        }

        $data = [
            'title' => $this->input('title', 'string'),
            'first_name' => $this->input('first_name', 'string'),
            'second_name' => $this->input('second_name', 'string'),
            'initials' => $this->input('initials', 'string'),
            'surname' => $this->input('surname', 'string'),
            'gender' => $this->input('gender', 'string'),
            'race' => $this->input('race', 'string'),
            'sa_id_no' => $this->input('sa_id_no', 'string'),
            'passport_number' => $this->input('passport_number', 'string'),
            'tel_number' => $this->input('tel_number', 'string'),
            'alternative_tel_number' => $this->input('alternative_tel_number', 'string'),
            'email_address' => $this->input('email_address', 'email'),
            'address_line_1' => $this->input('address_line_1', 'string'),
            'address_line_2' => $this->input('address_line_2', 'string'),
            'postal_code' => $this->input('postal_code', 'string'),
            'assessment_status' => $this->input('assessment_status', 'string'),
            'city_town_id' => $this->input('city_town_id', 'int'),
            'province_region_id' => $this->input('province_region_id', 'int'),
            'highest_qualification' => $this->input('highest_qualification', 'int'),
            'numeracy_level' => $this->input('numeracy_level', 'int'),
            'communication_level' => $this->input('communication_level', 'int'),
            'employer_id' => $this->input('employer_id', 'int'),
            'employment_status' => $this->input('employment_status', 'bool'),
            'disability_status' => $this->input('disability_status', 'bool'),
            'placement_assessment_date' => $this->input('placement_assessment_date', 'string'),
        ];

        $data = array_filter($data, fn($v) => $v !== null);

        if ($this->getLearnerService()->updateLearner($id, $data)) {
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
        if (!current_user_can('manage_learners')) {
            $this->sendError('Insufficient permissions.', 403);
            return;
        }

        $this->requireNonce('learners_nonce');

        $id = $this->input('id', 'int');

        if (!$id) {
            $this->sendError('Invalid learner ID');
            return;
        }

        if ($this->getLearnerService()->deleteLearner($id)) {
            $this->sendSuccess([], 'Learner deleted successfully');
        } else {
            $this->sendError('Failed to delete learner');
        }
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
        if (!current_user_can('manage_learners')) {
            return '<p>You do not have permission to access this content.</p>';
        }

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
                <?php wp_nonce_field('learners_nonce', 'nonce'); ?>

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
        if (!current_user_can('manage_learners')) {
            return '<p>You do not have permission to access this content.</p>';
        }

        $atts = shortcode_atts([
            'limit' => 10,
            'show_pagination' => true
        ], $atts);

        $service = $this->getLearnerService();
        $learners = $service->getLearners((int) $atts['limit']);
        $total = $service->getLearnerCount();

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
        if (!current_user_can('manage_learners')) {
            return '<p>You do not have permission to access this content.</p>';
        }

        $atts = shortcode_atts([
            'learner_id' => 0
        ], $atts);

        $learnerId = (int) $atts['learner_id'] ?: $this->query('learner_id', 'int') ?? 0;

        if (!$learnerId) {
            return '<div class="alert alert-warning">No learner ID specified.</div>';
        }

        $learner = $this->getLearnerService()->getLearner($learnerId);

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
