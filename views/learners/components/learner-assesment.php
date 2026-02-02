<?php
$upload_dir = wp_upload_dir();

$return = '<!-- Section Placement Assesment Info. -->
      <div class="container-fluid gtab tab-2 border-top border-bottom lh-1 mb-2">
         <!-- Row 1 -->
         <div class="row border-start">
            <div class="col border-end p-2 bg-light d-flex align-items-center">Placement Assessment Date</div>
            <div class="col border-end p-2 d-flex align-items-center">' . esc_html($learner->placement_assessment_date ?? '') . '</div>
            <div class="col border-end p-2 bg-light d-flex align-items-center">Num Placement Level</div>
            <div class="col border-end p-2 d-flex align-items-center">' . esc_html($learner->numeracy_level ?? '') . '</div>
            <div class="col border-end p-2 bg-light d-flex align-items-center">Comm Placement Level</div>
            <div class="col border-end p-2 d-flex align-items-center">' . esc_html($learner->communication_level ?? '') . '</div>
         </div>
         <!-- Row 2 -->
         <div class="row border-top border-start">
            <div class="col border-end p-2 bg-light d-flex align-items-center">Report</div>
            <div class="col border-end p-2 d-flex align-items-center">Uploaded: 09/27/2024</div>
            <div class="col-9 border-end d-flex align-items-center"><a href="' . esc_url($upload_dir['baseurl']) . '/reports/empty.pdf" download class="btn btn-sm btn-outline-discovery">Download</a></div>

         </div>
      </div>
      <!-- END Section Placement Assesment Info. -->';
print $return;