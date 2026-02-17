/**
 * WeCoza Dev Toolbar — Location Form Filler
 *
 * Fills the location capture form (#street_address, #suburb, #town, #province, etc.)
 * Note: Submit button is hidden until duplicate check passes.
 */
(function () {
    'use strict';

    var Gen = window.WeCozaDevGen;
    var D = window.WeCozaDevData;
    var Fillers = window.WeCozaDevFillers = window.WeCozaDevFillers || {};

    Fillers.location = {
        fill: async function (form) {
            var addr = Gen.generateAddress();

            Gen.setFieldValue('#street_address', addr.street);
            Gen.setFieldValue('#suburb', addr.suburb);
            Gen.setFieldValue('#town', addr.town);
            Gen.pickRandomOption('#province');
            Gen.setFieldValue('#postal_code', addr.postalCode);
            Gen.setFieldValue('#latitude', addr.latitude);
            Gen.setFieldValue('#longitude', addr.longitude);

            // The submit button is hidden until duplicate check runs.
            // Auto-click the "Check Duplicates" button to reveal submit.
            await Gen.delay(300);
            Gen.clickElement('#check_duplicate_btn');

            // Wait for duplicate check AJAX to complete
            await Gen.waitForAjaxIdle(5000);
            await Gen.delay(500);

            console.log('[DevToolbar] Location form filled');
        },

        submit: function (form) {
            // Submit button should now be visible after duplicate check
            var submitBtn = document.querySelector('#submit_location_btn');
            if (submitBtn && !submitBtn.classList.contains('d-none')) {
                submitBtn.click();
            } else {
                // Fallback: try standard submit
                var fallback = form.querySelector('button[type="submit"]');
                if (fallback) fallback.click();
            }
        }
    };
})();
