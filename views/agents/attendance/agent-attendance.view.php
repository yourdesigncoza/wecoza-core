<?php
/**
 * Agent Attendance View
 *
 * Renders the agent-facing class list for attendance capture.
 * Each class card links to the existing single-class attendance page.
 *
 * @package WeCoza\Agents
 * @since 7.0.0
 *
 * @var array $classes Array of class rows from the database
 * @var int   $agentId Agent ID of the currently logged-in agent
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="agent-attendance-page">

    <div class="mb-4">
        <h5 class="mb-1">My Classes</h5>
        <p class="text-muted fs-9 mb-0">Classes assigned to you for attendance capture</p>
    </div>

    <?php if (empty($classes)) : ?>

        <div class="alert alert-info" role="alert">
            <span class="fas fa-info-circle me-2"></span>
            No classes assigned to you.
        </div>

    <?php else : ?>

        <div class="row g-3">

            <?php foreach ($classes as $class) :
                $classId      = (int) $class['class_id'];
                $classCode    = htmlspecialchars((string) ($class['class_code'] ?? ''), ENT_QUOTES, 'UTF-8');
                $classSubject = htmlspecialchars((string) ($class['class_subject'] ?? ''), ENT_QUOTES, 'UTF-8');
                $classType    = htmlspecialchars((string) ($class['class_type'] ?? ''), ENT_QUOTES, 'UTF-8');
                $classStatus  = htmlspecialchars((string) ($class['class_status'] ?? ''), ENT_QUOTES, 'UTF-8');
                $clientName   = htmlspecialchars((string) ($class['client_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                $siteName     = htmlspecialchars((string) ($class['site_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                $addressLine  = htmlspecialchars((string) ($class['class_address_line'] ?? ''), ENT_QUOTES, 'UTF-8');
                $startDate    = $class['original_start_date'] ? date('d M Y', strtotime($class['original_start_date'])) : '';

                // Count active learners from JSONB array
                $learnerIds   = $class['learner_ids'] ?? [];
                if (is_string($learnerIds)) {
                    $learnerIds = json_decode($learnerIds, true) ?: [];
                }
                $learnerCount = count($learnerIds);

                // Build location string: site name, fallback to address
                $location = $siteName ?: $addressLine;

                // Map status to Phoenix badge variant
                $statusBadge = match ($classStatus) {
                    'active'    => 'badge-phoenix-success',
                    'stopped'   => 'badge-phoenix-warning',
                    'completed' => 'badge-phoenix-secondary',
                    default     => 'badge-phoenix-secondary',
                };

                $attendanceUrl = home_url('/app/display-single-class/?class_id=' . $classId);
            ?>

                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body">

                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h6 class="mb-0 fw-semibold"><?php echo $classCode; ?></h6>
                                <span class="badge badge-phoenix <?php echo $statusBadge; ?>">
                                    <?php echo ucfirst($classStatus); ?>
                                </span>
                            </div>

                            <?php if ($clientName) : ?>
                                <p class="fs-9 fw-semibold text-body mb-1">
                                    <span class="fas fa-building me-1 text-muted"></span><?php echo $clientName; ?>
                                </p>
                            <?php endif; ?>

                            <p class="fs-9 text-muted mb-1"><?php echo $classSubject; ?></p>

                            <?php if ($location) : ?>
                                <p class="fs-10 text-muted mb-1">
                                    <span class="fas fa-map-marker-alt me-1"></span><?php echo $location; ?>
                                </p>
                            <?php endif; ?>

                            <?php if ($startDate) : ?>
                                <p class="fs-10 text-muted mb-1">
                                    <span class="fas fa-calendar me-1"></span>Started <?php echo $startDate; ?>
                                </p>
                            <?php endif; ?>

                            <div class="d-flex align-items-center gap-2 mt-2">
                                <?php if ($classType) : ?>
                                    <span class="badge badge-phoenix badge-phoenix-secondary fs-10">
                                        <?php echo $classType; ?>
                                    </span>
                                <?php endif; ?>
                                <span class="badge badge-phoenix badge-phoenix-info fs-10">
                                    <span class="fas fa-users me-1"></span><?php echo $learnerCount; ?> Learner<?php echo $learnerCount !== 1 ? 's' : ''; ?>
                                </span>
                            </div>

                        </div>
                        <div class="card-footer bg-transparent border-top-0 pt-0">
                            <a href="<?php echo esc_url($attendanceUrl); ?>"
                               class="btn btn-phoenix-primary btn-sm w-100">
                                <span class="fas fa-calendar-check me-1"></span>
                                View / Capture Attendance
                            </a>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>
