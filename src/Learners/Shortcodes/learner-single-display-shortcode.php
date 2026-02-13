<?php
/**
 * Single Learner Display Shortcode
 *
 * Displays detailed information for a single learner in a standalone page format
 * Uses LearnerController (MVC pattern)
 *
 * Usage: [wecoza_single_learner_display]
 * URL Parameter: learner_id (e.g., /view-learner/?learner_id=33)
 *
 * @package WeCoza\Learners\Shortcodes
 * @since 1.0.0
 */

namespace WeCoza\Learners\Shortcodes;

use WeCoza\Learners\Controllers\LearnerController;

if (!defined('ABSPATH')) {
    exit;
}

function wecoza_single_learner_display_shortcode() {
    // Get learner ID from URL
    $learner_id = isset($_GET['learner_id']) ? intval($_GET['learner_id']) : 0;

    if (!$learner_id) {
        return '<div class="alert alert-subtle-warning">No learner ID provided.</div>';
    }

    // Use LearnerController for MVC pattern
    $controller = new LearnerController();
    $learnerModel = $controller->getLearner($learner_id);

    if (!$learnerModel) {
        return '<div class="alert alert-subtle-danger">Learner not found.</div>';
    }

    // Convert model to stdClass for backward compatibility with template
    // Pass true to include NULL values - prevents "Undefined property" warnings for optional fields
    $learner = (object) $learnerModel->toDbArray(true);

    // Add formatted full name from model method (DRY)
    $learner->formatted_full_name = $learnerModel->getFormattedFullName();

    // Add joined field names that the template expects
    $learner->employer_name = $learnerModel->getEmployerName();
    $learner->city_town_name = $learnerModel->getCityTownName();
    $learner->province_region_name = $learnerModel->getProvinceRegionName();

    // Get portfolios via model
    $portfolios = $learnerModel->getPortfolios();
    $uploads_dir = wp_upload_dir();
    $uploads_url = $uploads_dir['baseurl'];

    // Enqueue necessary scripts
    wp_enqueue_script('learner-single-display', WECOZA_CORE_URL . 'assets/js/learners/learner-single-display.js', array('jquery'), WECOZA_CORE_VERSION, true);
    wp_enqueue_script('learner-progressions', WECOZA_CORE_URL . 'assets/js/learners/learner-progressions.js', array('jquery'), WECOZA_CORE_VERSION, true);
    wp_localize_script('learner-single-display', 'learnerSingleAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('learners_nonce'),
        'learnerId' => $learner_id,
        'homeUrl' => home_url(),
        'displayLearnersUrl' => home_url('app/all-learners'),
        'updateLearnerUrl' => home_url('app/update-learners'),
        'isAdmin' => current_user_can('manage_options')
    ));

    // Start output buffering
    ob_start();
    ?>

    <div class="wecoza-single-learner-display">
        <!-- Header with Back Button and Actions -->
        <div class="d-flex justify-content-end mb-4">
            <div class="btn-group btn-group-sm mt-2 me-2" role="group" aria-label="Learner Actions">
                <button class="btn btn-subtle-primary back-to-learners" type="button">Back To Learners</button>
                <button class="btn btn-subtle-success edit-learner-btn" type="button" data-id="<?php echo esc_attr($learner_id); ?>">Edit</button>
                <button class="btn btn-subtle-danger delete-learner-btn" type="button" data-id="<?php echo esc_attr($learner_id); ?>">Delete</button>
            </div>
        </div>

        <!-- Main Info Card -->
        <div class="card mb-3">
            <div class="card-body ydcoza-mini-card-header">
                <div class="row g-4 justify-content-between">
                    <!-- Name Card -->
                    <div class="col-sm-auto">
                        <div class="d-flex align-items-center">
                            <div class="d-flex bg-primary-subtle rounded flex-center me-3">
                                <i class="bi bi-person text-primary"></i>
                            </div>
                            <div>
                                <p class="fw-bold mb-1">Learner Name</p>
                                <h5 class="fw-bolder text-nowrap">
                                    <?php echo esc_html($learner->formatted_full_name); ?>
                                </h5>
                            </div>
                        </div>
                    </div>

                    <!-- ID Type Card -->
                    <div class="col-sm-auto">
                        <div class="d-flex align-items-center border-start-sm ps-sm-5">
                            <div class="d-flex bg-info-subtle rounded flex-center me-3">
                                <i class="bi bi-credit-card-2-front text-info"></i>
                            </div>
                            <div>
                                <p class="fw-bold mb-1">ID Type</p>
                                <h5 class="fw-bolder text-nowrap">
                                    <?php echo !empty($learner->sa_id_no) ? 'SA ID' : (!empty($learner->passport_number) ? 'Passport' : 'Not Specified'); ?>
                                </h5>
                            </div>
                        </div>
                    </div>

                    <!-- Employment Status Card -->
                    <div class="col-sm-auto">
                        <div class="d-flex align-items-center border-start-sm ps-sm-5">
                            <div class="d-flex bg-success-subtle rounded flex-center me-3">
                                <i class="bi bi-briefcase text-success"></i>
                            </div>
                            <div>
                                <p class="fw-bold mb-1">Employment Status</p>
                                <h5 class="fw-bolder text-nowrap">
                                    <?php
                                    $status = strtolower($learner->employment_status ?? '');
                                    $badgeClass = $status === 'employed' ? 'bg-success' : 'bg-warning';
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <?php echo esc_html($learner->employment_status ?? 'Unknown'); ?>
                                    </span>
                                </h5>
                            </div>
                        </div>
                    </div>

                    <!-- Qualification Card -->
                    <div class="col-sm-auto">
                        <div class="d-flex align-items-center border-start-sm ps-sm-5">
                            <div class="d-flex bg-warning-subtle rounded flex-center me-3">
                                <i class="bi bi-mortarboard text-warning"></i>
                            </div>
                            <div>
                                <p class="fw-bold mb-1">Highest Qualification</p>
                                <h5 class="fw-bolder text-nowrap">
                                    <?php echo esc_html($learner->highest_qualification ?? 'Not Specified'); ?>
                                </h5>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Card -->
                    <div class="col-sm-auto">
                        <div class="d-flex align-items-center border-start-sm ps-sm-5">
                            <div class="d-flex bg-primary-subtle rounded flex-center me-3">
                                <i class="bi bi-telephone text-primary"></i>
                            </div>
                            <div>
                                <p class="fw-bold mb-1">Contact</p>
                                <h5 class="fw-bolder text-nowrap">
                                    <a href="tel:<?php echo esc_attr($learner->tel_number); ?>" class="text-decoration-none">
                                        <?php echo esc_html($learner->tel_number); ?>
                                    </a>
                                </h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabbed Content Section -->
        <ul class="nav nav-underline fs-9 mb-3" id="learnerTab" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link active" id="personal-tab" data-bs-toggle="tab" href="#tab-personal" role="tab" aria-controls="tab-personal" aria-selected="true">Personal Information</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="assessment-tab" data-bs-toggle="tab" href="#tab-assessment" role="tab" aria-controls="tab-assessment" aria-selected="false">Assessment Details</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="status-tab" data-bs-toggle="tab" href="#tab-status" role="tab" aria-controls="tab-status" aria-selected="false">Current Status</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="portfolio-tab" data-bs-toggle="tab" href="#tab-portfolio" role="tab" aria-controls="tab-portfolio" aria-selected="false">Portfolio</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="progressions-tab" data-bs-toggle="tab" href="#tab-progressions" role="tab" aria-controls="tab-progressions" aria-selected="false">Progressions</a>
            </li>
        </ul>

        <div class="tab-content" id="learnerTabContent">
            <!-- Personal Information Tab -->
            <div class="tab-pane fade active show" id="tab-personal" role="tabpanel" aria-labelledby="personal-tab">
                <div class="px-xl-4 mb-7">
                    <div class="row mx-0">
                        <!-- Left Column - Personal Information -->
                        <div class="col-sm-12 col-xxl-6 border-bottom border-end-xxl py-3">
                            <table class="w-100 table-stats table table-hover table-sm fs-9 mb-0">
                                <tbody>
                                    <tr>
                                        <td class="py-2 ydcoza-w-150">
                                            <div class="d-inline-flex align-items-center">
                                                <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                    <i class="bi bi-hash text-primary" style="font-size: 12px;"></i>
                                                </div>
                                                <p class="fw-bold mb-0">Learner ID :</p>
                                            </div>
                                        </td>
                                        <td class="py-2">
                                            <p class="fw-semibold mb-0">#<?php echo esc_html($learner->id); ?></p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="py-2">
                                            <div class="d-flex align-items-center">
                                                <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                    <i class="bi bi-person-circle text-info" style="font-size: 12px;"></i>
                                                </div>
                                                <p class="fw-bold mb-0">Full Name :</p>
                                            </div>
                                        </td>
                                        <td class="py-2">
                                            <p class="fw-semibold mb-0"><?php echo esc_html($learner->formatted_full_name); ?></p>
                                            <?php if (!empty($learner->initials)): ?>
                                                <small class="text-muted">Initials: <?php echo esc_html($learner->initials); ?></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="py-2">
                                            <div class="d-flex align-items-center">
                                                <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                    <i class="bi bi-people text-primary" style="font-size: 12px;"></i>
                                                </div>
                                                <p class="fw-bold mb-0">Gender :</p>
                                            </div>
                                        </td>
                                        <td class="py-2">
                                            <p class="fw-semibold mb-0"><?php echo esc_html($learner->gender ?? ''); ?></p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="py-2">
                                            <div class="d-flex align-items-center">
                                                <div class="d-flex bg-success-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                    <i class="bi bi-globe text-success" style="font-size: 12px;"></i>
                                                </div>
                                                <p class="fw-bold mb-0">Race :</p>
                                            </div>
                                        </td>
                                        <td class="py-2">
                                            <p class="fw-semibold mb-0"><?php echo esc_html($learner->race ?? ''); ?></p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="py-2">
                                            <div class="d-flex align-items-center">
                                                <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                    <i class="bi bi-credit-card text-info" style="font-size: 12px;"></i>
                                                </div>
                                                <p class="fw-bold mb-0">ID Number :</p>
                                            </div>
                                        </td>
                                        <td class="py-2">
                                            <p class="fw-semibold mb-0">
                                                <?php echo esc_html($learner->sa_id_no ?: $learner->passport_number ?: 'Not provided'); ?>
                                            </p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="py-2">
                                            <div class="d-flex align-items-center">
                                                <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                    <i class="bi bi-telephone text-primary" style="font-size: 12px;"></i>
                                                </div>
                                                <p class="fw-bold mb-0">Phone :</p>
                                            </div>
                                        </td>
                                        <td class="py-2">
                                            <p class="fw-semibold mb-0">
                                                <a href="tel:<?php echo esc_attr($learner->tel_number); ?>" class="text-decoration-none">
                                                    <?php echo esc_html($learner->tel_number); ?>
                                                </a>
                                                <?php if (!empty($learner->alternative_tel_number)): ?>
                                                    <br><small class="text-muted">Alt: <?php echo esc_html($learner->alternative_tel_number); ?></small>
                                                <?php endif; ?>
                                            </p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="py-2">
                                            <div class="d-flex align-items-center">
                                                <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                    <i class="bi bi-envelope text-info" style="font-size: 12px;"></i>
                                                </div>
                                                <p class="fw-bold mb-0">Email :</p>
                                            </div>
                                        </td>
                                        <td class="py-2">
                                            <p class="fw-semibold mb-0">
                                                <a href="mailto:<?php echo esc_attr($learner->email_address); ?>" class="text-decoration-none">
                                                    <?php echo esc_html($learner->email_address); ?>
                                                </a>
                                            </p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="py-2">
                                            <div class="d-flex align-items-center">
                                                <div class="d-flex bg-success-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                    <i class="bi bi-geo-alt text-success" style="font-size: 12px;"></i>
                                                </div>
                                                <p class="fw-bold mb-0">Address :</p>
                                            </div>
                                        </td>
                                        <td class="py-2">
                                            <div class="fw-semibold mb-0">
                                                <?php if (!empty($learner->address_line_1)): ?>
                                                    <?php echo esc_html($learner->address_line_1); ?><br>
                                                <?php endif; ?>
                                                <?php if (!empty($learner->address_line_2)): ?>
                                                    <?php echo esc_html($learner->address_line_2); ?><br>
                                                <?php endif; ?>
                                                <?php if (!empty($learner->suburb)): ?>
                                                    <?php echo esc_html($learner->suburb); ?><br>
                                                <?php endif; ?>
                                                <?php echo esc_html($learner->city_town_name ?? ''); ?>
                                                <?php if (!empty($learner->province_region_name)): ?>
                                                    , <?php echo esc_html($learner->province_region_name); ?>
                                                <?php endif; ?>
                                                <?php if (!empty($learner->postal_code)): ?>
                                                    , <?php echo esc_html($learner->postal_code); ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Right Column - Professional Information -->
                        <div class="col-sm-12 col-xxl-6 border-bottom py-3">
                            <table class="w-100 table-stats table table-hover table-sm fs-9 mb-0">
                                <tbody>
                                    <tr>
                                        <td class="py-2 ydcoza-w-150">
                                            <div class="d-inline-flex align-items-center">
                                                <div class="d-flex bg-warning-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                    <i class="bi bi-mortarboard text-warning" style="font-size: 12px;"></i>
                                                </div>
                                                <p class="fw-bold mb-0">Qualification :</p>
                                            </div>
                                        </td>
                                        <td class="py-2">
                                            <p class="fw-semibold mb-0"><?php echo esc_html($learner->highest_qualification ?? 'Not specified'); ?></p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="py-2">
                                            <div class="d-flex align-items-center">
                                                <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                    <i class="bi bi-building text-info" style="font-size: 12px;"></i>
                                                </div>
                                                <p class="fw-bold mb-0">Employer :</p>
                                            </div>
                                        </td>
                                        <td class="py-2">
                                            <p class="fw-semibold mb-0"><?php echo esc_html($learner->employer_name ?? 'Not specified'); ?></p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="py-2">
                                            <div class="d-flex align-items-center">
                                                <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                    <i class="bi bi-briefcase text-primary" style="font-size: 12px;"></i>
                                                </div>
                                                <p class="fw-bold mb-0">Employment Status :</p>
                                            </div>
                                        </td>
                                        <td class="py-2">
                                            <p class="fw-semibold mb-0"><?php echo esc_html($learner->employment_status ?? ''); ?></p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="py-2">
                                            <div class="d-flex align-items-center">
                                                <div class="d-flex bg-success-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                    <i class="bi bi-heart-pulse text-success" style="font-size: 12px;"></i>
                                                </div>
                                                <p class="fw-bold mb-0">Disability Status :</p>
                                            </div>
                                        </td>
                                        <td class="py-2">
                                            <p class="fw-semibold mb-0"><?php echo esc_html($learner->disability_status ?? 'None'); ?></p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="py-2">
                                            <div class="d-flex align-items-center">
                                                <div class="d-flex bg-secondary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                    <i class="bi bi-calendar-check text-secondary" style="font-size: 12px;"></i>
                                                </div>
                                                <p class="fw-bold mb-0">Created :</p>
                                            </div>
                                        </td>
                                        <td class="py-2">
                                            <p class="fw-semibold mb-0">
                                                <?php echo date('F j, Y g:i a', strtotime($learner->created_at)); ?>
                                            </p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="py-2">
                                            <div class="d-flex align-items-center">
                                                <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                    <i class="bi bi-calendar-event text-primary" style="font-size: 12px;"></i>
                                                </div>
                                                <p class="fw-bold mb-0">Last Updated :</p>
                                            </div>
                                        </td>
                                        <td class="py-2">
                                            <p class="fw-semibold mb-0">
                                                <?php echo date('F j, Y g:i a', strtotime($learner->updated_at)); ?>
                                            </p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assessment Details Tab -->
            <div class="tab-pane fade" id="tab-assessment" role="tabpanel" aria-labelledby="assessment-tab">
                <div class="px-xl-4 mb-7">
                    <div class="row mx-0">
                        <div class="col-12 py-3">
                            <table class="w-100 table-stats table table-hover table-sm fs-9 mb-0">
                                <tbody>
                                    <tr>
                                        <td class="py-2 ydcoza-w-150">
                                            <div class="d-inline-flex align-items-center">
                                                <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                    <i class="bi bi-calendar-date text-info" style="font-size: 12px;"></i>
                                                </div>
                                                <p class="fw-bold mb-0">Assessment Date :</p>
                                            </div>
                                        </td>
                                        <td class="py-2">
                                            <p class="fw-semibold mb-0">
                                                <?php echo !empty($learner->placement_assessment_date) ?
                                                    date('F j, Y', strtotime($learner->placement_assessment_date)) :
                                                    'Not assessed'; ?>
                                            </p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="py-2">
                                            <div class="d-flex align-items-center">
                                                <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                    <i class="bi bi-check-circle text-primary" style="font-size: 12px;"></i>
                                                </div>
                                                <p class="fw-bold mb-0">Assessment Status :</p>
                                            </div>
                                        </td>
                                        <td class="py-2">
                                            <p class="fw-semibold mb-0"><?php echo esc_html($learner->assessment_status ?? 'Pending'); ?></p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="py-2">
                                            <div class="d-flex align-items-center">
                                                <div class="d-flex bg-warning-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                    <i class="bi bi-calculator text-warning" style="font-size: 12px;"></i>
                                                </div>
                                                <p class="fw-bold mb-0">Numeracy Level :</p>
                                            </div>
                                        </td>
                                        <td class="py-2">
                                            <p class="fw-semibold mb-0">
                                                <?php echo esc_html($learner->numeracy_level ?? 'Not assessed'); ?>
                                            </p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <td class="py-2">
                                            <div class="d-flex align-items-center">
                                                <div class="d-flex bg-success-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                                    <i class="bi bi-chat-dots text-success" style="font-size: 12px;"></i>
                                                </div>
                                                <p class="fw-bold mb-0">Communication Level :</p>
                                            </div>
                                        </td>
                                        <td class="py-2">
                                            <p class="fw-semibold mb-0">
                                                <?php echo esc_html($learner->communication_level ?? 'Not assessed'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Status Tab -->
            <div class="tab-pane fade" id="tab-status" role="tabpanel" aria-labelledby="status-tab">
                <div class="px-xl-4 mb-7">
                    <div class="row mx-0">
                        <div class="col-12 py-3">
                            <div class="alert alert-subtle-primary">
                                <i class="bi bi-info-circle me-2"></i>
                                Current class and progress information will be displayed here when available.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Portfolio Tab -->
            <div class="tab-pane fade" id="tab-portfolio" role="tabpanel" aria-labelledby="portfolio-tab">
                <div class="px-xl-4 mb-7">
                    <div class="row mx-0">
                        <div class="col-12 py-3">
                            <?php if (!empty($portfolios) && count($portfolios) > 0): ?>
                                <h6 class="mb-3">Portfolio Documents</h6>
                                <div class="list-group">
                                    <?php foreach ($portfolios as $index => $portfolio): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="bi bi-file-pdf text-danger me-2"></i>
                                                Portfolio <?php echo $index + 1; ?>
                                                <?php if (!empty($portfolio['upload_date'])): ?>
                                                    <small class="text-muted ms-2">
                                                        (Uploaded: <?php echo date('Y-m-d', strtotime($portfolio['upload_date'])); ?>)
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <a href="<?php echo esc_url($uploads_url . '/' . $portfolio['file_path']); ?>"
                                               download class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-download me-1"></i> Download
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-subtle-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    No portfolio documents uploaded yet.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progressions Tab -->
            <div class="tab-pane fade" id="tab-progressions" role="tabpanel" aria-labelledby="progressions-tab">
                <?php include WECOZA_CORE_DIR . 'views/learners/components/learner-progressions.php'; ?>
            </div>
        </div>

        <!-- Documents & Compliance Section -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    Documents & Portfolio Status
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Portfolio Status -->
                    <div class="col-md-6 mb-3">
                        <div class="d-flex align-items-start">
                            <div class="d-flex <?php echo !empty($portfolios) ? 'bg-success-subtle' : 'bg-warning-subtle'; ?> rounded-circle flex-center me-3" style="width:40px; height:40px">
                                <i class="bi bi-folder-check <?php echo !empty($portfolios) ? 'text-success' : 'text-warning'; ?>"></i>
                            </div>
                            <div class="flex-1">
                                <h6 class="mb-1">Portfolio Documents</h6>
                                <?php if (!empty($portfolios) && count($portfolios) > 0): ?>
                                    <p class="text-success mb-0">
                                        <i class="bi bi-check-circle me-1"></i>
                                        <?php echo count($portfolios); ?> document(s) uploaded
                                    </p>
                                <?php else: ?>
                                    <p class="text-warning mb-0">
                                        <i class="bi bi-exclamation-circle me-1"></i>
                                        No documents uploaded
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Assessment Status -->
                    <div class="col-md-6 mb-3">
                        <div class="d-flex align-items-start">
                            <div class="d-flex <?php echo $learner->assessment_status === 'Completed' ? 'bg-success-subtle' : 'bg-warning-subtle'; ?> rounded-circle flex-center me-3" style="width:40px; height:40px">
                                <i class="bi bi-clipboard-check <?php echo $learner->assessment_status === 'Completed' ? 'text-success' : 'text-warning'; ?>"></i>
                            </div>
                            <div class="flex-1">
                                <h6 class="mb-1">Assessment Status</h6>
                                <p class="<?php echo $learner->assessment_status === 'Completed' ? 'text-success' : 'text-warning'; ?> mb-0">
                                    <i class="bi <?php echo $learner->assessment_status === 'Completed' ? 'bi-check-circle' : 'bi-exclamation-circle'; ?> me-1"></i>
                                    <?php echo esc_html($learner->assessment_status ?? 'Pending'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    return ob_get_clean();
}

// Register shortcode
add_shortcode('wecoza_single_learner_display', __NAMESPACE__ . '\wecoza_single_learner_display_shortcode');
