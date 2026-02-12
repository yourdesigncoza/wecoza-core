<?php
/**
 * Agent Single Display Template - Modern Layout
 *
 * This template displays a single agent's complete information on a dedicated page
 * using a modern, clean design inspired by the Phoenix design system.
 *
 * Available variables:
 * - $agent_id (int): The ID of the agent being displayed
 * - $agent (array|false): Agent data array or false if not found
 * - $error (string|false): Error message if any
 * - $loading (bool): Whether to show loading state
 * - $back_url (string): URL to return to agents list
 * - $can_manage (bool): Whether current user can manage agents
 * - $date_format (string): WordPress date format setting
 *
 * @package WeCoza\Core
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wecoza-single-agent-display">

    <?php // Loading state ?>
    <?php if ($loading) : ?>
        <div class="agent-loading-state text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden"><?php esc_html_e('Loading...', 'wecoza-core'); ?></span>
            </div>
            <p class="mt-3 text-muted"><?php esc_html_e('Loading agent details...', 'wecoza-core'); ?></p>
        </div>
    <?php endif; ?>

    <?php // Error state ?>
    <?php if ($error) : ?>
        <div class="agent-error-state">
            <div class="alert alert-danger d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                <div>
                    <h6 class="alert-heading mb-1"><?php esc_html_e('Error Loading Agent', 'wecoza-core'); ?></h6>
                    <p class="mb-0"><?php echo esc_html($error); ?></p>
                </div>
            </div>
            <div class="mt-3">
                <a href="<?php echo esc_url($back_url); ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>
                    <?php esc_html_e('Back to Agents', 'wecoza-core'); ?>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($agent && !$error && !$loading) : ?>
        <?php // Action Buttons ?>
        <div class="d-flex justify-content-end mb-4">
            <div class="btn-group mt-2 me-2" role="group" aria-label="...">
                <button class="btn btn-subtle-primary" type="button">Back To Agents</button>
                <button class="btn btn-subtle-success" type="button">Edit</button>
                <button class="btn btn-subtle-danger" type="button">Delete</button>
            </div>
        </div>

        <?php // Top Summary Cards ?>
        <div class="card mb-3">
            <div class="card-body ydcoza-mini-card-header">
                <div class="row g-4 justify-content-between">
                    <!-- Name Card -->
                    <div class="col-sm-auto">
                        <div class="d-flex align-items-center">
                            <div class="d-flex bg-primary-subtle rounded flex-center me-3" style="width:32px; height:32px">
                                <i class="bi bi-person text-primary"></i>
                            </div>
                            <div>
                                <p class="fw-bold mb-1"><?php esc_html_e('Agent Name', 'wecoza-core'); ?></p>
                                <h5 class="fw-bolder text-nowrap">
                                    <?php
                                    $full_name = '';
                                    if (!empty($agent['title'])) {
                                        $full_name .= $agent['title'] . ' ';
                                    }
                                    $full_name .= $agent['first_name'];
                                    if (!empty($agent['second_name'])) {
                                        $full_name .= ' ' . $agent['second_name'];
                                    }
                                    $full_name .= ' ' . $agent['last_name'];
                                    echo esc_html($full_name);
                                    ?>
                                </h5>
                            </div>
                        </div>
                    </div>

                    <!-- ID Type Card -->
                    <div class="col-sm-auto">
                        <div class="d-flex align-items-center border-start-sm ps-sm-5">
                            <div class="d-flex bg-info-subtle rounded flex-center me-3" style="width:32px; height:32px">
                                <i class="bi bi-credit-card-2-front text-info"></i>
                            </div>
                            <div>
                                <p class="fw-bold mb-1"><?php esc_html_e('ID Type', 'wecoza-core'); ?></p>
                                <h5 class="fw-bolder text-nowrap">
                                    <?php
                                    if ($agent['id_type'] === 'sa_id') {
                                        esc_html_e('SA ID', 'wecoza-core');
                                    } else {
                                        esc_html_e('Passport', 'wecoza-core');
                                    }
                                    ?>
                                </h5>
                            </div>
                        </div>
                    </div>

                    <!-- Status Card -->
                    <div class="col-sm-auto">
                        <div class="d-flex align-items-center border-start-sm ps-sm-5">
                            <div class="d-flex bg-success-subtle rounded flex-center me-3" style="width:32px; height:32px">
                                <i class="bi bi-toggle-on text-success"></i>
                            </div>
                            <div>
                                <p class="fw-bold mb-1"><?php esc_html_e('Status', 'wecoza-core'); ?></p>
                                <h5 class="fw-bolder text-nowrap">
                                    <?php
                                    $status_class = ($agent['status'] === 'active') ? 'success' : 'secondary';
                                    $status_text = ($agent['status'] === 'active') ? __('Active', 'wecoza-core') : __('Inactive', 'wecoza-core');
                                    ?>
                                    <span class="badge bg-<?php echo esc_attr($status_class); ?>">
                                        <?php echo esc_html($status_text); ?>
                                    </span>
                                </h5>
                            </div>
                        </div>
                    </div>

                    <!-- SACE Card -->
                    <div class="col-sm-auto">
                        <div class="d-flex align-items-center border-start-sm ps-sm-5">
                            <div class="d-flex bg-warning-subtle rounded flex-center me-3" style="width:32px; height:32px">
                                <i class="bi bi-award text-warning"></i>
                            </div>
                            <div>
                                <p class="fw-bold mb-1"><?php esc_html_e('SACE Registration', 'wecoza-core'); ?></p>
                                <h5 class="fw-bolder text-nowrap">
                                    <?php if (!empty($agent['sace_number'])) : ?>
                                        <span><?php echo esc_html($agent['sace_number']); ?></span>
                                    <?php else : ?>
                                        <span class="text-muted"><?php esc_html_e('Not Registered', 'wecoza-core'); ?></span>
                                    <?php endif; ?>
                                </h5>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Card -->
                    <div class="col-sm-auto">
                        <div class="d-flex align-items-center border-start-sm ps-sm-5">
                            <div class="d-flex bg-primary-subtle rounded flex-center me-3" style="width:32px; height:32px">
                                <i class="bi bi-telephone text-primary"></i>
                            </div>
                            <div>
                                <p class="fw-bold mb-1"><?php esc_html_e('Contact', 'wecoza-core'); ?></p>
                                <h5 class="fw-bolder text-nowrap">
                                    <a href="tel:<?php echo esc_attr($agent['phone']); ?>" class="text-decoration-none">
                                        <?php echo esc_html($agent['phone']); ?>
                                    </a>
                                </h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php // Details Tables ?>
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
                                        <p class="fw-bold mb-0"><?php esc_html_e('Agent ID :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <p class="fw-semibold mb-0">#<?php echo esc_html($agent['agent_id']); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                            <i class="bi bi-person-circle text-info" style="font-size: 12px;"></i>
                                        </div>
                                        <p class="fw-bold mb-0"><?php esc_html_e('Full Name :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <p class="fw-semibold mb-0"><?php echo esc_html($full_name); ?></p>
                                    <?php if (!empty($agent['initials'])) : ?>
                                        <small class="text-muted"><?php esc_html_e('Initials:', 'wecoza-core'); ?> <?php echo esc_html($agent['initials']); ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <tr>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                            <i class="bi bi-people text-primary" style="font-size: 12px;"></i>
                                        </div>
                                        <p class="fw-bold mb-0"><?php esc_html_e('Gender :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <p class="fw-semibold mb-0"><?php echo esc_html($agent['gender']); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex bg-success-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                            <i class="bi bi-globe text-success" style="font-size: 12px;"></i>
                                        </div>
                                        <p class="fw-bold mb-0"><?php esc_html_e('Race :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <p class="fw-semibold mb-0"><?php echo esc_html($agent['race']); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                            <i class="bi bi-credit-card text-info" style="font-size: 12px;"></i>
                                        </div>
                                        <p class="fw-bold mb-0"><?php esc_html_e('ID Number :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <p class="fw-semibold mb-0">
                                        <?php
                                        if ($agent['id_type'] === 'sa_id' && !empty($agent['id_number'])) {
                                            echo esc_html($agent['id_number']);
                                        } elseif (isset($agent['passport_number'])) {
                                            echo esc_html($agent['passport_number']);
                                        } else {
                                            echo '<span class="text-muted">' . esc_html__('Not provided', 'wecoza-core') . '</span>';
                                        }
                                        ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                            <i class="bi bi-telephone text-primary" style="font-size: 12px;"></i>
                                        </div>
                                        <p class="fw-bold mb-0"><?php esc_html_e('Phone :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <p class="fw-semibold mb-0">
                                        <a href="tel:<?php echo esc_attr($agent['phone']); ?>" class="text-decoration-none">
                                            <?php echo esc_html($agent['phone']); ?>
                                        </a>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                            <i class="bi bi-envelope text-info" style="font-size: 12px;"></i>
                                        </div>
                                        <p class="fw-bold mb-0"><?php esc_html_e('Email :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <p class="fw-semibold mb-0">
                                        <a href="mailto:<?php echo esc_attr($agent['email']); ?>" class="text-decoration-none">
                                            <?php echo esc_html($agent['email']); ?>
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
                                        <p class="fw-bold mb-0"><?php esc_html_e('Address :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <div class="fw-semibold mb-0">
                                        <?php echo esc_html($agent['street_address']); ?><br>
                                        <?php if (!empty($agent['address_line_2'])) : ?>
                                            <?php echo esc_html($agent['address_line_2']); ?><br>
                                        <?php endif; ?>
                                        <?php if (!empty($agent['residential_suburb'])) : ?>
                                            <?php echo esc_html($agent['residential_suburb']); ?><br>
                                        <?php endif; ?>
                                        <?php echo esc_html($agent['city'] . ', ' . $agent['province'] . ', ' . $agent['postal_code']); ?>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                            <i class="bi bi-geo-alt-fill text-primary" style="font-size: 12px;"></i>
                                        </div>
                                        <p class="fw-bold mb-0"><?php esc_html_e('Preferred Areas :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <?php
                                    // Get working area names
                                    $has_working_areas = false;
                                    $working_areas = array();

                                    for ($i = 1; $i <= 3; $i++) {
                                        $area_id = $agent["preferred_working_area_$i"] ?? null;
                                        if (!empty($area_id)) {
                                            $area_name = \WeCoza\Agents\Services\WorkingAreasService::get_working_area_by_id($area_id);
                                            if ($area_name) {
                                                $working_areas[] = $area_name;
                                                $has_working_areas = true;
                                            }
                                        }
                                    }
                                    ?>

                                    <?php if ($has_working_areas) : ?>
                                        <div class="fw-semibold mb-0">
                                            <?php foreach ($working_areas as $index => $area) : ?>
                                                <div class="mb-1">
                                                    <span class="badge bg-primary me-2"><?php echo $index + 1; ?></span>
                                                    <?php echo esc_html($area); ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else : ?>
                                        <p class="fw-semibold mb-0 text-muted">
                                            <?php esc_html_e('No preferred areas specified', 'wecoza-core'); ?>
                                        </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Right Column - Professional & Compliance -->
                <div class="col-sm-12 col-xxl-6 border-bottom py-3">
                    <table class="w-100 table-stats table table-hover table-sm fs-9 mb-0">
                        <tbody>
                            <tr>
                                <td class="py-2 ydcoza-w-150">
                                    <div class="d-inline-flex align-items-center">
                                        <div class="d-flex bg-warning-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                            <i class="bi bi-award text-warning" style="font-size: 12px;"></i>
                                        </div>
                                        <p class="fw-bold mb-0"><?php esc_html_e('SACE Number :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <p class="fw-semibold mb-0">
                                        <?php if (!empty($agent['sace_number'])) : ?>
                                            <?php echo esc_html($agent['sace_number']); ?>
                                            <?php
                                            // Check if SACE is expired
                                            $is_expired = false;
                                            if (!empty($agent['sace_expiry_date'])) {
                                                $expiry_date = strtotime($agent['sace_expiry_date']);
                                                $is_expired = $expiry_date < time();
                                            }
                                            ?>
                                            <?php if ($is_expired) : ?>
                                                <span class="badge bg-danger ms-2"><?php esc_html_e('Expired', 'wecoza-core'); ?></span>
                                            <?php else : ?>
                                                <span class="badge bg-success ms-2"><?php esc_html_e('Valid', 'wecoza-core'); ?></span>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <span class="text-muted"><?php esc_html_e('Not registered', 'wecoza-core'); ?></span>
                                        <?php endif; ?>
                                    </p>
                                </td>
                            </tr>

                            <?php if (!empty($agent['sace_number'])) : ?>
                            <tr>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                            <i class="bi bi-calendar-plus text-info" style="font-size: 12px;"></i>
                                        </div>
                                        <p class="fw-bold mb-0"><?php esc_html_e('SACE Registered :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <p class="fw-semibold mb-0">
                                        <?php
                                        if (!empty($agent['sace_registration_date'])) {
                                            echo esc_html(date_i18n($date_format, strtotime($agent['sace_registration_date'])));
                                        } else {
                                            echo '<span class="text-muted">-</span>';
                                        }
                                        ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex bg-warning-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                            <i class="bi bi-calendar-x text-warning" style="font-size: 12px;"></i>
                                        </div>
                                        <p class="fw-bold mb-0"><?php esc_html_e('SACE Expires :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <p class="fw-semibold mb-0">
                                        <?php
                                        if (!empty($agent['sace_expiry_date'])) {
                                            echo esc_html(date_i18n($date_format, strtotime($agent['sace_expiry_date'])));
                                            if ($is_expired) {
                                                echo ' <span class="text-danger">(' . esc_html__('Expired', 'wecoza-core') . ')</span>';
                                            }
                                        } else {
                                            echo '<span class="text-muted">-</span>';
                                        }
                                        ?>
                                    </p>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <tr>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                            <i class="bi bi-mortarboard text-primary" style="font-size: 12px;"></i>
                                        </div>
                                        <p class="fw-bold mb-0"><?php esc_html_e('Qualification :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <p class="fw-semibold mb-0"><?php echo esc_html($agent['highest_qualification']); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex bg-success-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                            <i class="bi bi-check-circle text-success" style="font-size: 12px;"></i>
                                        </div>
                                        <p class="fw-bold mb-0"><?php esc_html_e('Quantum Tests :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <div class="fw-semibold mb-0">
                                        <?php if (isset($agent['quantum_maths_score']) && $agent['quantum_maths_score'] > 0) : ?>
                                            <span class="badge badge-phoenix fs-10 badge-phoenix-info me-1">
                                                <?php esc_html_e('Maths:', 'wecoza-core'); ?> <?php echo esc_html($agent['quantum_maths_score']); ?>%
                                            </span>
                                        <?php endif; ?>
                                        <?php if (isset($agent['quantum_science_score']) && $agent['quantum_science_score'] > 0) : ?>
                                            <span class="badge badge-phoenix fs-10 badge-phoenix-info">
                                                <?php esc_html_e('Science:', 'wecoza-core'); ?> <?php echo esc_html($agent['quantum_science_score']); ?>%
                                            </span>
                                        <?php endif; ?>
                                        <?php if ((!isset($agent['quantum_maths_score']) || $agent['quantum_maths_score'] == 0) && (!isset($agent['quantum_science_score']) || $agent['quantum_science_score'] == 0)) : ?>
                                            <span class="text-muted"><?php esc_html_e('Not taken', 'wecoza-core'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                            <i class="bi bi-book text-primary" style="font-size: 12px;"></i>
                                        </div>
                                        <p class="fw-bold mb-0"><?php esc_html_e('Teaching Phase :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <p class="fw-semibold mb-0">
                                        <?php echo !empty($agent['phase_registered']) ? esc_html($agent['phase_registered']) : '<span class="text-muted">-</span>'; ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                            <i class="bi bi-journal-bookmark text-info" style="font-size: 12px;"></i>
                                        </div>
                                        <p class="fw-bold mb-0"><?php esc_html_e('Subjects :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <p class="fw-semibold mb-0">
                                        <?php echo !empty($agent['subjects_registered']) ? esc_html($agent['subjects_registered']) : '<span class="text-muted">-</span>'; ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex bg-success-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                            <i class="bi bi-calendar-check text-success" style="font-size: 12px;"></i>
                                        </div>
                                        <p class="fw-bold mb-0"><?php esc_html_e('Training Date :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <p class="fw-semibold mb-0">
                                        <?php
                                        if (!empty($agent['agent_training_date'])) {
                                            echo esc_html(date_i18n($date_format, strtotime($agent['agent_training_date'])));
                                        } else {
                                            echo '<span class="text-muted">-</span>';
                                        }
                                        ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                            <i class="bi bi-bank text-info" style="font-size: 12px;"></i>
                                        </div>
                                        <p class="fw-bold mb-0"><?php esc_html_e('Banking :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <div class="fw-semibold mb-0">
                                        <?php if (!empty($agent['bank_name']) && !empty($agent['account_number'])) : ?>
                                            <?php echo esc_html($agent['bank_name']); ?>
                                            <?php if (!empty($agent['bank_branch_code'])) : ?>
                                                <small class="text-muted">(<?php echo esc_html($agent['bank_branch_code']); ?>)</small>
                                            <?php endif; ?>
                                            <div class="fs-9 text-muted">
                                                <?php echo esc_html($agent['account_type'] . ' - ' . substr($agent['account_number'], -4)); ?>
                                                <?php if (!empty($agent['account_holder'])) : ?>
                                                    <br><?php esc_html_e('Holder:', 'wecoza-core'); ?> <?php echo esc_html($agent['account_holder']); ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php else : ?>
                                            <span class="text-warning">
                                                <i class="bi bi-exclamation-circle me-1"></i><?php esc_html_e('Incomplete', 'wecoza-core'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>


                            <tr>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex bg-secondary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                            <i class="bi bi-calendar-check text-secondary" style="font-size: 12px;"></i>
                                        </div>
                                        <p class="fw-bold mb-0"><?php esc_html_e('Created :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <p class="fw-semibold mb-0">
                                        <?php
                                        if (!empty($agent['created_at'])) {
                                            echo esc_html(date_i18n($date_format . ' ' . get_option('time_format'), strtotime($agent['created_at'])));
                                        } else {
                                            echo '<span class="text-muted">-</span>';
                                        }
                                        ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex bg-primary-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                            <i class="bi bi-calendar-event text-primary" style="font-size: 12px;"></i>
                                        </div>
                                        <p class="fw-bold mb-0"><?php esc_html_e('Last Updated :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <p class="fw-semibold mb-0">
                                        <?php
                                        if (!empty($agent['updated_at'])) {
                                            echo esc_html(date_i18n($date_format . ' ' . get_option('time_format'), strtotime($agent['updated_at'])));
                                        } else {
                                            echo '<span class="text-muted">-</span>';
                                        }
                                        ?>
                                    </p>
                                </td>
                            </tr>

                            <?php if (!empty($agent['notes'])) : ?>
                            <tr>
                                <td class="py-2">
                                    <div class="d-flex align-items-center">
                                        <div class="d-flex bg-info-subtle rounded-circle flex-center me-3" style="width:24px; height:24px">
                                            <i class="bi bi-sticky text-info" style="font-size: 12px;"></i>
                                        </div>
                                        <p class="fw-bold mb-0"><?php esc_html_e('Notes :', 'wecoza-core'); ?></p>
                                    </div>
                                </td>
                                <td class="py-2">
                                    <div class="fw-semibold mb-0 text-wrap">
                                        <?php echo esc_html($agent['notes']); ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php // Documents Section ?>
        <div class="card mb-3">
            <div class="card-header bg-light">
                <h6 class="mb-0">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    <?php esc_html_e('Documents & Compliance', 'wecoza-core'); ?>
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Criminal Record Check -->
                    <div class="col-md-6 mb-3">
                        <div class="d-flex align-items-start">
                            <div class="d-flex bg-danger-subtle rounded-circle flex-center me-3" style="width:40px; height:40px">
                                <i class="bi bi-shield-check text-danger"></i>
                            </div>
                            <div class="flex-1">
                                <h6 class="mb-1"><?php esc_html_e('Criminal Record Check', 'wecoza-core'); ?></h6>
                                <?php if (!empty($agent['criminal_record_file'])) : ?>
                                    <p class="text-success mb-1">
                                        <i class="bi bi-check-circle me-1"></i>
                                        <?php esc_html_e('Completed', 'wecoza-core'); ?>
                                    </p>
                                    <?php if (!empty($agent['criminal_record_date'])) : ?>
                                        <p class="text-muted small mb-2">
                                            <?php echo esc_html(date_i18n($date_format, strtotime($agent['criminal_record_date']))); ?>
                                        </p>
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url(wp_upload_dir()['baseurl'] . $agent['criminal_record_file']); ?>"
                                       class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="bi bi-download me-1"></i>
                                        <?php esc_html_e('Download', 'wecoza-core'); ?>
                                    </a>
                                <?php else : ?>
                                    <p class="text-warning mb-0">
                                        <i class="bi bi-exclamation-circle me-1"></i>
                                        <?php esc_html_e('Not submitted', 'wecoza-core'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Signed Agreement -->
                    <div class="col-md-6 mb-3">
                        <div class="d-flex align-items-start">
                            <div class="d-flex bg-success-subtle rounded-circle flex-center me-3" style="width:40px; height:40px">
                                <i class="bi bi-file-earmark-check text-success"></i>
                            </div>
                            <div class="flex-1">
                                <h6 class="mb-1"><?php esc_html_e('Agent Agreement', 'wecoza-core'); ?></h6>
                                <?php if (!empty($agent['signed_agreement_file'])) : ?>
                                    <p class="text-success mb-1">
                                        <i class="bi bi-check-circle me-1"></i>
                                        <?php esc_html_e('Signed', 'wecoza-core'); ?>
                                    </p>
                                    <?php if (!empty($agent['signed_agreement_date'])) : ?>
                                        <p class="text-muted small mb-2">
                                            <?php echo esc_html(date_i18n($date_format, strtotime($agent['signed_agreement_date']))); ?>
                                        </p>
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url(wp_upload_dir()['baseurl'] . $agent['signed_agreement_file']); ?>"
                                       class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="bi bi-download me-1"></i>
                                        <?php esc_html_e('Download', 'wecoza-core'); ?>
                                    </a>
                                <?php else : ?>
                                    <p class="text-warning mb-0">
                                        <i class="bi bi-exclamation-circle me-1"></i>
                                        <?php esc_html_e('Not signed', 'wecoza-core'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

</div>
