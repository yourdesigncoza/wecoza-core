<?php
$return = '<!-- Section Learner Info. -->
      <div class="container-fluid gtab tab-1 border-top border-bottom lh-1 mb-2 active">
         <!-- Row 1 -->
         <div class="row border-start">
            <div class="col border-end p-2 bg-light d-flex align-items-center">Initials</div>
            <div class="col border-end p-2 d-flex align-items-center">' . esc_html($learner->initials ?? '') . '</div>
            <div class="col border-end p-2 bg-light d-flex align-items-center">SA ID No</div>
            <div class="col border-end p-2 d-flex align-items-center">' . esc_html($learner->sa_id_no ?? '') . '</div>
            <div class="col border-end p-2 bg-light d-flex align-items-center">Passport Number</div>
            <div class="col border-end p-2 d-flex align-items-center">' . esc_html($learner->passport_number ?? '') . '</div>
            <div class="col border-end p-2 bg-light d-flex align-items-center">Alternative Tel Number</div>
            <div class="col border-end p-2 d-flex align-items-center">' . esc_html($learner->alternative_tel_number ?? '') . '</div>
         </div>
         <!-- Row 2 -->
         <div class="row border-top border-start">
            <div class="col border-end p-2 bg-light d-flex align-items-center">Address Line 1</div>
            <div class="col border-end p-2 d-flex align-items-center">' . esc_html($learner->address_line_1 ?? '') . '</div>
            <div class="col border-end p-2 bg-light d-flex align-items-center">Address Line 2</div>
            <div class="col border-end p-2 d-flex align-items-center">' . esc_html($learner->address_line_2 ?? '') . '</div>
            <div class="col border-end p-2 bg-light d-flex align-items-center">Suburb</div>
            <div class="col border-end p-2 d-flex align-items-center">' . esc_html($learner->suburb ?? '') . '</div>
            <div class="col border-end p-2 bg-light d-flex align-items-center">Province/Region</div>
            <div class="col border-end p-2 d-flex align-items-center">' . esc_html($learner->province_region_name ?? '') . '</div>
         </div>
         <!-- Row 3 -->
         <div class="row border-top border-start">
            <div class="col border-end p-2 bg-light d-flex align-items-center">Postal Code</div>
            <div class="col border-end p-2 d-flex align-items-center">' . esc_html($learner->postal_code ?? '') . '</div>
            <div class="col border-end p-2 bg-light d-flex align-items-center">Assessment Status</div>
            <div class="col border-end p-2 d-flex align-items-center">' . esc_html($learner->assessment_status ?? '') . '</div>
            <div class="col border-end p-2 bg-light d-flex align-items-center">Highest Qualification</div>
            <div class="col border-end p-2 d-flex align-items-center">' . esc_html($learner->highest_qualification ?? '') . '</div>
            <div class="col border-end p-2 bg-light d-flex align-items-center">Employer</div>
            <div class="col border-end p-2 d-flex align-items-center">' . esc_html($learner->employer_name ?? '') . '</div>
         </div>
         <!-- Row 7 -->
         <div class="row border-top border-start">
            <div class="col border-end p-2 bg-light d-flex align-items-center">Disability Status</div>
            <div class="col border-end p-2 d-flex align-items-center">' . esc_html($learner->disability_status ?? '') . '</div>
            <div class="col border-end p-2 bg-light d-flex align-items-center">Profile Updated At</div>
            <div class="col border-end p-2 d-flex align-items-center">' . esc_html($learner->updated_at ?? '') . '</div>
            <div class="col border-end p-2 bg-light d-flex align-items-center">&nbsp;</div>
            <div class="col border-end p-2 d-flex align-items-center">&nbsp;</div>
            <div class="col border-end p-2 bg-light d-flex align-items-center">&nbsp;</div>
            <div class="col border-end p-2 d-flex align-items-center">&nbsp;</div>
         </div>
      </div>
      <!-- END Section Learner Info. -->';
print $return;
