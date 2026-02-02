<?php
/**
 * Learner form view
 * 
 * @var array $data View data
 */
?>
<div class="wecoza-learner-form">
    <form id="learner-form" method="post" action="">
        <?php wp_nonce_field('wecoza_learner_form', 'wecoza_learner_nonce'); ?>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="first_name" class="form-label">First Name</label>
                <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo isset($data['learner']) ? esc_attr($data['learner']->getFirstName()) : ''; ?>" required>
            </div>
            <div class="col-md-6">
                <label for="last_name" class="form-label">Last Name</label>
                <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo isset($data['learner']) ? esc_attr($data['learner']->getLastName()) : ''; ?>" required>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($data['learner']) ? esc_attr($data['learner']->getEmail()) : ''; ?>" required>
            </div>
            <div class="col-md-6">
                <label for="phone" class="form-label">Phone</label>
                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo isset($data['learner']) ? esc_attr($data['learner']->getPhone()) : ''; ?>" required>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="id_number" class="form-label">ID Number</label>
                <input type="text" class="form-control" id="id_number" name="id_number" value="<?php echo isset($data['learner']) ? esc_attr($data['learner']->getIdNumber()) : ''; ?>" required>
            </div>
            <div class="col-md-6">
                <label for="date_of_birth" class="form-label">Date of Birth</label>
                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo isset($data['learner']) ? esc_attr($data['learner']->getDateOfBirth()) : ''; ?>" required>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="address" class="form-label">Address</label>
            <textarea class="form-control" id="address" name="address" rows="3"><?php echo isset($data['learner']) ? esc_textarea($data['learner']->getAddress()) : ''; ?></textarea>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="city" class="form-label">City</label>
                <input type="text" class="form-control" id="city" name="city" value="<?php echo isset($data['learner']) ? esc_attr($data['learner']->getCity()) : ''; ?>">
            </div>
            <div class="col-md-6">
                <label for="postal_code" class="form-label">Postal Code</label>
                <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?php echo isset($data['learner']) ? esc_attr($data['learner']->getPostalCode()) : ''; ?>">
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="province" class="form-label">Province</label>
                <input type="text" class="form-control" id="province" name="province" value="<?php echo isset($data['learner']) ? esc_attr($data['learner']->getProvince()) : ''; ?>">
            </div>
            <div class="col-md-6">
                <label for="country" class="form-label">Country</label>
                <input type="text" class="form-control" id="country" name="country" value="<?php echo isset($data['learner']) ? esc_attr($data['learner']->getCountry()) : ''; ?>" required>
            </div>
        </div>
        
        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</div>

<script>
    jQuery(document).ready(function($) {
        $('#learner-form').on('submit', function(e) {
            e.preventDefault();
            
            // Form validation
            if (!this.checkValidity()) {
                e.stopPropagation();
                $(this).addClass('was-validated');
                return;
            }
            
            // AJAX form submission
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: $(this).serialize() + '&action=wecoza_save_learner',
                success: function(response) {
                    if (response.success) {
                        alert('Learner saved successfully!');
                        // Redirect or refresh as needed
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        });
    });
</script>
