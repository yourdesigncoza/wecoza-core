<?php 
$output = '<div class="container-fluid lh-1 mb-2">';

$output .= '<div class="row border">';
$output .= '<div class="col-1 p-2 text-black fw-medium border-end">First Name</div>';
$output .= '<div class="col-1 p-2 text-black fw-medium border-end">Surname</div>';
$output .= '<div class="col-1 p-2 text-black fw-medium border-end">Gender</div>';
$output .= '<div class="col-1 p-2 text-black fw-medium border-end">Race</div>';
$output .= '<div class="col-2 p-2 text-black fw-medium border-end">Tel Number</div>';
$output .= '<div class="col-2 p-2 text-black fw-medium border-end">Email Address</div>';
$output .= '<div class="col-1 p-2 text-black fw-medium border-end">City/Town</div>';
$output .= '<div class="col-2 p-2 text-black fw-medium border-end">Employment Status</div>';
$output .= '<div class="col-1 p-2">&nbsp;</div>';
$output .= '</div>';

$output .= '<div class="row border border-top-0">';
$output .= '<div class="col-1 p-2 border-end">' . esc_html($learner->first_name ?? '') . '</div>';
$output .= '<div class="col-1 p-2 border-end">' . esc_html($learner->surname ?? '') . '</div>';
$output .= '<div class="col-1 p-2 border-end">' . esc_html($learner->gender ?? '') . '</div>';
$output .= '<div class="col-1 p-2 border-end">' . esc_html($learner->race ?? '') . '</div>';
$output .= '<div class="col-2 p-2 border-end">' . esc_html($learner->tel_number ?? '') . '</div>';
$output .= '<div class="col-2 p-2 border-end">' . esc_html($learner->email_address ?? '') . '</div>';
$output .= '<div class="col-1 p-2 border-end">' . esc_html($learner->city_town_name ?? '') . '</div>';
$output .= '<div class="col-2 p-2 border-end">' . esc_html($learner->employment_status) . '</div>';
$output .= '<div class="col-1 p-1 border-end"><a href=' . get_site_url() . '/update-learners/?learner_id=' . esc_attr($learner->id ?? '') . ' class="btn btn-sm bg-warning-subtle">Edit</a> <button class="btn btn-sm bg-danger-subtle delete-learner-btn" data-id="' . esc_attr($learner->id ?? '') . '">Delete</button></div>';
$output .= '</div>';
$output .= '</div>';
$output .= '<div class="clearfix"></div>';

print $output;
