jQuery(document).ready(function($) {
    const learnerSingle = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Back to learners button
            $('.back-to-learners').on('click', this.handleBackToLearners);
            // http://localhost/wecoza/app/all-learners/
            
            // Edit learner button
            $('.edit-learner-btn').on('click', this.handleEditLearner);
            
        },

        handleBackToLearners: function() {
            window.location.href = learnerSingleAjax.displayLearnersUrl;
        },

        handleEditLearner: function() {
            const learnerId = $(this).data('id');
            window.location.href = learnerSingleAjax.updateLearnerUrl + '/?learner_id=' + learnerId;
        },


        showAlert: function(type, message) {
            const alertClass = type === 'success' ? 'alert-subtle-success' : 'alert-subtle-danger';
            const alertHtml = `
                <div class="alert ${alertClass} alert-dismissible fade show mb-3" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            // Insert alert after the header buttons
            $('.wecoza-single-learner-display > .d-flex').after(alertHtml);
            
            // Auto-dismiss after 5 seconds if not success
            if (type !== 'success') {
                setTimeout(() => {
                    $('.alert').fadeOut();
                }, 5000);
            }
        }
    };

    // Initialize
    learnerSingle.init();
});