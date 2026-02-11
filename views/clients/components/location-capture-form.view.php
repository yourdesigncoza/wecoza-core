<?php

use WeCoza\Clients\Helpers\ViewHelpers;

$errors = $errors ?? array();
$success = $success ?? false;
$location = $location ?? array();
$provinces = $provinces ?? array();
$google_maps_enabled = $google_maps_enabled ?? false;

$provinceOptions = array();
foreach ($provinces as $province) {
    $provinceOptions[$province] = $province;
}

?>

<div class="wecoza-clients-form-container">
    <h4 class="mb-3">Capture a Location</h4>
    <p class="mb-4 text-muted">Use the form below to add new locations for suburbs and towns across South Africa.</p>

    <?php if ($success) : ?>
        <?php echo ViewHelpers::renderAlert(__('Location saved successfully.', 'wecoza-clients'), 'success'); ?>
    <?php endif; ?>

    <?php if (!empty($errors)) : ?>
        <?php if (isset($errors['general'])) : ?>
            <?php echo ViewHelpers::renderAlert($errors['general'], 'error'); ?>
        <?php else : ?>
            <?php echo ViewHelpers::renderAlert(__('Please correct the errors below.', 'wecoza-clients'), 'error'); ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!$google_maps_enabled) : ?>
        <?php echo ViewHelpers::renderAlert(__('Google Maps autocomplete is not configured. You can still complete all fields manually.', 'wecoza-clients'), 'warning'); ?>
    <?php endif; ?>

    <form method="POST" class="needs-validation ydcoza-compact-form" novalidate>
        <?php wp_nonce_field('submit_locations_form', 'wecoza_locations_form_nonce'); ?>
        <?php if (!empty($location['location_id'])) : ?>
            <input type="hidden" name="location_id" value="<?php echo (int) $location['location_id']; ?>">
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-12">
                <label for="wecoza_clients_google_address_search" class="form-label">Search Address</label>
                <div id="wecoza_clients_google_address_container" class="position-relative">
                    <input type="text" id="wecoza_clients_google_address_search" class="form-control form-control-sm" placeholder="Start typing an address...">
                    <div id="address_search_loading" class="position-absolute end-0 top-50 translate-middle-y me-2 d-none">
                        <div class="spinner-border spinner-border-sm" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div id="address_search_status" class="position-absolute end-0 top-50 translate-middle-y me-2 d-none">
                        <i class="fas fa-check-circle text-success"></i>
                    </div>
                </div>
                <small class="text-muted d-block mt-2">Use the address search to auto-fill suburb, town, province, and coordinates.</small>
            </div>
        </div>

        <div class="row g-3">
            <?php
            echo ViewHelpers::renderField('text', 'street_address', __('Street Address', 'wecoza-clients'), $location['street_address'] ?? '', array(
                'required' => true,
                'col_class' => 'col-md-4',
                'error' => $errors['street_address'] ?? '',
                'placeholder' => 'e.g. 123 Main Street',
            ));

            echo ViewHelpers::renderField('text', 'suburb', __('Suburb', 'wecoza-clients'), $location['suburb'] ?? '', array(
                'required' => true,
                'col_class' => 'col-md-4',
                'error' => $errors['suburb'] ?? '',
            ));

            echo ViewHelpers::renderField('text', 'town', __('Town / City', 'wecoza-clients'), $location['town'] ?? '', array(
                'required' => true,
                'col_class' => 'col-md-4',
                'error' => $errors['town'] ?? '',
            ));
            ?>
        </div>

        <div class="row g-3 mt-1">
            <?php
            echo ViewHelpers::renderField('select', 'province', __('Province', 'wecoza-clients'), $location['province'] ?? '', array(
                'required' => true,
                'col_class' => 'col-md-3',
                'options' => $provinceOptions,
                'error' => $errors['province'] ?? '',
            ));

            echo ViewHelpers::renderField('text', 'postal_code', __('Postal Code', 'wecoza-clients'), $location['postal_code'] ?? '', array(
                'required' => true,
                'col_class' => 'col-md-3',
                'error' => $errors['postal_code'] ?? '',
            ));

            echo ViewHelpers::renderField('text', 'latitude', __('Latitude', 'wecoza-clients'), $location['latitude'] ?? '', array(
                'required' => true,
                'col_class' => 'col-md-3',
                'error' => $errors['latitude'] ?? '',
                'help_text' => __('e.g. -26.2041', 'wecoza-clients'),
                'pattern' => '^-?\\d+\\.?\\d*$',
                'title' => __('Must be a valid decimal coordinate', 'wecoza-clients'),
                'min' => '-90',
                'max' => '90',
            ));

            echo ViewHelpers::renderField('text', 'longitude', __('Longitude', 'wecoza-clients'), $location['longitude'] ?? '', array(
                'required' => true,
                'col_class' => 'col-md-3',
                'error' => $errors['longitude'] ?? '',
                'help_text' => __('e.g. 28.0473', 'wecoza-clients'),
                'pattern' => '^-?\\d+\\.?\\d*$',
                'title' => __('Must be a valid decimal coordinate', 'wecoza-clients'),
                'min' => '-180',
                'max' => '180',
            ));
            ?>
        </div>        
        <div id="duplicate_check_results" class="mt-3 d-none">
            <!-- Duplicate check results will be displayed here -->
        </div>

        <div class="mt-4 d-flex align-items-center">
            <button type="button" id="check_duplicate_btn" class="btn btn-subtle-info btn-sm me-2">
                <i class="fas fa-search me-1"></i> Check Duplicates
            </button>
            <div>
                <button type="reset" id="reset_location_form" class="btn btn-subtle-warning btn-sm me-2">Reset Form</button>
                <button type="submit" id="submit_location_btn" class="btn btn-subtle-primary d-none">Save Location</button>
            </div>
        </div>

    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.querySelector('.wecoza-clients-form-container form');
    if (!form) {
        return;
    }

    // Enhanced form validation
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
            
            // Scroll to first error
            var firstError = form.querySelector('.is-invalid');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
        }

        form.classList.add('was-validated');
    }, false);

    // Coordinate validation
    function validateCoordinate(value, type) {
        var num = parseFloat(value);
        if (isNaN(num)) return false;
        
        if (type === 'lat') {
            return num >= -90 && num <= 90;
        } else {
            return num >= -180 && num <= 180;
        }
    }

    // Real-time coordinate validation
    var latField = document.getElementById('latitude');
    var lonField = document.getElementById('longitude');
    
    if (latField) {
        latField.addEventListener('input', function() {
            if (this.value && !validateCoordinate(this.value, 'lat')) {
                this.setCustomValidity('Latitude must be between -90 and 90');
            } else {
                this.setCustomValidity('');
            }
        });
    }
    
    if (lonField) {
        lonField.addEventListener('input', function() {
            if (this.value && !validateCoordinate(this.value, 'lon')) {
                this.setCustomValidity('Longitude must be between -180 and 180');
            } else {
                this.setCustomValidity('');
            }
        });
    }

    // Duplicate check functionality
    var checkDuplicateBtn = document.getElementById('check_duplicate_btn');
    var duplicateResults = document.getElementById('duplicate_check_results');
    var submitBtn = document.getElementById('submit_location_btn');
    var resetBtn = document.getElementById('reset_location_form');
    
    if (checkDuplicateBtn) {
        checkDuplicateBtn.addEventListener('click', function() {
            var streetAddress = document.getElementById('street_address').value.trim();
            var suburb = document.getElementById('suburb').value.trim();
            var town = document.getElementById('town').value.trim();
            
            if (!streetAddress && !suburb && !town) {
                showDuplicateAlert('Please enter a street address, suburb, or town to check for duplicates.', 'warning');
                hideSubmit();
                return;
            }
            
            // Show loading state
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Checking...';
            
            // AJAX call to check duplicates
            var formData = new FormData();
            formData.append('action', 'check_location_duplicates');
            formData.append('nonce', document.querySelector('#wecoza_locations_form_nonce').value);
            formData.append('street_address', streetAddress);
            formData.append('suburb', suburb);
            formData.append('town', town);
            
            fetch(wecoza_ajax.ajax_url, {
                method: 'POST',
                body: formData
            }).then(function(response) {
                return response.json().then(function(json) {
                    return {
                        ok: response.ok,
                        payload: json
                    };
                });
            }).then(function(result) {
                var payload = result.payload || {};

                if (!result.ok || !payload.success) {
                    var message = payload.data && payload.data.message ? payload.data.message : 'Error checking duplicates.';
                    showDuplicateAlert(message, 'danger');
                    hideSubmit();
                    return;
                }

                var duplicates = Array.isArray(payload.data && payload.data.duplicates) ? payload.data.duplicates : [];

                if (duplicates.length > 0) {
                    showDuplicateResults(duplicates);
                } else {
                    showDuplicateAlert('No duplicate locations found.', 'success');
                }

                showSubmit();
            }).catch(function(error) {
                showDuplicateAlert('Error checking duplicates: ' + error.message, 'danger');
                hideSubmit();
            }).finally(function() {
                // Reset button state
                checkDuplicateBtn.disabled = false;
                checkDuplicateBtn.innerHTML = '<i class="fas fa-search me-1"></i> Check Duplicates';
            });
        });
    }
    
    function hideSubmit() {
        if (submitBtn && !submitBtn.classList.contains('d-none')) {
            submitBtn.classList.add('d-none');
        }
    }

    function showSubmit() {
        if (submitBtn && submitBtn.classList.contains('d-none')) {
            submitBtn.classList.remove('d-none');
        }
    }

    function handleInputChange() {
        hideSubmit();
        clearDuplicateResults();
    }

    ['street_address', 'suburb', 'town', 'province', 'postal_code', 'latitude', 'longitude'].forEach(function(id) {
        var field = document.getElementById(id);
        if (field) {
            field.addEventListener('input', handleInputChange);
            field.addEventListener('change', handleInputChange);
        }
    });

    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            hideSubmit();
            clearDuplicateResults();
        });
    }

    function clearDuplicateResults() {
        if (!duplicateResults) {
            return;
        }

        duplicateResults.classList.add('d-none');
        duplicateResults.innerHTML = '';
        duplicateResults.removeAttribute('data-duplicate-state');
    }

    function showDuplicateResults(duplicates) {
        var html = '<div class="alert alert-subtle-warning"><strong>Possible duplicates found:</strong><ul class="mb-0 mt-2">';
        duplicates.forEach(function(loc) {
            var locationText = '';
            if (loc.street_address) {
                locationText = loc.street_address + ', ';
            }
            locationText += loc.suburb + ', ' + loc.town + ' (' + loc.province + ')';
            html += '<li>' + locationText + '</li>';
        });
        html += '</ul></div>';
        duplicateResults.innerHTML = html;
        duplicateResults.classList.remove('d-none');
        duplicateResults.setAttribute('data-duplicate-state', 'results');
    }
    
    function showDuplicateAlert(message, type) {
        var html = '<div class="alert alert-subtle-' + type + '">' + message + '</div>';
        duplicateResults.innerHTML = html;
        duplicateResults.classList.remove('d-none');
        duplicateResults.setAttribute('data-duplicate-state', type);
    }
});
</script>
