<?php
/**
 * Client Update Form View
 *
 * @package WeCoza\Clients
 * @since 1.0.0
 */

use WeCoza\Clients\Helpers\ViewHelpers;

// Extract variables
$client = $client ?? null;
$errors = $errors ?? [];
$success = $success ?? false;
$seta_options = $seta_options ?? [];
$status_options = $status_options ?? [];
$location_data = $location_data ?? [];
$location_selected = $location_data["selected"] ?? [];
$location_hierarchy = $location_data["hierarchy"] ?? [];
$sites = $sites ?? ["head" => null, "sub_sites" => []];
$main_clients = $main_clients ?? [];
$is_update_mode = $is_update_mode ?? false;

// Sub-client variables
$is_sub_client = !empty($client["main_client_id"]);
$selected_main_client_id = $client["main_client_id"] ?? "";
$is_sub_client_checked = $is_sub_client ? "checked" : "";

$headSite = $sites["head"] ?? null;

$headSiteId = $headSite["site_id"] ?? ($client["site_id"] ?? "");
$headSiteName =
    $headSite["site_name"] ??
    ($client["site_name"] ?? ($client["client_name"] ?? ""));
$headSiteAddress1 =
    $headSite["address_line_1"] ?? ($client["client_street_address"] ?? "");

$selected_province =
    $location_selected["province"] ?? ($client["client_province"] ?? "");
$selected_town = $location_selected["town"] ?? ($client["client_town"] ?? "");
$selected_location_id =
    $location_selected["locationId"] ?? ($client["client_town_id"] ?? "");
$selected_suburb =
    $location_selected["suburb"] ?? ($client["client_suburb"] ?? "");
$selected_postal_code =
    $location_selected["postalCode"] ?? ($client["client_postal_code"] ?? "");

$province_options = [];
$town_options = [];
$suburb_options = [];

foreach ($location_hierarchy as $provinceData) {
    $provinceName = $provinceData["name"] ?? "";
    if ($provinceName === "") {
        continue;
    }

    $province_options[$provinceName] = $provinceName;

    if ($provinceName !== $selected_province || empty($provinceData["towns"])) {
        continue;
    }

    foreach ($provinceData["towns"] as $townData) {
        $townName = $townData["name"] ?? "";
        if ($townName === "") {
            continue;
        }

        $town_options[$townName] = $townName;

        if ($townName !== $selected_town || empty($townData["suburbs"])) {
            continue;
        }

        foreach ($townData["suburbs"] as $suburbData) {
            $locationId = isset($suburbData["id"])
                ? (int) $suburbData["id"]
                : 0;
            if ($locationId <= 0) {
                continue;
            }

            $label = $suburbData["name"] ?? "";
            $suburb_options[$locationId] = [
                "label" => $label,
                "data" => [
                    "postal_code" => $suburbData["postal_code"] ?? "",
                    "suburb" => $label,
                    "town" => $townName,
                    "province" => $provinceName,
                ],
            ];
        }
    }
}

$has_province = $selected_province !== "";
$has_town = $selected_town !== "";
$has_location = !empty($selected_location_id);

$is_edit = !empty($client["id"]);

$resolved_contact_person = $client["contact_person"] ?? "";
$resolved_contact_email = $client["contact_person_email"] ?? "";
$resolved_contact_cell = $client["contact_person_cellphone"] ?? "";
$resolved_contact_tel = $client["contact_person_tel"] ?? "";
$resolved_contact_position = $client["contact_person_position"] ?? "";
?>

