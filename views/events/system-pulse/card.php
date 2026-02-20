<?php
/**
 * System Pulse card view — stacked sections, full width
 *
 * @var array $kpis {learners, classes, agents, clients}
 * @var array $latestClasses Latest 3 classes from DB
 * @var array $attentionItems Items needing attention
 */
if (!defined("ABSPATH")) {
    exit();
}

$kpiItems = [
    [
        "icon" => "bi-people",
        "color" => "primary",
        "value" => $kpis["learners"],
        "label" => "Learners",
    ],
    [
        "icon" => "bi-journal-text",
        "color" => "info",
        "value" => $kpis["classes"],
        "label" => "Classes",
    ],
    [
        "icon" => "bi-building",
        "color" => "success",
        "value" => $kpis["clients"],
        "label" => "Clients",
    ],
    [
        "icon" => "bi-person-badge",
        "color" => "warning",
        "value" => $kpis["agents"],
        "label" => "Agents",
    ],
];

$accentColors = ["primary", "info", "success"];
?>
<div class="card border h-100 w-100 overflow-hidden">
    <div class="bg-holder d-block bg-card" style="background-image:url(https://prium.github.io/phoenix/v1.22.0/assets/img/spot-illustrations/32.png);background-position: top right;"></div>
    <!--/.bg-holder-->
    <div class="d-dark-none">
        <div class="bg-holder d-none d-sm-block d-xl-none d-xxl-block bg-card" style="background-image:url(https://prium.github.io/phoenix/v1.22.0/assets/img/spot-illustrations/21.png);background-position: bottom right; background-size: auto;"></div>
        <!--/.bg-holder-->
    </div>
    <div class="d-light-none">
        <div class="bg-holder d-none d-sm-block d-xl-none d-xxl-block bg-card" style="background-image:url(https://prium.github.io/phoenix/v1.22.0/assets/img/spot-illustrations/dark_21.png);background-position: bottom right; background-size: auto;"></div>
        <!--/.bg-holder-->
    </div>

    <div class="card-body px-5 position-relative">
        <div class="badge badge-phoenix fs-10 badge-phoenix-success mb-4"><span class="fw-bold">Live</span><span class="fa-solid fa-signal ms-1"></span></div>
        <h3 class="mb-4">System Pulse</h3>

        <!-- Entity Totals (inline chips) -->
        <div class="d-flex flex-wrap gap-3 mb-4" style="max-width: 70%;">
            <?php foreach ($kpiItems as $kpi): ?>
            <div class="d-flex align-items-center gap-2">
                <i class="bi <?= esc_attr($kpi["icon"]) ?> text-<?= esc_attr(
     $kpi["color"],
 ) ?>" style="font-size: 1.25rem; line-height: 1;"></i>
                <span class="fw-bold fs-7 lh-1"><?= esc_html(
                    (string) $kpi["value"],
                ) ?></span>
                <span class="text-body-tertiary lh-1"><?= esc_html(
                    $kpi["label"],
                ) ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Latest Classes (mini cards) -->
        <?php if (!empty($latestClasses)): ?>
        <div class="border-top pt-3 mb-3" style="max-width: 70%;">
            <h6 class="text-body-tertiary text-uppercase fs-10 mb-2">Latest Classes</h6>
            <div class="row g-2">
                <?php foreach ($latestClasses as $i => $cls):

                    $code = $cls["class_code"] ?? "";
                    $client = $cls["client_name"] ?? "";
                    $classId = (int) ($cls["class_id"] ?? 0);
                    $count = (int) ($cls["learner_count"] ?? 0);
                    $createdAt = $cls["created_at"] ?? "";
                    $relTime = "";
                    if ($createdAt !== "") {
                        $ts = strtotime($createdAt);
                        if ($ts !== false) {
                            $relTime =
                                human_time_diff(
                                    $ts,
                                    current_time("timestamp"),
                                ) . " ago";
                        }
                    }
                    $borderColor = $accentColors[$i % count($accentColors)];
                    $classUrl = site_url(
                        "/app/display-single-class/?class_id=" . $classId,
                    );
                    ?>
                <div class="col-md-4">
                    <div class="border-start border-3 border-<?= esc_attr(
                        $borderColor,
                    ) ?> rounded-2 bg-body-highlight p-2 ps-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="fw-bold"><?= esc_html($code) ?></span>
                            <span class="text-body-tertiary fs-9"><?= esc_html(
                                $relTime,
                            ) ?></span>
                        </div>
                        <?php if ($client !== ""): ?>
                        <div class="text-body-secondary fs-9"><?= esc_html(
                            $client,
                        ) ?></div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center mt-1">
                            <span class="text-body-tertiary fs-9"><?= esc_html(
                                $count,
                            ) ?> <?= $count === 1
     ? "learner"
     : "learners" ?></span>
                            <a href="<?= esc_url(
                                $classUrl,
                            ) ?>" class="btn btn-phoenix-primary btn-sm px-3">View</a>
                        </div>
                    </div>
                </div>
                <?php
                endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Needs Attention -->
        <?php if (!empty($attentionItems)): ?>
        <div class="border-top pt-3 mb-3" style="max-width: 70%;">
            <h6 class="text-body-tertiary text-uppercase mb-3">Needs Attention</h6>
            <div class="d-flex flex-wrap gap-3">
                <?php foreach ($attentionItems as $item): ?>
                <div class="d-flex align-items-center gap-2">
                    <i class="bi <?= esc_attr(
                        $item["icon"],
                    ) ?> text-<?= esc_attr(
     $item["color"],
 ) ?>" style="font-size: 1.25rem; line-height: 1;"></i>
                    <?php if (
                        $item["value"] !== ""
                    ): ?><span class="fw-bold fs-7 lh-1"><?= esc_html(
    (string) $item["value"],
) ?></span><?php endif; ?>
                    <span class="text-body-tertiary lh-1"><?= esc_html(
                        $item["label"],
                    ) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="card-footer border-0 py-0 px-5 z-1">
        <p class="fs-10 text-body-tertiary mb-2">Updated <?= isset($cacheAge) &&
        $cacheAge > 60
            ? human_time_diff(time() - $cacheAge, time()) . " ago"
            : "just now" ?></p>
    </div>
</div>
