<?php
declare(strict_types=1);

/**
 * WeCoza Learners Capture Form Shortcode
 *
 * This shortcode handles the learner registration form including:
 * - Form rendering with Bootstrap 5 styling
 * - Server-side validation and sanitization
 * - File upload handling for PDF portfolios
 * - Database insertion via LearnerController (MVC pattern)
 * - AJAX-based dropdown population
 *
 * Shortcode: [wecoza_learners_form]
 *
 * @package WeCoza\Learners\Shortcodes
 * @since 1.0.0
 */

namespace WeCoza\Learners\Shortcodes;

use WeCoza\Learners\Services\LearnerService;

if (!defined('ABSPATH')) {
    exit;
}

function wecoza_learners_form_shortcode($atts) {
    // Initialize variables for form errors and data
    $form_error = false;
    $error_messages = [];
    $data = [];

    // Use LearnerService for business logic
    $service = new LearnerService();

    // Check if form is submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wecoza_learners_form_nonce']) && wp_verify_nonce($_POST['wecoza_learners_form_nonce'], 'submit_learners_form')) {

        // Initialize variables
        $scanned_portfolio_path = '';
        $form_error = false;
        $error_messages = [];

        // Sanitize and prepare form inputs
        $data = [
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
            'employment_status' => isset($_POST['employment_status']) && $_POST['employment_status'] !== '' ? (int) filter_var($_POST['employment_status'], FILTER_VALIDATE_BOOLEAN) : 0,
            'employer_id' => intval($_POST['employer_id']),
            'disability_status' => isset($_POST['disability_status']) && $_POST['disability_status'] !== '' ? (int) filter_var($_POST['disability_status'], FILTER_VALIDATE_BOOLEAN) : 0,
            'scanned_portfolio' => $scanned_portfolio_path,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        // Validate SA ID or Passport
        if (empty($_POST['sa_id_no']) && empty($_POST['passport_number'])) {
            $form_error = true;
            $error_messages[] = 'You must provide either SA ID Number or Passport Number.';
        }

        // Proceed only if there are no errors
        if (!$form_error) {
            // Ensure date fields are valid
            $data['placement_assessment_date'] = !empty($data['placement_assessment_date']) ? $data['placement_assessment_date'] : null;

            // Initialize scanned_portfolio as empty string
            $data['scanned_portfolio'] = '';

            // Validate employer_id and numeracy_level
            $data['employer_id'] = !empty($data['employer_id']) ? $data['employer_id'] : null;
            $data['numeracy_level'] = !empty($data['numeracy_level']) ? $data['numeracy_level'] : null;
            $data['communication_level'] = !empty($data['communication_level']) ? $data['communication_level'] : null;

            // Create learner using LearnerService
            $learner = $service->createLearner($data);

            if ($learner) {
                $learner_id = $learner->getId();
                echo '<div class="alert alert-sublte-success alert-dismissible fade show ydcoza-notification ydcoza-auto-close" role="alert"><div class="d-flex gap-4"><span><i class="fa-solid fa-circle-check icon-success"></i></span><div>Learner Added successfully!</div></div><button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button></div>';

                // Handle file uploads if files were submitted
                if (isset($_FILES['scanned_portfolio']) && !empty($_FILES['scanned_portfolio']['name'][0])) {
                    $upload_result = $service->savePortfolios($learner_id, $_FILES['scanned_portfolio']);
                    if (!$upload_result['success']) {
                        echo '<div class="alert alert-subtle-danger alert-dismissible fade show ydcoza-notification" role="alert"><button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button><div class="d-flex gap-4"><span><i class="fa-solid fa-circle-exclamation icon-danger"></i></span><div class="d-flex flex-column gap-2"><h6 class="mb-0">ERROR !</h6><p class="mb-0">Some files could not be uploaded: ' . esc_html($upload_result['message']) . '</p></div></div></div>';
                    }
                }

                // Save sponsors if any were selected
                if (!empty($_POST['sponsors']) && is_array($_POST['sponsors'])) {
                    $sponsor_ids = array_map('intval', $_POST['sponsors']);
                    $service->saveSponsors($learner_id, $sponsor_ids);
                }
            } else {
                echo '<div class="alert alert-subtle-danger alert-dismissible fade show ydcoza-notification" role="alert"><button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button><div class="d-flex gap-4"><span><i class="fa-solid fa-circle-exclamation icon-danger"></i></span><div class="d-flex flex-column gap-2"><h6 class="mb-0">ERROR !</h6><p class="mb-0">There was an error inserting the learner. Please try again.</p></div></div></div>';
            }
        } else {
            // Display all error messages
            foreach ($error_messages as $message) {
                echo '<p class="text-danger">' . esc_html($message) . '</p>';
            }
        }
    }

    // Render the Form with Bootstrap 5
    ob_start();
    ?>
    <form id="learners-form" class="needs-validation ydcoza-compact-form" novalidate method="POST" enctype="multipart/form-data">
        <?php wp_nonce_field('submit_learners_form', 'wecoza_learners_form_nonce'); ?>
        <div class="row">
            <h6 class="mb-2">Personal Info.</h6>
            <div class="col-md-2">
                <!-- Title -->
                <div class="mb-1">
                    <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                    <select id="title" name="title" class="form-select form-select-sm" required>
                        <option value="">Select</option>
                        <option value="Mr">Mr</option>
                        <option value="Mrs">Mrs</option>
                        <option value="Ms">Ms</option>
                        <option value="Miss">Miss</option>
                        <option value="Dr">Dr</option>
                        <option value="Prof">Prof</option>
                    </select>
                    <div class="invalid-feedback">Please select a title.</div>
                    <div class="valid-feedback">Looks good!</div>
                </div>
            </div>
            <div class="col-md-3">
                <!-- First Name -->
                <div class="mb-1">
                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="form-control form-control-sm" required>
                    <div class="invalid-feedback">Please provide a first name.</div>
                    <div class="valid-feedback">Looks good!</div>
                </div>
            </div>
            <div class="col-md-3">
                <!-- Second Name -->
                <div class="mb-1">
                    <label for="second_name" class="form-label">Second Name</label>
                    <input type="text" id="second_name" name="second_name" class="form-control form-control-sm">
                    <div class="invalid-feedback">Please provide a valid second name.</div>
                </div>
            </div>
            <div class="col-md-3">
                <!-- Surname -->
                <div class="mb-1">
                    <label for="surname" class="form-label">Surname <span class="text-danger">*</span></label>
                    <input type="text" id="surname" name="surname" class="form-control form-control-sm" required>
                    <div class="invalid-feedback">Please provide a surname.</div>
                    <div class="valid-feedback">Looks good!</div>
                </div>
            </div>
        </div> <!-- ./row -->
        <div class="row">
            <div class="col-md-2">
                <!-- Initials -->
                <div class="mb-1">
                    <label for="initials" class="form-label">Initials <span class="text-danger">*</span></label>
                    <input type="text" id="initials" name="initials" class="form-control form-control-sm" required readonly>
                    <div class="form-text text-muted">Generated from first & second name</div>
                    <div class="invalid-feedback">Please provide initials.</div>
                    <div class="valid-feedback">Looks good!</div>
                </div>
            </div>
            <div class="col-md-3">
                <!-- Telephone Number -->
                <div class="mb-1">
                    <label for="tel_number" class="form-label">Telephone Number <span class="text-danger">*</span></label>
                    <input type="text" id="tel_number" name="tel_number" class="form-control form-control-sm" required>
                    <div class="invalid-feedback">Please provide a valid telephone number.</div>
                    <div class="valid-feedback">Looks good!</div>
                </div>
            </div>
            <div class="col-md-3">
                <!-- Alternative Telephone Number -->
                <div class="mb-1">
                    <label for="alternative_tel_number" class="form-label">Alternative Telephone Number</label>
                    <input type="text" id="alternative_tel_number" name="alternative_tel_number" class="form-control form-control-sm">
                    <div class="invalid-feedback">Please provide a valid alternative telephone number.</div>
                </div>
            </div>
            <div class="col-md-3">
                <!-- Email Address -->
                <div class="mb-1">
                    <label for="email_address" class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input type="email" id="email_address" name="email_address" class="form-control form-control-sm" required>
                    <div class="invalid-feedback">Please provide a valid email address.</div>
                    <div class="valid-feedback">Looks good!</div>
                </div>
            </div>
        </div> <!-- ./row -->
        <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>
        <div class="row">
            <h6 class="mb-2">ID, Race & Gender</h6>
            <div class="col-md-2">
                <!-- Radio buttons for ID or Passport selection -->
                <div class="mb-1">
                    <label class="form-label">Identification Type <span class="text-danger">*</span></label>
                    <div id="id_type_container" class="row">
                        <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="id_type" id="sa_id_option" value="sa_id" required>
                            <label class="form-check-label" for="sa_id_option">
                                SA ID
                            </label>
                        </div>
                        </div>
                        <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="id_type" id="passport_option" value="passport" required>
                            <label class="form-check-label" for="passport_option">
                                Passport
                            </label>
                        </div>
                        </div>
                </div>
                    <div class="invalid-feedback">Please select an identification type.</div>
                </div>
            </div>
            <div id="id_fields_container" class="col-md-2 d-none">
                <!-- SA ID Number (Initially Hidden) -->
                <div id="sa_id_field" class="mb-3 d-none">
                    <label for="sa_id_no" class="form-label">SA ID Number <span class="text-danger">*</span></label>
                    <input type="text" id="sa_id_no" name="sa_id_no" class="form-control form-control-sm" maxlength="13">
                    <div class="invalid-feedback">Please provide a valid SA ID number.</div>
                    <div class="valid-feedback">Valid ID number!</div>
                </div>

                <!-- Passport Number (Initially Hidden) -->
                <div id="passport_field" class="mb-3 d-none">
                    <label for="passport_number" class="form-label">Passport Number <span class="text-danger">*</span></label>
                    <input type="text" id="passport_number" name="passport_number" class="form-control form-control-sm" maxlength="12">
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
                        <option value="Black">Black</option>
                        <option value="White">White</option>
                        <option value="Coloured">Coloured</option>
                        <option value="Indian">Indian</option>
                    </select>
                    <div class="invalid-feedback">Please select a race.</div>
                </div>
            </div>
            <div class="col-md-2">
            <!-- Gender -->
                <div class="mb-1">
                    <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                    <select id="gender" name="gender" class="form-select form-select-sm" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                    <div class="invalid-feedback">Please select a gender.</div>
                    <div class="valid-feedback">Looks good!</div>
                </div>
            </div>
        </div>
        <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>
        <div class="row">
            <h6 class="mb-2">Address Details</h6>
            <div class="col-md-3">
                <!-- Address Line 1 -->
                <div class="mb-1">
                    <label for="address_line_1" class="form-label">Address Line 1 <span class="text-danger">*</span></label>
                    <input type="text" id="address_line_1" name="address_line_1" class="form-control form-control-sm" required>
                    <div class="invalid-feedback">Please provide a valid address.</div>
                    <div class="valid-feedback">Looks good!</div>
                </div>
            </div>
            <div class="col-md-3">
                <!-- Address Line 2 -->
                <div class="mb-1">
                    <label for="address_line_2" class="form-label">Address Line 2</label>
                    <input type="text" id="address_line_2" name="address_line_2" class="form-control form-control-sm">
                    <div class="invalid-feedback">Please provide a valid address line 2.</div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-2">
                <!-- City/Town (Foreign Key) -->
                <div class="mb-1">
                    <label for="city_town_id" class="form-label">City/Town <span class="text-danger">*</span></label>
                    <select id="city_town_id" name="city_town_id" class="form-select form-select-sm" required>
                        <option value="">Loading Cities ...</option>
                    </select>
                    <div class="invalid-feedback">Please select a valid city or town.</div>
                    <div class="valid-feedback">Looks good!</div>
                </div>
            </div>
            <div class="col-md-2">
                <!-- Province/Region (Foreign Key) -->
                <div class="mb-1">
                    <label for="province_region_id" class="form-label">Province/Region <span class="text-danger">*</span></label>
                    <select id="province_region_id" name="province_region_id" class="form-select form-select-sm" required>
                        <option value="">Loading provinces...</option>
                    </select>
                    <div class="invalid-feedback">Please select a valid province or region.</div>
                    <div class="valid-feedback">Looks good!</div>
                </div>
            </div>
            <div class="col-md-2">
                <!-- Postal Code -->
                <div class="mb-1">
                    <label for="postal_code" class="form-label">Postal Code <span class="text-danger">*</span></label>
                    <input type="text" id="postal_code" name="postal_code" class="form-control form-control-sm" required>
                    <div class="invalid-feedback">Please provide a valid postal code.</div>
                    <div class="valid-feedback">Looks good!</div>
                </div>
            </div>
        </div>
        <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>
        <div class="row">
            <h6 class="mb-2">Educational & Disability Details</h6>
            <div class="col-md-3">
                <div class="mb-1">
                    <label for="highest_qualification" class="form-label">Highest Qualification <span class="text-danger">*</span></label>
                    <select id="highest_qualification" name="highest_qualification" class="form-select form-select-sm">
                        <option value="">Loading qualifications...</option>
                    </select>
                    <div class="invalid-feedback">Please select a Qualification.</div>
                </div>
            </div>
            <div class="col-md-2">
                <!-- Disability Status -->
                <div class="mb-1">
                    <label for="disability_status" class="form-label">Disability Status <span class="text-danger">*</span></label>
                    <select id="disability_status" name="disability_status" class="form-select form-select-sm" required>
                        <option value="">Select Disability Status</option>
                        <option value="0">Not Disabled</option>
                        <option value="1">Has Disability</option>
                    </select>
                    <div class="invalid-feedback">Please provide your disability status.</div>
                </div>
            </div>
            <div class="col-md-3">
                <!-- Scanned Portfolio Upload -->
                <div class="mb-1">
                    <label for="scanned_portfolio" class="form-label">Assesment Report (PDF only)</label>
                    <input type="file" id="scanned_portfolio" name="scanned_portfolio[]" class="form-control form-control-sm" accept="application/pdf" multiple>
                    <div class="invalid-feedback">Please upload a valid PDF file.</div>
                </div>
            </div>
        </div>
        <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>
        <div class="row">
            <h6 class="mb-2">Assessment Details</h6>
            <div class="col-md-2">
                <!-- Assessment Status -->
                <div class="mb-1">
                    <label for="assessment_status" class="form-label">Assessment Status <span class="text-danger">*</span></label>
                    <select id="assessment_status" name="assessment_status" class="form-select form-select-sm" required>
                        <option value="">Select Assessment Status</option>
                        <option value="Assessed">Assessed</option>
                        <option value="Not Assessed">Not Assessed</option>
                    </select>
                    <div class="invalid-feedback">Please select an assessment status.</div>
                </div>
            </div>
            <div class="col-md-2">
                <!--  Communication Level -->
                <div class="mb-3 initial_communication_level d-none">
                    <label for="communication_level" class="form-label">Assessment Communication Level.<span class="text-danger">*</span></label>
                    <select id="communication_level" name="communication_level" class="form-select form-select-sm" required>
                        <option value="">Loading communication levels...</option>
                    </select>
                    <div class="invalid-feedback">Please select the Assesed Communication Level.</div>
                </div>
            </div>
            <div class="col-md-2">
                <!-- Numeracy Level -->
                <div class="mb-3 placement_date_outerdiv d-none">
                    <label for="numeracy_level" class="form-label">Assessment Numeracy Level.<span class="text-danger">*</span></label>
                    <select id="numeracy_level" name="numeracy_level" class="form-select form-select-sm">
                        <option value="">Loading placement levels...</option>
                    </select>
                    <div class="invalid-feedback">Please select a Placement Level.</div>
                </div>
            </div>
            <div class="col-md-2">
                <!-- Placement Assessment Date (initially hidden) -->
                <div class="mb-3 placement_date_outerdiv d-none">
                    <label for="placement_assessment_date" class="form-label">Assessment Date.<span class="text-danger">*</span></label>
                    <input type="date" id="placement_assessment_date" name="placement_assessment_date" class="form-control form-control-sm">
                    <div class="invalid-feedback">Please select the placement assessment date.</div>
                </div>
            </div>
        </div>
        <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>
        <div class="row">
            <h6 class="mb-2">Employment Details</h6>
            <div class="col-md-2">
                <!-- Employment Status -->
                <div class="mb-1">
                    <label for="employment_status" class="form-label">Employment Status <span class="text-danger">*</span></label>
                    <select id="employment_status" name="employment_status" class="form-select form-select-sm" required>
                        <option value="">Employment Status</option>
                        <option value="1">Employed</option>
                        <option value="0">Unemployed</option>
                    </select>
                    <div class="form-text text-muted">Select Employment Status</div>
                    <div class="invalid-feedback">Please select an employment status.</div>
                </div>
            </div>
            <div class="col-md-2">
                <!-- Employer (Foreign Key) - Starts hidden if Employment Status == employed dislay -->
                <div id="employer_field" class="mb-3 d-none">
                    <label for="employer_id" class="form-label">Employer <span class="text-danger">*</span></label>
                    <select id="employer_id" name="employer_id" class="form-select form-select-sm">
                        <option value="">Loading employers...</option>
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
                <select name="sponsors[]" id="sponsors" class="form-select form-select-sm sponsor-select">
                    <option value="">Select Sponsor</option>
                    <!-- Populate dynamically -->
                </select>
                <button type="button" class="btn btn-sm btn-subtle-danger remove_sponsor_btn">Remove</button>
                <div class="invalid-feedback">Please select a sponsor.</div>
            </div>
        </div>
        </div>
        <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>
        <!-- Submit Button -->
        <button type="submit" class="btn btn-primary mt-3">Add New Learner</button>
    </form>
    <script>
        jQuery(document).ready(function ($) {
            $.ajax({
                url: "<?php echo admin_url('admin-ajax.php'); ?>",
                type: "POST",
                data: { action: "fetch_learners_dropdown_data", nonce: "<?php echo wp_create_nonce('learners_nonce'); ?>" },
                success: function (response) {
                    if (response.success) {
                        console.log(response);

                        // Populate dropdowns with the data
                        populateDropdown("#city_town_id", response.data.cities);
                        populateDropdown("#province_region_id", response.data.provinces);
                        populateDropdown("#highest_qualification", response.data.qualifications);
                        populateDropdown("#employer_id", response.data.employers);
                        populateDropdown("#sponsors", response.data.employers);
                        // Assessment Numeracy Level
                        populateDropdown("#numeracy_level", response.data.placement_levels.numeracy_levels);
                        // Assessment Communications Level
                        populateDropdown("#communication_level", response.data.placement_levels.communication_levels);
                    } else {
                        console.error("Error loading dropdown data:", response.data);
                    }
                },
                error: function () {
                    console.error("An error occurred while fetching dropdown data.");
                },
            });

                function populateDropdown(selector, items) {
                    const dropdown = $(selector);
                    dropdown.empty();
                    dropdown.append('<option value="">Select</option>');

                    if (Array.isArray(items)) {
                        items.forEach((item) => {
                            dropdown.append(`<option value="${item.id}">${item.name}</option>`);
                        });
                    } else {
                        console.error(`Expected array but got:`, items);
                    }
                }

            // Add Sponsor Input Group
            $('#add_sponsor_btn').click(function() {
                var newSponsor = $('#sponsor_template').clone().removeAttr('id').removeClass('d-none');
                newSponsor.find('.remove_sponsor_btn').click(function() {
                    $(this).closest('.sponsor-group').remove();
                });
                $('#sponsor_container').append(newSponsor);
            });

            // Note: Initials generation is now handled globally by learners-app.js

        });
    </script>
    <?php
    return ob_get_clean();
}

// Register shortcode
add_shortcode('wecoza_learners_form', __NAMESPACE__ . '\wecoza_learners_form_shortcode');