<div class="wecoza-clients-form-container">
    <?php if ($success): ?>
        <?php echo ViewHelpers::renderAlert(
            "Client updated successfully!",
            "success",
            true,
        ); ?>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <?php if (isset($errors["general"])): ?>
            <?php echo ViewHelpers::renderAlert(
                $errors["general"],
                "error",
                true,
            ); ?>
        <?php else: ?>
            <?php echo ViewHelpers::renderAlert(
                "Please correct the errors below.",
                "error",
                true,
            ); ?>
        <?php endif; ?>
    <?php endif; ?>

    <h4 class="mb-1 mt-4">Update Client</h4>

    <form id="clients-form" class="needs-validation ydcoza-compact-form" novalidate method="POST" enctype="multipart/form-data">
        <?php wp_nonce_field("clients_nonce_action", "nonce"); ?>

        <input type="hidden" name="id" value="<?php echo esc_attr(
            $client["id"],
        ); ?>">
        <input type="hidden" name="head_site_id" value="<?php echo esc_attr(
            $headSiteId,
        ); ?>">

        <!-- Basic Information -->
        <div class="row">
            <?php
            echo ViewHelpers::renderField(
                "text",
                "client_name",
                "Client Name",
                $client["client_name"] ?? "",
                [
                    "required" => true,
                    "col_class" => "col-md-3",
                    "error" => $errors["client_name"] ?? "",
                ],
            );

            echo ViewHelpers::renderField(
                "text",
                "company_registration_nr",
                "Company Registration Nr",
                $client["company_registration_nr"] ?? "",
                [
                    "required" => true,
                    "col_class" => "col-md-3",
                    "error" => $errors["company_registration_nr"] ?? "",
                ],
            );
            ?>
        </div>

        <!-- Site Information -->
        <div class="row mt-3">
            <div class="col-3">
                <?php echo ViewHelpers::renderField(
                    "text",
                    "site_name",
                    "Site Name",
                    $headSiteName,
                    [
                        "required" => true,
                        "col_class" => "",
                        "error" => $errors["site_name"] ?? "",
                    ],
                ); ?>
            </div>
        </div>

        <!-- Sub-Client Information -->
        <div class="row mt-4">
            <div class="col-3">
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="is_sub_client" name="is_sub_client" <?php echo $is_sub_client_checked; ?>>
                    <label class="form-check-label" for="is_sub_client">
                        <strong>Is SubClient</strong><br>
                        <small class="text-muted">Check if this client is a branch/subsidiary of another main client</small>
                    </label>
                </div>
            </div>
            <div class="col-3">
                <div id="main_client_dropdown_container" style="<?php echo $is_sub_client
                    ? ""
                    : "display: none;"; ?>">
                    <?php
                    // Prepare enhanced main client options with company registration numbers
                    $main_client_options = ["" => "Select Main Client..."];
                    if (!empty($main_clients_raw)) {
                        foreach ($main_clients_raw as $main_client) {
                            $label = $main_client["client_name"];
                            if (
                                !empty($main_client["company_registration_nr"])
                            ) {
                                $label .=
                                    " (" .
                                    $main_client["company_registration_nr"] .
                                    ")";
                            }
                            $main_client_options[$main_client["id"]] = $label;
                        }
                    } else {
                        // Fallback to basic format if raw data not available
                        $main_client_options = $main_clients;
                    }

                    echo ViewHelpers::renderField(
                        "select",
                        "main_client_id",
                        "Main Client",
                        $selected_main_client_id,
                        [
                            "required" => true,
                            "col_class" => "js-main-client-field",
                            "class" => "js-main-client-select",
                            "options" => $main_client_options,
                            "error" => $errors["main_client_id"] ?? "",
                        ],
                    );
                    ?>
                </div>
            </div>
        </div>

        <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>

        <!-- Address Information -->
        <div class="row">
            <p class="text-muted">The correct address should already be registered in the Locations table. If not, please add it there first.</p>

            <?php
            echo ViewHelpers::renderField(
                "select",
                "client_province",
                "Province",
                $selected_province,
                [
                    "required" => true,
                    "col_class" => "col-md-3 js-province-field",
                    "class" => "js-province-select",
                    "options" => $province_options,
                    "error" => $errors["client_province"] ?? "",
                ],
            );

            echo ViewHelpers::renderField(
                "select",
                "client_town",
                "Town",
                $selected_town,
                [
                    "required" => true,
                    "col_class" =>
                        "col-md-3 js-town-field" .
                        ($has_province ? "" : " d-none"),
                    "class" => "js-town-select",
                    "options" => $town_options,
                    "error" => "",
                ],
            );

            echo ViewHelpers::renderField(
                "select",
                "client_town_id",
                "Suburb",
                $selected_location_id,
                [
                    "required" => true,
                    "col_class" =>
                        "col-md-3 js-suburb-field" .
                        ($has_town ? "" : " d-none"),
                    "class" => "js-suburb-select",
                    "options" => $suburb_options,
                    "error" =>
                        $errors["client_town_id"] ??
                        ($errors["client_suburb"] ??
                            ($errors["site_place_id"] ?? "")),
                ],
            );

            echo ViewHelpers::renderField(
                "text",
                "client_postal_code",
                "Client Postal Code",
                $selected_postal_code,
                [
                    "required" => true,
                    "readonly" => true,
                    "col_class" =>
                        "col-md-3 js-postal-field" .
                        ($has_location ? "" : " d-none"),
                    "error" => $errors["client_postal_code"] ?? "",
                ],
            );
            ?>
        </div>

        <input type="hidden" name="client_suburb" value="<?php echo esc_attr(
            $selected_suburb,
        ); ?>" class="js-suburb-hidden">
        <input type="hidden" name="client_town_name" value="<?php echo esc_attr(
            $selected_town,
        ); ?>" class="js-town-hidden">

        <div class="row mt-3">
            <?php echo ViewHelpers::renderField(
                "text",
                "client_street_address",
                "Client Street Address",
                $headSiteAddress1,
                [
                    "required" => true,
                    "readonly" => $has_location,
                    "title" => $has_location
                        ? "Address auto-populated from location data"
                        : "",
                    "col_class" =>
                        "col-md-3 js-address-field js-street-address-field" .
                        ($has_location ? "" : " d-none"),
                    "error" =>
                        $errors["site_address_line_1"] ??
                        ($errors["client_street_address"] ?? ""),
                ],
            ); ?>
        </div>

        <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>

        <!-- Contact Information -->
        <div class="row">
            <?php
            echo ViewHelpers::renderField(
                "text",
                "contact_person",
                "Contact Person",
                $resolved_contact_person,
                [
                    "required" => true,
                    "col_class" => "col-md-3",
                    "error" => $errors["contact_person"] ?? "",
                ],
            );

            echo ViewHelpers::renderField(
                "email",
                "contact_person_email",
                "Contact Person Email",
                $resolved_contact_email,
                [
                    "required" => true,
                    "col_class" => "col-md-3",
                    "error" => $errors["contact_person_email"] ?? "",
                ],
            );

            echo ViewHelpers::renderField(
                "tel",
                "contact_person_cellphone",
                "Contact Person Cellphone",
                $resolved_contact_cell,
                [
                    "required" => true,
                    "col_class" => "col-md-3",
                    "error" => $errors["contact_person_cellphone"] ?? "",
                ],
            );

            echo ViewHelpers::renderField(
                "tel",
                "contact_person_tel",
                "Contact Person Tel Number",
                $resolved_contact_tel,
                [
                    "col_class" => "col-md-3",
                    "error" => $errors["contact_person_tel"] ?? "",
                ],
            );

            echo ViewHelpers::renderField(
                "text",
                "contact_person_position",
                "Contact Person Position",
                $resolved_contact_position,
                [
                    "col_class" => "col-md-3",
                    "error" => $errors["contact_person_position"] ?? "",
                ],
            );
            ?>
        </div>

        <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>

        <!-- Business Information -->
        <div class="row mt-3">
            <?php
            // Prepare SETA options for select
            $seta_select_options = [];
            foreach ($seta_options as $seta) {
                $seta_select_options[$seta] = $seta;
            }

            echo ViewHelpers::renderField(
                "select",
                "seta",
                "SETA",
                $client["seta"] ?? "",
                [
                    "required" => true,
                    "col_class" => "col-md-3",
                    "options" => $seta_select_options,
                    "error" => $errors["seta"] ?? "",
                ],
            );

            echo ViewHelpers::renderField(
                "select",
                "client_status",
                "Client Status",
                $client["client_status"] ?? "",
                [
                    "required" => true,
                    "col_class" => "col-md-3",
                    "options" => $status_options,
                    "error" => $errors["client_status"] ?? "",
                ],
            );

            echo ViewHelpers::renderField(
                "date",
                "financial_year_end",
                "Financial Year End",
                $client["financial_year_end"] ?? "",
                [
                    "required" => true,
                    "col_class" => "col-md-3",
                    "error" => $errors["financial_year_end"] ?? "",
                ],
            );

            echo ViewHelpers::renderField(
                "date",
                "bbbee_verification_date",
                "BBBEE Verification Date",
                $client["bbbee_verification_date"] ?? "",
                [
                    "required" => true,
                    "col_class" => "col-md-3",
                    "error" => $errors["bbbee_verification_date"] ?? "",
                ],
            );
            ?>
        </div>

        <div class="border-top border-opacity-25 border-3 border-discovery my-5 mx-1"></div>

        <!-- Form Actions -->
        <div class="row">
            <div class="col-md-3">
                <div class="d-flex gap-2">
                    <a href="<?php echo esc_url(
                        remove_query_arg(["mode", "client_id"]),
                    ); ?>" class="btn btn-subtle-warning mt-3">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-subtle-primary mt-3" id="saveClientBtn">
                        Update Client
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
