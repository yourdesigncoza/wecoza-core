/**
 * WeCoza Dev Toolbar — Learner Form Filler
 *
 * Fills the learner capture form (#learners-form).
 * Must wait for AJAX dropdown data (fetch_learners_dropdown_data) before picking values.
 */
(function () {
    'use strict';

    var Gen = window.WeCozaDevGen;
    var D = window.WeCozaDevData;
    var Fillers = window.WeCozaDevFillers = window.WeCozaDevFillers || {};

    Fillers.learner = {
        fill: async function (form) {
            var person = Gen.generatePerson();
            var addr = Gen.generateAddress();

            // Wait for AJAX dropdown data to load (cities, provinces, qualifications, etc.)
            await Gen.waitForAjaxIdle(8000);

            // ── Personal Info ──────────────────────────────
            Gen.pickRandomOption('#title');
            Gen.setFieldValue('#first_name', person.firstName);
            Gen.setFieldValue('#second_name', person.secondName);
            Gen.setFieldValue('#surname', person.surname);
            // Initials are auto-generated (readonly) — trigger name change
            await Gen.delay(200);

            Gen.setFieldValue('#tel_number', person.phone);
            Gen.setFieldValue('#alternative_tel_number', person.altPhone);
            Gen.setFieldValue('#email_address', person.email);

            // ── ID, Race & Gender ──────────────────────────
            Gen.setRadioValue('id_type', 'sa_id');
            await Gen.delay(300); // Wait for conditional field to appear

            Gen.setFieldValue('#sa_id_no', person.saId);
            Gen.pickRandomOption('#race');
            Gen.pickRandomOption('#gender');

            // ── Address ────────────────────────────────────
            Gen.setFieldValue('#address_line_1', addr.street);
            Gen.setFieldValue('#address_line_2', addr.unit);

            // AJAX-loaded dropdowns — wait for options to be populated
            await Gen.waitForOptions('#city_town_id', 5000);
            Gen.pickRandomOption('#city_town_id');

            await Gen.waitForOptions('#province_region_id', 5000);
            Gen.pickRandomOption('#province_region_id');

            Gen.setFieldValue('#postal_code', addr.postalCode);

            // ── Educational & Disability ───────────────────
            await Gen.waitForOptions('#highest_qualification', 5000);
            Gen.pickRandomOption('#highest_qualification');

            Gen.pickRandomOption('#disability_status');

            // ── Assessment Report Upload ─────────────────────
            Gen.setFileInput('#scanned_portfolio', 'assessment_report_' + person.surname.toLowerCase() + '.pdf');

            // ── Assessment Details ─────────────────────────
            Gen.setSelectValue('#assessment_status', 'Assessed');
            // Trigger change to show conditional fields
            var assessEl = document.querySelector('#assessment_status');
            if (assessEl && window.jQuery) {
                jQuery(assessEl).trigger('change');
            }
            await Gen.delay(500);

            // Fill conditional assessment fields if visible
            await Gen.waitForOptions('#communication_level', 3000);
            Gen.pickRandomOption('#communication_level');

            await Gen.waitForOptions('#numeracy_level', 3000);
            Gen.pickRandomOption('#numeracy_level');

            Gen.setFieldValue('#placement_assessment_date', Gen.formatDate(Gen.generateRecentDate(3)));

            // ── Employment ─────────────────────────────────
            Gen.setSelectValue('#employment_status', Gen.pickRandom(D.employmentStatuses));
            var empEl = document.querySelector('#employment_status');
            if (empEl && window.jQuery) {
                jQuery(empEl).trigger('change');
            }
            await Gen.delay(500);

            // If employed, fill employer (conditional field)
            if (empEl && empEl.value === '1') {
                await Gen.waitForOptions('#employer_id', 3000);
                Gen.pickRandomOption('#employer_id');
            }

            // ── Sponsors ──────────────────────────────────
            var addSponsorBtn = document.querySelector('#add_sponsor_btn');
            if (addSponsorBtn && window.jQuery) {
                var sponsorCount = Gen.pickRandom([1, 1, 2]); // 1 or 2 sponsors
                for (var s = 0; s < sponsorCount; s++) {
                    jQuery(addSponsorBtn).trigger('click');
                    await Gen.delay(300);
                }
                // Pick random values in each sponsor dropdown
                var sponsorSelects = document.querySelectorAll('#sponsor_container .sponsor-select');
                var usedValues = [];
                sponsorSelects.forEach(function (sel) {
                    var options = Array.from(sel.options).filter(function (o) {
                        return o.value && usedValues.indexOf(o.value) === -1;
                    });
                    if (options.length) {
                        var pick = options[Math.floor(Math.random() * options.length)];
                        sel.value = pick.value;
                        usedValues.push(pick.value);
                        jQuery(sel).trigger('change');
                    }
                });
            }

            console.log('[DevToolbar] Learner form filled');
        }
    };
})();
