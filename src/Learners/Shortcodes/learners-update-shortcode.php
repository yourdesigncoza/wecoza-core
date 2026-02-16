<?php
declare(strict_types=1);

/**
 * WeCoza Learners Update Form Shortcode
 *
 * This shortcode renders a form to update existing learner information
 * with validation and file handling.
 * Uses LearnerController (MVC pattern)
 *
 * Shortcode: [wecoza_learners_update_form]
 *
 * @package WeCoza\Learners\Shortcodes
 * @since 1.0.0
 */

namespace WeCoza\Learners\Shortcodes;

use WeCoza\Learners\Controllers\LearnerController;

if (!defined('ABSPATH')) {
    exit;
}

function wecoza_learners_update_form_shortcode($atts) {
    if (!current_user_can('manage_learners')) {
        return '<p>You do not have permission to access this content.</p>';
    }

    // Retrieve the saved URL for the redirect
    $redirect_update_url = get_option('wecoza_learners_update_form_url');

    // Initialize variables for form errors and data
    $form_error = false;
    $error_messages = [];
    $data = [];

    // Get learner ID from URL
    $learner_id = isset($_GET['learner_id']) ? intval($_GET['learner_id']) : 0;

    // Validate learner ID exists
    if (!$learner_id) {
        return '<div class="alert alert-subtle-danger">Invalid learner ID</div>';
    }

    // Use LearnerController for MVC pattern
    $controller = new LearnerController();
    $learnerModel = $controller->getLearner($learner_id);

    if (!$learnerModel) {
        return '<div class="alert alert-subtle-danger">Learner not found</div>';
    }

    // Convert model to stdClass for backward compatibility with form template
    // Pass true to include NULL values - prevents "Undefined property" warnings for optional fields
    $learner = (object) $learnerModel->toDbArray(true);
    // Add joined field names that the form expects
    $learner->highest_qualification = $learnerModel->getHighestQualificationName() ?? $learnerModel->getHighestQualification();

    // Fetch dropdown data via controller
    $dropdownData = $controller->getDropdownData();
    $locations = [
        'cities' => $dropdownData['cities'],
        'provinces' => $dropdownData['provinces']
    ];
    $employers = $dropdownData['employers'];
    $qualifications = $dropdownData['qualifications'];
    $portfolios = $learnerModel->getPortfolios();
    $existing_sponsor_ids = $controller->getSponsors($learner_id);

    // Enqueue learners-app.js for field toggling and validation
    wp_enqueue_script(
        'learners-app',
        wecoza_js_url('learners/learners-app.js'),
        ['jquery'],
        '1.0.0',
        true
    );

    // Check if form is submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wecoza_learners_update_form_nonce']) &&
        wp_verify_nonce($_POST['wecoza_learners_update_form_nonce'], 'submit_learners_update_form')) {

        wecoza_log('[learners-update-form] POST request received for learner_id: ' . $learner_id, 'debug');

        // Handle file upload if new file is provided
        $upload_dir = wp_upload_dir();
        $portfolios_dir = $upload_dir['basedir'] . '/portfolios/';

        if (!file_exists($portfolios_dir)) {
            wp_mkdir_p($portfolios_dir);
        }

        // Sanitize and prepare form inputs
        $data = [
            'id' => $learner_id,
            'title' => sanitize_text_field($_POST['title']),
            'first_name' => sanitize_text_field($_POST['first_name']),
            'second_name' => sanitize_text_field($_POST['second_name'] ?? ''),
            'initials' => sanitize_text_field($_POST['initials']),
            'surname' => sanitize_text_field($_POST['surname']),
            'gender' => sanitize_text_field($_POST['gender']),
            'race' => sanitize_text_field($_POST['race']),
            'sa_id_no' => sanitize_text_field($_POST['sa_id_no']),
            'passport_number' => sanitize_text_field($_POST['passport_number']),
            'tel_number' => sanitize_text_field($_POST['tel_number']),
            'alternative_tel_number' => sanitize_text_field($_POST['alternative_tel_number']),
            'email_address' => sanitize_email($_POST['email_address']),
            'address_line_1' => sanitize_text_field($_POST['address_line_1']),
            'address_line_2' => sanitize_text_field($_POST['address_line_2']),
            'city_town_id' => intval($_POST['city_town_id']),
            'province_region_id' => intval($_POST['province_region_id']),
            'postal_code' => sanitize_text_field($_POST['postal_code']),
            'highest_qualification' => intval($_POST['highest_qualification']),
            'assessment_status' => sanitize_text_field($_POST['assessment_status']),
            'placement_assessment_date' => (($d = \DateTime::createFromFormat('Y-m-d', $_POST['placement_assessment_date'] ?? '')) && $d->format('Y-m-d') === $_POST['placement_assessment_date']) ? $d->format('Y-m-d') : null,
            'numeracy_level' => intval($_POST['numeracy_level']),
            'communication_level' => intval($_POST['communication_level']),
            'employment_status' => isset($_POST['employment_status']) ? (int)filter_var($_POST['employment_status'], FILTER_VALIDATE_BOOLEAN) : 0,
            'employer_id' => intval($_POST['employer_id']),
            'disability_status' => isset($_POST['disability_status']) ? (int)filter_var($_POST['disability_status'], FILTER_VALIDATE_BOOLEAN) : 0,
            'scanned_portfolio' => $learner->scanned_portfolio,
            'updated_at' => current_time('mysql')
        ];

        // Proceed only if there are no errors
        if (!$form_error) {
            // Update learner data via controller (MVC pattern)
            if ($controller->updateLearner($learner_id, $data)) {
                // Save sponsors (replace all existing with submitted values)
                $sponsor_ids = !empty($_POST['sponsors']) && is_array($_POST['sponsors'])
                    ? array_map('intval', $_POST['sponsors'])
                    : [];
                $controller->saveSponsors($learner_id, $sponsor_ids);

                // Handle new file uploads if present
                if (isset($_FILES['scanned_portfolio']) && !empty($_FILES['scanned_portfolio']['name'][0])) {
                    $upload_result = $controller->savePortfolios($learner_id, $_FILES['scanned_portfolio']);

                    if ($upload_result['success']) {
                        echo '<div class="alert alert-subtle-success alert-dismissible fade show" role="alert">
                            Learner updated successfully! Files have been uploaded.
                            <div class="mt-2">
                                <small class="text-muted">Redirecting you to the learners list...</small>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';

                        echo '<script>
                            setTimeout(function() {
                                window.location.href = "' . esc_url($redirect_update_url) . '/?updated=true";
                            }, 2000);
                        </script>';
                        return;
                    } else {
                        echo '<div class="alert alert-subtle-warning alert-dismissible fade show" role="alert">
                            Learner information updated, but some files could not be uploaded: ' . esc_html($upload_result['message']) . '
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
                    }
                } else {
                    echo '<div class="alert alert-subtle-success alert-dismissible fade show" role="alert">
                        Learner information has been updated successfully!
                        <div class="mt-2">
                            <small class="text-muted">Redirecting you to the learners list...</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>';

                    echo '<script>
                        setTimeout(function() {
                            window.location.href = "' . esc_url($redirect_update_url) . '/?updated=true";
                        }, 2000);
                    </script>';
                    return;
                }
            } else {
                $error_messages[] = 'There was an error updating the learner. Please try again.';
            }
        } else {
            // Display any validation error messages
            foreach ($error_messages as $message) {
                echo '<div class="alert alert-subtle-danger">' . esc_html($message) . '</div>';
            }
        }
    }

    // Start building the form HTML
    ob_start();

    // Display any error messages
    if (!empty($error_messages)) {
        foreach ($error_messages as $message) {
            echo '<div class="alert alert-subtle-danger">' . esc_html($message) . '</div>';
        }
    }
    ?>
    <form id="learners-update-form" class="needs-validation ydcoza-compact-form" novalidate method="POST" enctype="multipart/form-data">
    <div class="container container-md ms-0">
        <?php wp_nonce_field('submit_learners_update_form', 'wecoza_learners_update_form_nonce'); ?>
        <input type="hidden" name="learner_id" value="<?php echo esc_attr($learner_id); ?>">
        <div class="row">
            <h6 class="mb-2">Personal Info.</h6>
            <div class="col-md-2">
                <!-- Title -->
                <div class="mb-1">
                    <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                    <select id="title" name="title" class="form-select form-select-sm" required>
                        <option value="">Select</option>
                        <option value="Mr" <?php echo ($learner->title ?? '') === 'Mr' ? 'selected' : ''; ?>>Mr</option>
                        <option value="Mrs" <?php echo ($learner->title ?? '') === 'Mrs' ? 'selected' : ''; ?>>Mrs</option>
                        <option value="Ms" <?php echo ($learner->title ?? '') === 'Ms' ? 'selected' : ''; ?>>Ms</option>
                        <option value="Miss" <?php echo ($learner->title ?? '') === 'Miss' ? 'selected' : ''; ?>>Miss</option>
                        <option value="Dr" <?php echo ($learner->title ?? '') === 'Dr' ? 'selected' : ''; ?>>Dr</option>
                        <option value="Prof" <?php echo ($learner->title ?? '') === 'Prof' ? 'selected' : ''; ?>>Prof</option>
                    </select>
                    <div class="invalid-feedback">Please select a title.</div>
                    <div class="valid-feedback">Looks good!</div>
                </div>
            </div>
            <div class="col-md-3">
                <!-- Personal Information Section -->
                <div class="mb-1">
                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="form-control form-control-sm" required
                           value="<?php echo esc_attr($learner->first_name); ?>">
                    <div class="invalid-feedback">Please provide a first name.</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="mb-1">
                    <label for="second_name" class="form-label">Second Name</label>
                    <input type="text" id="second_name" name="second_name" class="form-control form-control-sm"
                           value="<?php echo esc_attr($learner->second_name ?? ''); ?>">
                    <div class="invalid-feedback">Please provide a valid second name.</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="mb-1">
                    <label for="surname" class="form-label">Surname <span class="text-danger">*</span></label>
                    <input type="text" id="surname" name="surname" class="form-control form-control-sm" required
                           value="<?php echo esc_attr($learner->surname); ?>">
                    <div class="invalid-feedback">Please provide a surname.</div>
                </div>
            </div>
        </div>
        <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>
        <div class="row">
                <div class="col-md-2">
                    <div class="mb-1">
                        <label for="initials" class="form-label">Initials <span class="text-danger">*</span></label>
                        <input type="text" id="initials" name="initials" class="form-control form-control-sm" required readonly
                            value="<?php echo esc_attr($learner->initials); ?>">
                        <div class="form-text">Auto-generated from first & second name</div>
                        <div class="invalid-feedback">Please provide initials.</div>
                    </div>
                </div>
            <div class="col-md-3">
                <!-- Contact Information -->
                <div class="mb-1">
                    <label for="tel_number" class="form-label">Telephone Number <span class="text-danger">*</span></label>
                    <input type="text" id="tel_number" name="tel_number" class="form-control form-control-sm" required
                           value="<?php echo esc_attr($learner->tel_number); ?>">
                    <div class="invalid-feedback">Please provide a valid telephone number.</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="mb-1">
                    <label for="alternative_tel_number" class="form-label">Alternative Telephone Number</label>
                    <input type="text" id="alternative_tel_number" name="alternative_tel_number" class="form-control form-control-sm"
                           value="<?php echo esc_attr($learner->alternative_tel_number); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="mb-1">
                    <label for="email_address" class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input type="email" id="email_address" name="email_address" class="form-control form-control-sm" required
                           value="<?php echo esc_attr($learner->email_address); ?>">
                    <div class="invalid-feedback">Please provide a valid email address.</div>
                </div>
            </div>
        </div>
        <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>
        <div class="row">
            <h6 class="mb-2">ID, Race & Gender</h6>
            <div class="col-md-2">
                <!-- Identification Section -->
                <div class="mb-1">
                    <label class="form-label">Identification Type <span class="text-danger">*</span></label>
                    <div class="row">
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="id_type" id="sa_id_option" value="sa_id"
                                       <?php checked(!empty($learner->sa_id_no)); ?> required>
                                <label class="form-check-label" for="sa_id_option">SA ID</label>
                            </div>
                        </div>
                        <div class="col">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="id_type" id="passport_option" value="passport"
                                       <?php checked(!empty($learner->passport_number)); ?> required>
                                <label class="form-check-label" for="passport_option">Passport</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="id_fields_container" class="col-md-2">
                <div id="sa_id_field" class="mb-3 <?php echo empty($learner->sa_id_no) ? 'd-none' : ''; ?>">
                    <label for="sa_id_no" class="form-label">SA ID Number <span class="text-danger">*</span></label>
                    <input type="text" id="sa_id_no" name="sa_id_no" class="form-control form-control-sm" maxlength="13"
                           value="<?php echo esc_attr($learner->sa_id_no); ?>">
                    <div class="invalid-feedback">Please provide a valid SA ID number.</div>
                    <div class="valid-feedback">Valid ID number!</div>
                </div>

                <div id="passport_field" class="mb-3 <?php echo empty($learner->passport_number) ? 'd-none' : ''; ?>">
                    <label for="passport_number" class="form-label">Passport Number <span class="text-danger">*</span></label>
                    <input type="text" id="passport_number" name="passport_number" class="form-control form-control-sm" maxlength="12"
                           value="<?php echo esc_attr($learner->passport_number); ?>">
                    <div class="invalid-feedback">Please provide a valid passport number.</div>
                    <div class="valid-feedback">Valid passport number!</div>
                </div>
            </div>
            <div class="col-md-2">
                <!-- Race Field -->
                <div class="mb-1">
                    <label for="race" class="form-label">Race <span class="text-danger">*</span></label>
                    <select id="race" name="race" class="form-select form-select-sm" required>
                        <option value="">Select Race</option>
                        <?php
                        $races = ['Black', 'White', 'Coloured', 'Indian'];
                        foreach ($races as $race) {
                            echo '<option value="' . esc_attr($race) . '"' .
                                 selected($learner->race, $race, false) . '>' .
                                 esc_html($race) . '</option>';
                        }
                        ?>
                    </select>
                    <div class="invalid-feedback">Please select a race.</div>
                </div>
            </div>
            <div class="col-md-3">
                <!-- Gender -->
                <div class="mb-1">
                    <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                    <select id="gender" name="gender" class="form-select form-select-sm" required>
                        <option value="">Select Gender</option>
                        <option value="Male"<?php selected($learner->gender, 'Male'); ?>>Male</option>
                        <option value="Female"<?php selected($learner->gender, 'Female'); ?>>Female</option>
                    </select>
                    <div class="invalid-feedback">Please select a gender.</div>
                </div>
            </div>

        </div>
        <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>
        <div class="row">
            <h6 class="mb-2">Address Details</h6>
            <div class="col-md-3">
                <!-- Address Information -->
                <div class="mb-1">
                    <label for="address_line_1" class="form-label">Address Line 1 <span class="text-danger">*</span></label>
                    <input type="text" id="address_line_1" name="address_line_1" class="form-control form-control-sm" required
                           value="<?php echo esc_attr($learner->address_line_1); ?>">
                    <div class="invalid-feedback">Please provide a valid address.</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="mb-1">
                    <label for="address_line_2" class="form-label">Address Line 2</label>
                    <input type="text" id="address_line_2" name="address_line_2" class="form-control form-control-sm"
                           value="<?php echo esc_attr($learner->address_line_2); ?>">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-2">
                <!-- City/Town Dropdown -->
                <div class="mb-1">
                    <label for="city_town_id" class="form-label">City/Town <span class="text-danger">*</span></label>
                    <select id="city_town_id" name="city_town_id" class="form-select form-select-sm" required>
                        <option value="">Select City/Town</option>
                        <?php foreach ($locations['cities'] as $city): ?>
                            <option value="<?php echo esc_attr($city['location_id']); ?>"
                                    <?php selected($learner->city_town_id, $city['location_id']); ?>>
                                <?php echo esc_html($city['town']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Please select a valid city or town.</div>
                </div>
            </div>
            <div class="col-md-2">
                <!-- Province/Region Dropdown -->
                <div class="mb-1">
                    <label for="province_region_id" class="form-label">Province/Region <span class="text-danger">*</span></label>
                    <select id="province_region_id" name="province_region_id" class="form-select form-select-sm" required>
                        <option value="">Select Province/Region</option>
                        <?php foreach ($locations['provinces'] as $province): ?>
                            <option value="<?php echo esc_attr($province['location_id']); ?>"
                                    <?php selected($learner->province_region_id, $province['location_id']); ?>>
                                <?php echo esc_html($province['province']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Please select a valid province or region.</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="mb-1">
                    <label for="postal_code" class="form-label">Postal Code <span class="text-danger">*</span></label>
                    <input type="text" id="postal_code" name="postal_code" class="form-control form-control-sm" required
                           value="<?php echo esc_attr($learner->postal_code); ?>">
                    <div class="invalid-feedback">Please provide a valid postal code.</div>
                </div>
            </div>
        </div>
        <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>
        <div class="row">
            <h6 class="mb-2">Educational & Disability Details</h6>
            <div class="col-md-3">
            <!-- Assessment Information -->
            <div class="mb-1">
            <label for="highest_qualification" class="form-label">Highest Qualification <span class="text-danger">*</span></label>
            <select id="highest_qualification" name="highest_qualification" class="form-select form-select-sm">
                <option value="">Select Highest Qualification</option>
                <?php foreach ($qualifications as $qualification): ?>
                    <option value="<?php echo esc_attr($qualification['id']); ?>"
                            <?php selected($learner->highest_qualification, $qualification['qualification']); ?>>
                        <?php echo esc_attr($qualification['qualification']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Please select an employer.</div>
            </div>
            </div>
            <div class="col-md-2">
                <!-- Disability Status -->
                <div class="mb-1">
                    <label for="disability_status" class="form-label">Disability Status <span class="text-danger">*</span></label>
                    <select id="disability_status" name="disability_status" class="form-select form-select-sm" required>
                        <option value="0" <?php echo $learner->disability_status == 0 ? 'selected' : ''; ?>>Not Disabled</option>
                        <option value="1" <?php echo $learner->disability_status == 1 ? 'selected' : ''; ?>>Has Disability</option>
                    </select>
                    <div class="invalid-feedback">Please provide your disability status.</div>
                </div>
            </div>
            <div class="col-md-3">
                <!-- Portfolio Upload -->
                <div class="mb-1">
                    <div class="mt-0">
                        <label class="form-label">Add New Portfolio Files:</label>
                        <input type="file"
                               id="scanned_portfolio"
                               name="scanned_portfolio[]"
                               class="form-control form-control-sm"
                               accept="application/pdf"
                               multiple>
                        <div class="form-text small" style="font-size:12px">You can select multiple PDF files to upload.</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                    <?php
                    if (!empty($portfolios)): ?>
                        <div class="mb-1">
                            <?php foreach ($portfolios as $index => $portfolio): ?>
                                <div class="d-flex align-items-center mb-0 portfolio-item mt-4">
                                    <span class="me-2 small" style="font-size:12px">Portfolio <?php echo $index + 1; ?></span>
                                    <span class="text-muted me-2 small" style="font-size:12px">
                                        (Uploaded: <?php echo wp_date('Y-m-d', strtotime($portfolio['upload_date'])); ?>)
                                    </span>
                                    <button type="button"
                                            class="delete-portfolio btn btn-subtle-danger"
                                            data-portfolio-id="<?php echo esc_attr($portfolio['portfolio_id']); ?>"
                                            data-learner-id="<?php echo esc_attr($learner_id); ?>">
                                        Delete
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
            </div>
        </div>
        <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>
        <div class="row">
            <h6 class="mb-2">Assessment Details</h6>
            <div class="col-md-2">
                <div class="mb-1">
                    <label for="assessment_status" class="form-label">Assessment Status <span class="text-danger">*</span></label>
                    <select id="assessment_status" name="assessment_status" class="form-select form-select-sm" required>
                        <option value="">Select Assessment Status</option>
                        <option value="Assessed" <?php selected($learner->assessment_status, 'Assessed'); ?>>Assessed</option>
                        <option value="Not Assessed" <?php selected($learner->assessment_status, 'Not Assessed'); ?>>Not Assessed</option>
                    </select>
                    <div class="invalid-feedback">Please select an assessment status.</div>
                </div>
            </div>
            <div class="col-md-2">
                <!-- Communication Placement Level -->
                <div class="mb-1 placement_date_outerdiv" id="placement_level_div" <?php echo $learner->assessment_status !== 'Assessed' ? 'style="display:none;"' : ''; ?>>
                    <label for="communication_level" class="form-label">Assessment Comm Level <span class="text-danger">*</span></label>
                    <select id="communication_level" name="communication_level" class="form-select form-select-sm">
                        <option value="">Loading...</option>
                    </select>
                    <div class="invalid-feedback">Please select a Assessment Communication Level.</div>
                </div>
            </div>
            <div class="col-md-2">
                <!-- Numeracy Placement Level -->
                <div class="mb-1 placement_date_outerdiv" id="placement_level_div" <?php echo $learner->assessment_status !== 'Assessed' ? 'style="display:none;"' : ''; ?>>
                    <label for="numeracy_level" class="form-label">Assessment Num Level <span class="text-danger">*</span></label>
                    <select id="numeracy_level" name="numeracy_level" class="form-select form-select-sm">
                    <option value="">Loading...</option>
                    </select>
                    <div class="invalid-feedback">Please select a Assessment Communication Level.</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="mb-1 placement_date_outerdiv" id="placement_date_div" <?php echo $learner->assessment_status !== 'Assessed' ? 'style="display:none;"' : ''; ?>>
                    <label for="placement_assessment_date" class="form-label">Placement Assessment Date <span class="text-danger">*</span></label>
                    <input type="date" id="placement_assessment_date" name="placement_assessment_date" class="form-control form-control-sm"
                           value="<?php echo esc_attr($learner->placement_assessment_date); ?>">
                    <div class="invalid-feedback">Please select the placement assessment date.</div>
                </div>
            </div>
        </div>
        <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>
        <div class="row">
            <div class="col-md-3">
                <!-- Employment Information -->
                <div class="mb-1">
                    <label for="employment_status" class="form-label">Employment Status <span class="text-danger">*</span></label>
                    <select id="employment_status" name="employment_status" class="form-select form-select-sm" required>
                        <option value="">Select Employment Status</option>
                        <option value="1" <?php selected($learner->employment_status, "Employed"); ?>>Employed</option>
                        <option value="0" <?php selected($learner->employment_status, "Unemployed"); ?>>Unemployed</option>
                    </select>
                    <div class="invalid-feedback">Please select an employment status.</div>
                </div>
            </div>
            <div class="col-md-3">
                <div id="employer_field" class="mb-1" <?php echo $learner->employment_status !== 'Employed' ? 'style="display:none;"' : ''; ?>>
                    <label for="employer_id" class="form-label">Employer <span class="text-danger">*</span></label>
                    <select id="employer_id" name="employer_id" class="form-select form-select-sm">
                        <option value="">Select Employer</option>
                        <?php foreach ($employers as $employer): ?>
                            <option value="<?php echo esc_attr($employer['employer_id']); ?>"
                                    <?php selected($learner->employer_id, $employer['employer_id']); ?>>
                                <?php echo esc_html($employer['employer_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Please select an employer.</div>
                </div>
            </div>

            <!-- Sponsored By Section -->
            <div class="col-md-3">
                <label class="form-label">Sponsored By</label>
                <div id="sponsor_container">
                    <!-- Sponsor input groups will be appended here -->
                </div>
                <button type="button" class="btn btn-subtle-primary btn-sm me-1 mb-1" id="add_sponsor_btn">+ Add Sponsor</button>
            </div>

        <!-- Sponsor Input Group Template -->
        <div id="sponsor_template" class="d-none">
            <div class="input-group mb-2 sponsor-group">
                <select name="sponsors[]" class="form-select form-select-sm sponsor-select">
                    <option value="">Select Sponsor</option>
                    <!-- Populate dynamically -->
                </select>
                <button type="button" class="btn btn-sm btn-subtle-danger remove_sponsor_btn">Remove</button>
                <div class="invalid-feedback">Please select a sponsor.</div>
            </div>
        </div>

        </div>


        <!-- Form Actions -->
        <div class="mt-4">
            <button type="submit" class="btn btn-primary btn-sm">Update Learner</button>
            <a href="<?php echo home_url(); ?>/update-learners/" class="btn btn-secondary btn-sm">Cancel</a>
        </div>
    </div> <!-- ./container container-md -->
    </form>

    <script>
    jQuery(document).ready(function($) {
        // Form validation
        (function() {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                        console.log('[learners-update-form] Validation failed - form blocked from submitting');
                        // Log which fields are invalid
                        var invalidFields = form.querySelectorAll(':invalid');
                        invalidFields.forEach(function(field) {
                            console.log('[learners-update-form] Invalid field:', field.name || field.id, 'Type:', field.type, 'Hidden:', field.offsetParent === null);
                        });
                    } else {
                        console.log('[learners-update-form] Validation passed - form submitting');
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();

        // Note: Initials generation is now handled globally by learners-app.js

        // Add Sponsor Input Group functionality
        $('#add_sponsor_btn').click(function() {
            var newSponsor = $('#sponsor_template').clone().removeAttr('id').removeClass('d-none');
            newSponsor.find('.remove_sponsor_btn').click(function() {
                $(this).closest('.sponsor-group').remove();
            });
            $('#sponsor_container').append(newSponsor);
        });
    });

    // Global variable to store employers data for sponsors
    var employersData = [];

    document.addEventListener('DOMContentLoaded', function() {
        // Fetch dropdown data via AJAX
        fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=fetch_learners_dropdown_data&nonce=<?php echo wp_create_nonce('learners_nonce'); ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Store employers data globally for sponsor dropdowns
                    employersData = data.data.employers;

                    // Populate the sponsor template dropdown
                    const templateSelect = document.querySelector('#sponsor_template .sponsor-select');
                    if (templateSelect && employersData) {
                        employersData.forEach(function(employer) {
                            const option = document.createElement('option');
                            option.value = employer.id;
                            option.textContent = employer.name;
                            templateSelect.appendChild(option);
                        });
                    }

                    // Pre-populate existing sponsors
                    var existingSponsorIds = <?php echo json_encode(array_map('intval', $existing_sponsor_ids)); ?>;
                    if (existingSponsorIds.length > 0 && employersData) {
                        existingSponsorIds.forEach(function(sponsorId) {
                            var $newSponsor = jQuery('#sponsor_template').clone().removeAttr('id').removeClass('d-none');
                            $newSponsor.find('.sponsor-select').val(String(sponsorId));
                            $newSponsor.find('.remove_sponsor_btn').click(function() {
                                jQuery(this).closest('.sponsor-group').remove();
                            });
                            jQuery('#sponsor_container').append($newSponsor);
                        });
                    }

                    // Populate Communication Levels
                    const communicationSelect = document.getElementById('communication_level');
                    communicationSelect.innerHTML = data.data.placement_levels.communication_levels.map(level =>
                        `<option value="${level.id}" ${level.name == <?php echo json_encode($learner->communication_level); ?> ? 'selected' : ''}>
                            ${level.name}
                        </option>`
                    ).join('');

                    // Populate Numeracy Levels
                    const numeracySelect = document.getElementById('numeracy_level');
                    numeracySelect.innerHTML = data.data.placement_levels.numeracy_levels.map(level =>
                        `<option value="${level.id}" ${level.name == <?php echo json_encode($learner->numeracy_level); ?> ? 'selected' : ''}>
                            ${level.name}
                        </option>`
                    ).join('');
                } else {
                    console.error('Failed to load placement levels:', data);
                }
            })
            .catch(error => console.error('Error fetching dropdown data:', error));
    });
    </script>


    <?php
    return ob_get_clean();
}

// Register shortcode
add_shortcode('wecoza_learners_update_form', __NAMESPACE__ . '\wecoza_learners_update_form_shortcode');
