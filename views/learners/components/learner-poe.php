<?php
    // Get uploads directory information
    $uploads_dir = wp_upload_dir();
    $uploadsUrl = $uploads_dir['baseurl'];

function formatPortfolioLinks($portfolioDetails, $uploadsUrl) {
    if (empty($portfolioDetails)) {
        return 'No portfolios uploaded';
    }

    // Split the portfolio details string into an array of individual portfolios
    $portfolios = array_filter(array_map(function($entry) {
        $parts = explode('|', $entry);
        return [
            'path' => trim($parts[0]),
            'date' => isset($parts[1]) ? trim($parts[1]) : null
        ];
    }, explode(',', $portfolioDetails)), function($portfolio) {
        return !empty($portfolio['path']);
    });

    // Sort portfolios by date in descending order
    usort($portfolios, function($a, $b) {
        if (!empty($a['date']) && !empty($b['date'])) {
            return strtotime($b['date']) - strtotime($a['date']);
        }
        return 0;
    });

    // Generate HTML output
    $html = '<div class="portfolio-links">';
    foreach ($portfolios as $index => $portfolio) {
        $uploadDate = !empty($portfolio['date']) ? 
            '<span class="text-muted ms-2">(' . date('Y-m-d', strtotime($portfolio['date'])) . ')</span>' : '';

        // Construct full URL path
        $fullPath = rtrim($uploadsUrl, '/') . '/' . ltrim($portfolio['path'], '/');

        $html .= '
            <div class="d-flex align-items-center mb-2">
                <span class="me-3">Portfolio ' . ($index + 1) . ' ' . $uploadDate . '</span>
                <a href="' . htmlspecialchars($fullPath) . '" download class="btn btn-sm btn-outline-discovery">Download</a>
            </div>';
    }
    $html .= '</div>';

    return $html;
}

$portfolioDetails = esc_html($learner->portfolio_details ?? '');
// $portfolioDetails = "portfolios/portfolio_6720752cccc1c2.73545383.pdf|2024-10-29 05:39:56.770881, portfolios/portfolio_6720752d307e01.14382765.pdf|2024-10-29 05:39:56.770881";



$accordion_copy = '<!-- Row 1 -->
               <div class="container-fluid">
                  <div class="row border-start border-top">
                     <div class="col border-end p-2 bg-light d-flex align-items-center">Level Started</div>
                     <div class="col border-end p-2 d-flex align-items-center">&nbsp;</div>
                     <div class="col border-end p-2 bg-light d-flex align-items-center">Level Completed</div>
                     <div class="col border-end p-2 d-flex align-items-center">&nbsp;</div>
                     <div class="col border-end p-2 bg-light d-flex align-items-center">Result</div>
                     <div class="col border-end p-2 d-flex align-items-center">&nbsp;</div>
                     <div class="col border-end px-2 py-1 align-items-center">
                        <div class="d-grid">
                          <button class="btn btn-sm btn-discovery" type="button">View POE</button>
                        </div>
                     </div>
                     <div class="col border-end px-2 py-1 align-items-center">
                        <div class="d-grid">
                           <button class="btn btn-sm btn-outline-discovery" type="button">View SOR</button>
                        </div>
                     </div>
                  </div>
                  <!-- Row 2 -->
                  <div class="row border-start border-top">
                     <div class="col border-end p-2 bg-light d-flex align-items-center">POE PDF</div>
                     <div class="col border-end px-2 py-1 align-items-center">
                        <div class="d-grid">
                           <button class="btn btn-sm btn-discovery" type="button">View POE</button>
                        </div>
                     </div>
                     <div class="col border-end p-2 bg-light d-flex align-items-center">Batch Number</div>
                     <div class="col border-end p-2 d-flex align-items-center">&nbsp;</div>
                     <div class="col border-end p-2 bg-light d-flex align-items-center">Received Date</div>
                     <div class="col border-end p-2 d-flex align-items-center">&nbsp;</div>
                     <div class="col border-end p-2 bg-light d-flex align-items-center">&nbsp;</div>
                     <div class="col border-end p-2 d-flex align-items-center">&nbsp;</div>
                  </div>
                  <!-- Row 3 -->
                  <div class="row border-start border-top border-bottom">
                     <div class="col border-end p-2 bg-light d-flex align-items-center">Sent for SOR</div>
                     <div class="col border-end p-2 d-flex align-items-center">&nbsp;</div>
                     <div class="col border-end p-2 bg-light d-flex align-items-center">Assesed Date</div>
                     <div class="col border-end p-2 d-flex align-items-center">&nbsp;</div>
                     <div class="col border-end p-2 bg-light d-flex align-items-center">Assessor</div>
                     <div class="col border-end p-2 d-flex align-items-center">&nbsp;</div>
                     <div class="col border-end p-2 bg-light d-flex align-items-center">&nbsp;</div>
                     <div class="col border-end p-2 d-flex align-items-center">&nbsp;</div>
                  </div>
               </div>';


