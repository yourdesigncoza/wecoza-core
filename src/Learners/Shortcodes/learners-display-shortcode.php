<?php
/**
 * WeCoza Learners Display Shortcode
 *
 * Displays a data table of all learners with search, export, and refresh functionality.
 *
 * Shortcode: [wecoza_display_learners]
 *
 * @package WeCoza\Learners\Shortcodes
 * @since 1.0.0
 */

namespace WeCoza\Learners\Shortcodes;

if (!defined('ABSPATH')) {
    exit;
}

function wecoza_display_learners_shortcode() {
    if (!current_user_can('manage_learners')) {
        return '<p>You do not have permission to view this content.</p>';
    }

    // Enqueue necessary scripts
    wp_enqueue_script('learners-display-script', WECOZA_CORE_URL . 'assets/js/learners/learners-display-shortcode.js', array('jquery'), WECOZA_CORE_VERSION, true);

    // Localize script with necessary data
    wp_localize_script('learners-display-script', 'wecozaAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('learners_nonce'),
        'homeUrl' => home_url(),
        'viewLearnerUrl' => home_url('app/view-learner'),
        'updateLearnerUrl' => home_url('app/update-learners')
    ));

    // Start output buffering
    ob_start();
    ?>

    <!-- Alert Container -->
    <div id="alert-container" class="alert-container"></div>

    <!-- Loader -->
    <div id="learners-loading" class="d-flex justify-content-center align-items-center py-4">
        <div class="spinner-border text-primary me-3" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <span class="text-muted">Loading learners...</span>
    </div>

    <!-- Main Content Container -->
    <div id="learners-content" style="display: none;">
        <!-- Learners Table -->
        <div class="card shadow-none border my-3" data-component-card="data-component-card">
            <div class="card-header p-3 border-bottom">
                <div class="row g-3 justify-content-between align-items-center mb-3">
                    <div class="col-12 col-md">
                        <h4 class="text-body mb-0" data-anchor="data-anchor" id="learners-table-header">
                            All Learners
                            <i class="bi bi-people ms-2"></i>
                        </h4>
                    </div>
                    <div class="search-box col-auto">
                        <form class="position-relative">
                            <input class="form-control search-input search form-control-sm" type="search"
                                   placeholder="Search" aria-label="Search" id="learners-search">
                            <svg class="svg-inline--fa fa-magnifying-glass search-box-icon" aria-hidden="true"
                                 focusable="false" data-prefix="fas" data-icon="magnifying-glass" role="img"
                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" data-fa-i2svg="">
                                <path fill="currentColor" d="M416 208c0 45.9-14.9 88.3-40 122.7L502.6 457.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L330.7 376c-34.4 25.2-76.8 40-122.7 40C93.1 416 0 322.9 0 208S93.1 0 208 0S416 93.1 416 208zM208 352a144 144 0 1 0 0-288 144 144 0 1 0 0 288z"></path>
                            </svg>
                        </form>
                    </div>
                    <div class="col-auto">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="refresh-learners">
                                Refresh
                                <i class="bi bi-arrow-clockwise ms-1"></i>
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="export-learners">
                                Export
                                <i class="bi bi-download ms-1"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Summary strip -->
                <div class="col-12">
                    <div class="scrollbar">
                        <div class="row g-0 flex-nowrap" id="learners-summary">
                            <!-- Summary stats will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-4 py-2">
                <div class="table-responsive">
                    <span id="learners-search-status" class="badge badge-phoenix badge-phoenix-primary mb-2" style="display: none;"></span>
                    <table id="learners-table" class="table table-hover table-sm fs-9 mb-0 overflow-hidden">
                        <thead class="border-bottom">
                            <tr>
                                <th scope="col" class="border-0 ps-4">
                                    ID
                                    <i class="bi bi-hash ms-1"></i>
                                </th>
                                <th scope="col" class="border-0">
                                    Full Name
                                    <i class="bi bi-person ms-1"></i>
                                </th>
                                <th scope="col" class="border-0">
                                    Surname
                                    <i class="bi bi-person-badge ms-1"></i>
                                </th>
                                <th scope="col" class="border-0">
                                    Gender
                                    <i class="bi bi-gender-ambiguous ms-1"></i>
                                </th>
                                <th scope="col" class="border-0">
                                    Race
                                    <i class="bi bi-globe ms-1"></i>
                                </th>
                                <th scope="col" class="border-0">
                                    Tel Number
                                    <i class="bi bi-telephone ms-1"></i>
                                </th>
                                <th scope="col" class="border-0">
                                    Email
                                    <i class="bi bi-envelope ms-1"></i>
                                </th>
                                <th scope="col" class="border-0">
                                    City/Town
                                    <i class="bi bi-geo-alt ms-1"></i>
                                </th>
                                <th scope="col" class="border-0">
                                    Employment Status
                                    <i class="bi bi-briefcase ms-1"></i>
                                </th>
                                <th scope="col" class="border-0 pe-4">
                                    Actions
                                    <i class="bi bi-gear ms-1"></i>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="learners-table-body">
                            <!-- Table rows will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
                <div id="learners-pagination" class="d-flex justify-content-between mt-3">
                    <!-- Pagination will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <?php
    return ob_get_clean();
}

// Register shortcode - this will be called from the main plugin file
add_shortcode('wecoza_display_learners', __NAMESPACE__ . '\wecoza_display_learners_shortcode');
