<?php
/**
 * Agent Capture Form Template
 *
 * This template displays the agent capture/edit form.
 *
 * @package WeCoza\Core
 * @since 1.0.0
 *
 * @var array $agent Current agent data (if editing)
 * @var array $errors Form validation errors
 * @var string $mode Form mode ('add' or 'edit')
 * @var array $atts Shortcode attributes
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

use WeCoza\Agents\Helpers\FormHelpers;

?>

<div class="wecoza-agents-form-container">
<div class="wecoza-agents-feedback mt-3"></div>

<?php if ($mode === 'edit' && !empty($agent['agent_id'])) : ?>
    <div class="alert alert-subtle-primary mb-4">
        <i class="fas fa-edit me-2"></i>
        <strong><?php _e('Editing Agent:', 'wecoza-core'); ?></strong>
        <?php echo esc_html(FormHelpers::get_field_value($agent, 'first_name') . ' ' . FormHelpers::get_field_value($agent, 'surname')); ?>
        (ID: <?php echo esc_html(FormHelpers::get_field_value($agent, 'agent_id')); ?>)
    </div>
<?php endif; ?>

<form id="agents-form" class="ydcoza-compact-form needs-validation mt-6" method="POST" enctype="multipart/form-data" novalidate>
    <?php wp_nonce_field('submit_agent_form', 'wecoza_agents_form_nonce'); ?>

    <!-- Hidden Agent ID field for editing -->
    <?php if ($mode === 'edit' && !empty($agent['agent_id'])) : ?>
        <input type="hidden" name="editing_agent_id" value="<?php echo esc_attr(FormHelpers::get_field_value($agent, 'agent_id')); ?>">
    <?php endif; ?>

    <!-- Personal Information Section -->
    <div class="row">
        <h6 class="mb-2">Personal Info.</h6>
        <!-- Title -->
        <div class="col-md-2">
            <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
            <select id="title" name="title" class="form-select form-select-sm <?php echo FormHelpers::get_error_class($errors, 'title'); ?>" required>
                <option value="">Select</option>
                <option value="Mr" <?php selected(FormHelpers::get_field_value($agent, 'title'), 'Mr'); ?>>Mr</option>
                <option value="Mrs" <?php selected(FormHelpers::get_field_value($agent, 'title'), 'Mrs'); ?>>Mrs</option>
                <option value="Ms" <?php selected(FormHelpers::get_field_value($agent, 'title'), 'Ms'); ?>>Ms</option>
                <option value="Miss" <?php selected(FormHelpers::get_field_value($agent, 'title'), 'Miss'); ?>>Miss</option>
                <option value="Dr" <?php selected(FormHelpers::get_field_value($agent, 'title'), 'Dr'); ?>>Dr</option>
                <option value="Prof" <?php selected(FormHelpers::get_field_value($agent, 'title'), 'Prof'); ?>>Prof</option>
            </select>
            <?php FormHelpers::display_field_error($errors, 'title'); ?>
        </div>

        <!-- First Name -->
        <div class="col-md-3">
            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
            <input type="text" id="first_name" name="first_name" class="form-control form-control-sm <?php echo FormHelpers::get_error_class($errors, 'first_name'); ?>"
                   value="<?php echo FormHelpers::get_field_value($agent, 'first_name'); ?>" required>
            <div class="invalid-feedback">Please provide the first name.</div>
            <?php FormHelpers::display_field_error($errors, 'first_name'); ?>
        </div>

        <!-- Second Name -->
        <div class="col-md-3">
            <label for="second_name" class="form-label">Second Name</label>
            <input type="text" id="second_name" name="second_name" class="form-control form-control-sm <?php echo FormHelpers::get_error_class($errors, 'second_name'); ?>"
                   value="<?php echo FormHelpers::get_field_value($agent, 'second_name'); ?>">
            <?php FormHelpers::display_field_error($errors, 'second_name'); ?>
        </div>

        <!-- Surname -->
        <div class="col-md-3">
            <label for="surname" class="form-label">Surname <span class="text-danger">*</span></label>
            <input type="text" id="surname" name="surname" class="form-control form-control-sm <?php echo FormHelpers::get_error_class($errors, 'surname'); ?>"
                   value="<?php echo FormHelpers::get_field_value($agent, 'surname'); ?>" required>
            <div class="invalid-feedback">Please provide the surname.</div>
            <?php FormHelpers::display_field_error($errors, 'surname'); ?>
        </div>
    </div>
    <div class="row mt-3">
        <!-- Initials -->
        <div class="col-md-2">
            <label for="initiales" class="form-label">Initials <small>(Auto)</small></label>
            <input type="text" id="initials" name="initials" class="form-control form-control-sm"
                   value="<?php echo FormHelpers::get_field_value($agent, 'initials'); ?>" readonly>
        </div>

        <!-- Gender -->
        <div class="col-md-3">
            <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
            <select id="gender" name="gender" class="form-select form-select-sm <?php echo FormHelpers::get_error_class($errors, 'gender'); ?>" required>
                <option value="">Select</option>
                <option value="M" <?php selected(FormHelpers::get_field_value($agent, 'gender'), 'M'); ?>>Male</option>
                <option value="F" <?php selected(FormHelpers::get_field_value($agent, 'gender'), 'F'); ?>>Female</option>
            </select>
            <div class="invalid-feedback">Please select your gender.</div>
            <?php FormHelpers::display_field_error($errors, 'gender'); ?>
        </div>
        <!-- Race -->
        <div class="col-md-3">
            <label for="race" class="form-label">Race <span class="text-danger">*</span></label>
            <select id="race" name="race" class="form-select form-select-sm <?php echo FormHelpers::get_error_class($errors, 'race'); ?>" required>
                <option value="">Select</option>
                <option value="African" <?php selected(FormHelpers::get_field_value($agent, 'race'), 'African'); ?>>African</option>
                <option value="Coloured" <?php selected(FormHelpers::get_field_value($agent, 'race'), 'Coloured'); ?>>Coloured</option>
                <option value="White" <?php selected(FormHelpers::get_field_value($agent, 'race'), 'White'); ?>>White</option>
                <option value="Indian" <?php selected(FormHelpers::get_field_value($agent, 'race'), 'Indian'); ?>>Indian</option>
            </select>
            <div class="invalid-feedback">Please select your race.</div>
            <?php FormHelpers::display_field_error($errors, 'race'); ?>
        </div>
    </div>

    <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>

    <!-- Identification Section -->
    <div class="row">
        <h6 class="mb-2">ID & Contact</h6>
        <div class="col-md-2">
            <!-- Radio buttons for ID or Passport selection -->
            <div class="mb-1">
                <label class="form-label">Identification Type <span class="text-danger">*</span></label>
                <div class="row">
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="id_type" id="sa_id_option" value="sa_id"
                                   <?php checked(FormHelpers::get_field_value($agent, 'id_type', 'sa_id'), 'sa_id'); ?> required>
                            <label class="form-check-label" for="sa_id_option">SA ID</label>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="id_type" id="passport_option" value="passport"
                                   <?php checked(FormHelpers::get_field_value($agent, 'id_type'), 'passport'); ?> required>
                            <label class="form-check-label" for="passport_option">Passport</label>
                        </div>
                    </div>
                </div>
                <div class="invalid-feedback">Please select an identification type.</div>
            </div>
        </div>

        <div class="col-md-2">
            <!-- SA ID Number -->
            <div id="sa_id_field" class="mb-3 <?php echo FormHelpers::get_field_value($agent, 'id_type', 'sa_id') !== 'sa_id' ? 'd-none' : ''; ?>">
                <label for="sa_id_no" class="form-label">SA ID Number <span class="text-danger">*</span></label>
                <input type="text" id="sa_id_no" name="sa_id_no"
                       class="form-control form-control-sm <?php echo FormHelpers::get_error_class($errors, 'sa_id_no'); ?>"
                       value="<?php echo FormHelpers::get_field_value($agent, 'sa_id_no'); ?>" maxlength="13"
                       pattern="[0-9]{13}" title="SA ID must be exactly 13 digits">
                <div class="invalid-feedback">Please provide a valid SA ID number.</div>
                <div class="valid-feedback">Valid SA ID number!</div>
                <?php FormHelpers::display_field_error($errors, 'sa_id_no'); ?>
            </div>

            <!-- Passport Number -->
            <div id="passport_field" class="mb-3 <?php echo FormHelpers::get_field_value($agent, 'id_type') !== 'passport' ? 'd-none' : ''; ?>">
                <label for="passport_number" class="form-label">Passport Number <span class="text-danger">*</span></label>
                <input type="text" id="passport_number" name="passport_number"
                       class="form-control form-control-sm <?php echo FormHelpers::get_error_class($errors, 'passport_number'); ?>"
                       value="<?php echo FormHelpers::get_field_value($agent, 'passport_number'); ?>" maxlength="12">
                <div class="invalid-feedback">Please provide a valid passport number.</div>
                <?php FormHelpers::display_field_error($errors, 'passport_number'); ?>
            </div>
        </div>

        <!-- Telephone Number -->
        <div class="col-md-2">
            <label for="tel_number" class="form-label">Telephone Number <span class="text-danger">*</span></label>
            <input type="text" id="tel_number" name="tel_number"
                   class="form-control form-control-sm <?php echo FormHelpers::get_error_class($errors, 'tel_number'); ?>"
                   value="<?php echo FormHelpers::get_field_value($agent, 'tel_number'); ?>" required>
            <div class="invalid-feedback">Please provide a telephone number.</div>
            <?php FormHelpers::display_field_error($errors, 'tel_number'); ?>
        </div>

        <!-- Email -->
        <div class="col-md-2">
            <label for="email_address" class="form-label">Email <span class="text-danger">*</span></label>
            <input type="email" id="email_address" name="email_address"
                   class="form-control form-control-sm <?php echo FormHelpers::get_error_class($errors, 'email_address'); ?>"
                   value="<?php echo FormHelpers::get_field_value($agent, 'email_address'); ?>" required>
            <div class="invalid-feedback">Please provide a valid email address.</div>
            <?php FormHelpers::display_field_error($errors, 'email_address'); ?>
        </div>
    </div>

    <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>

    <!-- SACE Registration Section -->
    <div class="row">
        <h6 class="mb-2">Sace Details</h6>
        <div class="col-md-2">
            <label for="sace_number" class="form-label">SACE Registration Number</label>
            <input type="text" id="sace_number" name="sace_number" class="form-control form-control-sm"
                   value="<?php echo FormHelpers::get_field_value($agent, 'sace_number'); ?>">
        </div>
        <div class="col-md-2">
            <label for="sace_registration_date" class="form-label">SACE Registration Date</label>
            <input type="date" id="sace_registration_date" name="sace_registration_date" class="form-control form-control-sm"
                   value="<?php echo FormHelpers::get_field_value($agent, 'sace_registration_date'); ?>">
        </div>

        <div class="col-md-2">
            <label for="sace_expiry_date" class="form-label">SACE Expiry Date</label>
            <input type="date" id="sace_expiry_date" name="sace_expiry_date" class="form-control form-control-sm"
                   value="<?php echo FormHelpers::get_field_value($agent, 'sace_expiry_date'); ?>">
        </div>
    </div>

    <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>

    <!-- Address Section -->
    <div class="row">
        <h6 class="mb-2">Address</h6>
        <div class="col-md-3">
            <label for="google_address_search" class="form-label">Search Address</label>
            <div id="google_address_container">
                <input type="text" id="google_address_search" class="form-control form-control-sm"
                       placeholder="Start typing an address...">
            </div>
            <small class="text-muted">Use Google Places to search for an address</small>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-3">
            <label for="address_line_1" class="form-label">Street Address <span class="text-danger">*</span></label>
            <input type="text" id="address_line_1" name="address_line_1"
                   class="form-control form-control-sm <?php echo FormHelpers::get_error_class($errors, 'address_line_1'); ?>"
                   value="<?php echo FormHelpers::get_field_value($agent, 'address_line_1'); ?>" required>
            <div class="invalid-feedback">Please provide a street address.</div>
            <?php FormHelpers::display_field_error($errors, 'address_line_1'); ?>
        </div>

        <div class="col-md-3">
            <label for="address_line_2" class="form-label">Unit/Complex</label>
            <input type="text" id="address_line_2" name="address_line_2" class="form-control form-control-sm"
                   value="<?php echo FormHelpers::get_field_value($agent, 'address_line_2'); ?>">
        </div>
        <div class="col-md-3">
            <label for="residential_suburb" class="form-label">Suburb <span class="text-danger">*</span></label>
            <input type="text" id="residential_suburb" name="residential_suburb"
                   class="form-control form-control-sm <?php echo FormHelpers::get_error_class($errors, 'residential_suburb'); ?>"
                   value="<?php echo FormHelpers::get_field_value($agent, 'residential_suburb'); ?>" required>
            <div class="invalid-feedback">Please provide a suburb.</div>
            <?php FormHelpers::display_field_error($errors, 'residential_suburb'); ?>
        </div>
    </div>

    <div class="row mt-3">

        <div class="col-md-3">
            <label for="city_town" class="form-label">City/Town <span class="text-danger">*</span></label>
            <input type="text" id="city_town" name="city_town"
                   class="form-control form-control-sm <?php echo FormHelpers::get_error_class($errors, 'city_town'); ?>"
                   value="<?php echo FormHelpers::get_field_value($agent, 'city'); ?>" required>
            <div class="invalid-feedback">Please provide a city or town.</div>
            <?php FormHelpers::display_field_error($errors, 'city_town'); ?>
        </div>

        <div class="col-md-3">
            <label for="province_region" class="form-label">Province/Region <span class="text-danger">*</span></label>
            <select id="province_region" name="province_region"
                    class="form-select form-select-sm <?php echo FormHelpers::get_error_class($errors, 'province_region'); ?>" required>
                <option value="">Select</option>
                <option value="Eastern Cape" <?php selected(FormHelpers::get_field_value($agent, 'province'), 'Eastern Cape'); ?>>Eastern Cape</option>
                <option value="Free State" <?php selected(FormHelpers::get_field_value($agent, 'province'), 'Free State'); ?>>Free State</option>
                <option value="Gauteng" <?php selected(FormHelpers::get_field_value($agent, 'province'), 'Gauteng'); ?>>Gauteng</option>
                <option value="KwaZulu-Natal" <?php selected(FormHelpers::get_field_value($agent, 'province'), 'KwaZulu-Natal'); ?>>KwaZulu-Natal</option>
                <option value="Limpopo" <?php selected(FormHelpers::get_field_value($agent, 'province'), 'Limpopo'); ?>>Limpopo</option>
                <option value="Mpumalanga" <?php selected(FormHelpers::get_field_value($agent, 'province'), 'Mpumalanga'); ?>>Mpumalanga</option>
                <option value="Northern Cape" <?php selected(FormHelpers::get_field_value($agent, 'province'), 'Northern Cape'); ?>>Northern Cape</option>
                <option value="North West" <?php selected(FormHelpers::get_field_value($agent, 'province'), 'North West'); ?>>North West</option>
                <option value="Western Cape" <?php selected(FormHelpers::get_field_value($agent, 'province'), 'Western Cape'); ?>>Western Cape</option>
            </select>
            <div class="invalid-feedback">Please select a province or region.</div>
            <?php FormHelpers::display_field_error($errors, 'province_region'); ?>
        </div>

        <div class="col-md-3">
            <label for="postal_code" class="form-label">Postal Code <span class="text-danger">*</span></label>
            <input type="text" id="postal_code" name="postal_code"
                   class="form-control form-control-sm <?php echo FormHelpers::get_error_class($errors, 'postal_code'); ?>"
                   value="<?php echo FormHelpers::get_field_value($agent, 'postal_code'); ?>" required>
            <div class="invalid-feedback">Please provide a postal code.</div>
            <?php FormHelpers::display_field_error($errors, 'postal_code'); ?>
        </div>
    </div>


    <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>

    <!-- Preferred Working Areas Section -->
    <div class="row">
        <h6 class="mb-2">Preferred Working Area's</h6>
        <?php
        for ($i = 1; $i <= 3; $i++) :
            $field_name = "preferred_working_area_$i";
            $selected_value = FormHelpers::get_field_value($agent, $field_name);
        ?>
        <div class="col-md-3">
            <label for="<?php echo $field_name; ?>" class="form-label">
                Preferred Working Area <?php echo $i; ?> <?php if ($i === 1) : ?><span class="text-danger">*</span><?php endif; ?>
            </label>
            <select id="<?php echo $field_name; ?>" name="<?php echo $field_name; ?>"
                    class="form-select form-select-sm <?php echo FormHelpers::get_error_class($errors, $field_name); ?>"
                    <?php echo $i === 1 ? 'required' : ''; ?>>
                <option value="">Select</option>
                <?php foreach ($working_areas as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($selected_value, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($i === 1) : ?>
            <div class="invalid-feedback">Please select a preferred working area.</div>
            <?php endif; ?>
            <?php FormHelpers::display_field_error($errors, $field_name); ?>
        </div>
        <?php endfor; ?>
    </div>

    <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>

    <!-- Phase Registered Section -->
    <div class="row">
        <h6 class="mb-2">Phase & Subjects Info.</h6>
        <div class="col-md-2">
            <label for="phase_registered" class="form-label">Phase Registered</label>
            <select id="phase_registered" name="phase_registered" class="form-select form-select-sm">
                <option value="">Select</option>
                <option value="Foundation" <?php selected(FormHelpers::get_field_value($agent, 'phase_registered'), 'Foundation'); ?>>Foundation Phase</option>
                <option value="Intermediate" <?php selected(FormHelpers::get_field_value($agent, 'phase_registered'), 'Intermediate'); ?>>Intermediate Phase</option>
                <option value="Senior" <?php selected(FormHelpers::get_field_value($agent, 'phase_registered'), 'Senior'); ?>>Senior Phase</option>
                <option value="FET" <?php selected(FormHelpers::get_field_value($agent, 'phase_registered'), 'FET'); ?>>FET Phase</option>
            </select>
        </div>

        <div class="col-md-3">
            <label for="subjects_registered" class="form-label">Subjects Registered <span class="text-danger">*</span></label>
            <input type="text" id="subjects_registered" name="subjects_registered" class="form-control form-control-sm"
                   value="<?php echo FormHelpers::get_field_value($agent, 'subjects_registered'); ?>"
                   placeholder="e.g., Mathematics, Science, English" required>
        </div>
        <div class="col-md-2">
            <label for="highest_qualification" class="form-label">Highest Qualification <span class="text-danger">*</span></label>
            <input type="text" id="highest_qualification" name="highest_qualification" class="form-control form-control-sm"
                   value="<?php echo FormHelpers::get_field_value($agent, 'highest_qualification'); ?>"
                   placeholder="e.g., Bachelor of Education" required>
        </div>

        <div class="col-md-2">
            <label for="agent_training_date" class="form-label">Agent Training Date <span class="text-danger">*</span></label>
            <input type="date" id="agent_training_date" name="agent_training_date" class="form-control form-control-sm"
                   value="<?php echo FormHelpers::get_field_value($agent, 'agent_training_date'); ?>" required>
        </div>
    </div>

    <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>

    <!-- Quantum Tests Section -->
    <div class="row">
        <h6 class="mb-2">Quatntum Assesments</h6>
        <div class="col-md-2">
            <label for="quantum_assessment" class="form-label">Quantum Assessment % <span class="text-danger">*</span></label>
            <input type="number" id="quantum_assessment" name="quantum_assessment" class="form-control form-control-sm"
                   value="<?php echo FormHelpers::get_field_value($agent, 'quantum_assessment'); ?>"
                   min="0" max="100" step="1" required>
        </div>
        <div class="col-md-2">
            <label for="quantum_maths_score" class="form-label">Quantum Maths Score % <span class="text-danger">*</span></label>
            <input type="number" id="quantum_maths_score" name="quantum_maths_score" class="form-control form-control-sm"
                   value="<?php echo FormHelpers::get_field_value($agent, 'quantum_maths_score'); ?>"
                   min="0" max="100" step="1" required>
        </div>

        <div class="col-md-2">
            <label for="quantum_science_score" class="form-label">Quantum Science Score % <span class="text-danger">*</span></label>
            <input type="number" id="quantum_science_score" name="quantum_science_score" class="form-control form-control-sm"
                   value="<?php echo FormHelpers::get_field_value($agent, 'quantum_science_score'); ?>"
                   min="0" max="100" step="1" required>
        </div>
    </div>

    <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>

    <!-- Criminal Record Check Section -->
    <div class="row">
        <h6 class="mb-2">Legal</h6>
        <div class="col-md-2">
            <label for="criminal_record_date" class="form-label">Criminal Record Check Date</label>
            <input type="date" id="criminal_record_date" name="criminal_record_date" class="form-control form-control-sm"
                   value="<?php echo FormHelpers::get_field_value($agent, 'criminal_record_date'); ?>">
        </div>

        <div class="col-md-4">
            <label for="criminal_record_file" class="form-label">Upload Criminal Record</label>
            <input type="file" id="criminal_record_file" name="criminal_record_file" class="form-control form-control-sm"
                   accept=".pdf,.doc,.docx">
            <?php
            $criminal_file = FormHelpers::get_field_value($agent, 'criminal_record_file');
            if (!empty($criminal_file)) :
            ?>
            <div class="mt-1">
                <a href="<?php echo esc_url(wp_upload_dir()['baseurl'] . $criminal_file); ?>" target="_blank" class="text-decoration-none">
                    <span class="badge badge-phoenix fs-10 badge-phoenix-primary">
                        <span class="badge-label">View Upload</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16px" height="16px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-download ms-1" style="height:12.8px;width:12.8px;">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7,10 12,15 17,10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                    </span>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>

    <!-- Agreement Section -->
    <div class="row">
        <h6 class="mb-2">Agreement</h6>
        <div class="col-md-2">
            <label for="signed_agreement_date" class="form-label">Agreement Signed Date <span class="text-danger">*</span></label>
            <input type="date" id="signed_agreement_date" name="signed_agreement_date" class="form-control form-control-sm"
                   value="<?php echo FormHelpers::get_field_value($agent, 'signed_agreement_date'); ?>"
                   data-original-value="<?php echo esc_attr(FormHelpers::get_field_value($agent, 'signed_agreement_date')); ?>"
                   autocomplete="off" required>
            <?php if ($mode === 'edit' && !empty(FormHelpers::get_field_value($agent, 'signed_agreement_date'))) : ?>
            <script>
                // Simplified defensive script to protect the signed_agreement_date value
                (function() {
                    var dateField = document.getElementById('signed_agreement_date');
                    if (!dateField) return;

                    var originalValue = dateField.getAttribute('data-original-value');
                    if (!originalValue) {
                        console.log('[WeCoza] No original value found for signed_agreement_date');
                        return;
                    }

                    console.log('[WeCoza] Protecting signed_agreement_date with value:', originalValue);

                    // Function to restore value
                    var restoreValue = function() {
                        if (!dateField.value || dateField.value === '') {
                            dateField.value = originalValue;
                            console.log('[WeCoza] Restored signed_agreement_date:', originalValue);
                        }
                    };

                    // Restore immediately
                    restoreValue();

                    // Also restore on focus/blur events
                    dateField.addEventListener('focus', function() {
                        if (!this.value) {
                            this.value = originalValue;
                        }
                    });

                    dateField.addEventListener('blur', function() {
                        if (!this.value) {
                            this.value = originalValue;
                        }
                    });

                    // Keep checking and restoring for 2 seconds
                    var checkCount = 0;
                    var interval = setInterval(function() {
                        checkCount++;
                        if (!dateField.value || dateField.value === '') {
                            dateField.value = originalValue;
                            console.log('[WeCoza] Restored value (check #' + checkCount + ')');
                        }
                    }, 50);

                    // Stop after 2 seconds
                    setTimeout(function() {
                        clearInterval(interval);
                        console.log('[WeCoza] Stopped monitoring after ' + checkCount + ' checks');
                        // Final restore
                        restoreValue();
                    }, 2000);
                })();
            </script>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <?php
            $agreement_file = FormHelpers::get_field_value($agent, 'signed_agreement_file');
            $is_edit_mode_with_file = ($mode === 'edit' && !empty($agreement_file));
            ?>
            <label for="signed_agreement_file" class="form-label">
                Upload Signed Agreement
                <?php if (!$is_edit_mode_with_file) : ?>
                <span class="text-danger">*</span>
                <?php endif; ?>
            </label>
            <input type="file" id="signed_agreement_file" name="signed_agreement_file" class="form-control form-control-sm"
                   accept=".pdf,.doc,.docx" <?php echo $is_edit_mode_with_file ? '' : 'required'; ?>>
            <?php if (!empty($agreement_file)) : ?>
            <div class="mt-1">
                <a href="<?php echo esc_url(wp_upload_dir()['baseurl'] . $agreement_file); ?>" target="_blank" class="text-decoration-none">
                    <span class="badge badge-phoenix fs-10 badge-phoenix-primary">
                        <span class="badge-label">View Upload</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16px" height="16px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-download ms-1" style="height:12.8px;width:12.8px;">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7,10 12,15 17,10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                    </span>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>

    <!-- Banking Details Section -->
    <h6 class="mb-2">Banking Details</h6>
    <div class="row">
        <div class="col-md-2">
            <label for="bank_name" class="form-label">Bank Name <span class="text-danger">*</span></label>
            <input type="text" id="bank_name" name="bank_name" class="form-control form-control-sm"
                   value="<?php echo FormHelpers::get_field_value($agent, 'bank_name'); ?>" required>
        </div>

        <div class="col-md-2">
            <label for="account_holder" class="form-label">Account Holder Name <span class="text-danger">*</span></label>
            <input type="text" id="account_holder" name="account_holder" class="form-control form-control-sm"
                   value="<?php echo FormHelpers::get_field_value($agent, 'account_holder'); ?>" required>
        </div>

        <div class="col-md-2">
            <label for="account_number" class="form-label">Account Number <span class="text-danger">*</span></label>
            <input type="text" id="account_number" name="account_number" class="form-control form-control-sm"
                   value="<?php echo FormHelpers::get_field_value($agent, 'account_number'); ?>" required>
        </div>

        <div class="col-md-2">
            <label for="branch_code" class="form-label">Branch Code <span class="text-danger">*</span></label>
            <input type="text" id="branch_code" name="branch_code" class="form-control form-control-sm"
                   value="<?php echo FormHelpers::get_field_value($agent, 'branch_code'); ?>" required>
        </div>
        <div class="col-md-2">
            <label for="account_type" class="form-label">Account Type <span class="text-danger">*</span></label>
            <select id="account_type" name="account_type" class="form-select form-select-sm" required>
                <option value="">Select</option>
                <option value="Savings" <?php selected(FormHelpers::get_field_value($agent, 'account_type'), 'Savings'); ?>>Savings</option>
                <option value="Current" <?php selected(FormHelpers::get_field_value($agent, 'account_type'), 'Current'); ?>>Current/Cheque</option>
                <option value="Transmission" <?php selected(FormHelpers::get_field_value($agent, 'account_type'), 'Transmission'); ?>>Transmission</option>
            </select>
        </div>
    </div>

    <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>

    <!-- Submit Button -->
    <div class="row">
        <div class="col-md-12">
            <button type="submit" class="btn btn-subtle-primary mt-3">
                <?php echo $mode === 'edit' ? 'Update Agent' : 'Add New Agent'; ?>
            </button>
            <?php if (!empty($atts['redirect_after_save'])) : ?>
            <a href="<?php echo esc_url($atts['redirect_after_save']); ?>" class="btn btn-secondary mt-3">Cancel</a>
            <?php endif; ?>
        </div>
    </div>
</form>
</div><!-- /.wecoza-agents-form-container -->