$return = '<!-- Section Portfolio of Evidence -->
<div class="container-fluid gtab tab-4 border-top border-bottom mb-2 lh-1">
   <!-- Start Accordions -->
   <div class="accordion accordion-flush ml-0 mr-2" style="margin:0 -12px" id="accordionFlush">
      <div class="accordion-item">
         <h2 class="accordion-header">
            <button class="accordion-button collapsed btn btn-light border-start border-end" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseOne" aria-expanded="false" aria-controls="flush-collapseOne">ML3 | Bidvest | Class #1313 | 2023/09/26</button>
         </h2>
         <div id="flush-collapseOne" class="accordion-collapse collapse" data-bs-parent="#accordionFlush">
            <div class="accordion-body">';
$return .= $accordion_copy;
$return .= '</div>
         </div>
      </div>
      <div class="accordion-item">
         <h2 class="accordion-header">
            <button class="accordion-button collapsed btn ydcoza-text-warning border-start border-end" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseTwo" aria-expanded="false" aria-controls="flush-collapseTwo">ML2 | Anglo | Class #6478 | 2022/07/11</button>
         </h2>
         <div id="flush-collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionFlush">
            <div class="accordion-body">';
$return .= $accordion_copy;
$return .= '</div>
         </div>
      </div>
      <div class="accordion-item">
         <h2 class="accordion-header">
            <button class="accordion-button collapsed btn btn-light border-start border-end" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseThree" aria-expanded="false" aria-controls="flush-collapseThree">ML1 | Woolworths | Class #1113 |2021/11/01</button>
         </h2>
         <div id="flush-collapseThree" class="accordion-collapse collapse" data-bs-parent="#accordionFlush">
            <div class="accordion-body">';
$return .= $accordion_copy;
$return .= '</div>
         </div>
      </div>
   </div>
   <!-- END Accordions -->
   <!-- Row 7 -->
   <div class="row border-start border-top">
      <div class="col border-end p-2 bg-light d-flex align-items-center">

         <button class="btn btn-sm btn-outline-discovery" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasPortfolio" aria-controls="offcanvasPortfolio">Scanned Portfolio</button>


         <div class="offcanvas offcanvas-bottom" tabindex="-1" id="offcanvasPortfolio" aria-labelledby="offcanvasPortfolioLabel">
            <div class="container">
               <div class="offcanvas-header pb-2 pt-2 border-bottom">
                  <h5 class="offcanvas-title" id="offcanvasPortfolioLabel">Scanned Portfolio</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
               </div>
            </div>
            <div class="offcanvas-body">
               <div class="container">
                  <div class="row px-3">';


$return .= formatPortfolioLinks($portfolioDetails, $uploadsUrl);


$return .= '</div>
               </div>
            </div>
         </div>
         <div id="scanned_portfolio" class="modal fade" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
               <div class="modal-content">
                  <div class="modal-header">
                     <h6 class="modal-title">Scanned Portfolio</h6>
                     <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                  </div>
                  <div class="modal-body">
                     ${formatPortfolioLinks(scanned_portfolio, portfolio_dates)}
                  </div>
                  <div class="modal-footer">
                     <button type="button" class="btn btn-sm btn-primary edit-learner-btn" data-bs-dismiss="modal">Close</button>
                  </div>
               </div>
            </div>
         </div>
      </div>
      <div class="col border-end p-2 d-flex align-items-center">&nbsp;</div>
      <div class="col border-end p-2 bg-light d-flex align-items-center">&nbsp;</div>
      <div class="col border-end p-2 d-flex align-items-center">&nbsp;</div>
   </div>
   <!-- END Row 7 -->
</div>
<!-- END Section Portfolio of Evidence -->';
print $return;