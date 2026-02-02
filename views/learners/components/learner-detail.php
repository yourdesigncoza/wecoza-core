<?php 
function get_learner_data_by_id() {
        try {
            // Verify nonce
            if (!check_ajax_referer('learners_nonce', 'nonce', false)) {
                throw new Exception('Invalid security token');
            }
            $learner_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            if (!$learner_id) {
                throw new Exception('Learner ID is required');
            }

            // We are Getting the class correctly "learner_DB"
            $db = new learner_DB();
            $learner = $db->get_learner_by_id($learner_id);

            // Ensure the class file is included, for some reson it's not included 
            if (!class_exists('learner_DB')) {
                throw new Exception('Learners_DB class not found.');
            }
            
            if (!$learner) {
                throw new Exception('Learner not found');
            }

            // Generate the HTML content in PHP
            ob_start();
            
            // print_r($learner);

            // Get learners detail
            // Basic info.
            require_once WECOZA_CHILD_DIR . '/assets/learners/components/learner-header.php';
            // Tabs
            require_once WECOZA_CHILD_DIR . '/assets/learners/components/learner-tabs.php';
            // Info 
            require_once WECOZA_CHILD_DIR . '/assets/learners/components/learner-info.php';
            // Placement assesment 
            require_once WECOZA_CHILD_DIR . '/assets/learners/components/learner-assesment.php';
            // Learner Class
            require_once WECOZA_CHILD_DIR . '/assets/learners/components/learner-class-info.php';
            // POE
            require_once WECOZA_CHILD_DIR . '/assets/learners/components/learner-poe.php';

            $html = ob_get_clean();

        // Send the generated HTML in the response
        wp_send_json_success(['html' => $html]);

        // DEBUG Include the raw learner data in the response Then print console log in AJAX function
        // wp_send_json_success([
        //     'learner' => $learner // Send the database result back as part of the response
        // ]);

    } catch (Exception $e) {
        error_log('Error in get_learner: ' . $e->getMessage());
        wp_send_json_error($e->getMessage());
    }
}
