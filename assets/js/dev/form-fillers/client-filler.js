/**
 * WeCoza Dev Toolbar — Client Form Filler
 *
 * Fills the client capture/update form (#clients-form).
 * Handles synchronous cascading: Province → Town → Suburb (pre-loaded hierarchy).
 */
(function () {
    'use strict';

    var Gen = window.WeCozaDevGen;
    var D = window.WeCozaDevData;
    var Fillers = window.WeCozaDevFillers = window.WeCozaDevFillers || {};

    Fillers.client = {
        fill: async function (form) {
            var person = Gen.generatePerson();
            var company = Gen.pickRandom(D.companyNames);

            // ── Basic Information ──────────────────────────
            Gen.setFieldValue('#client_name', company);
            var regYear = Gen.randomInt(2015, 2025);
            var regSeq = String(Gen.randomInt(100000, 999999));
            Gen.setFieldValue('#company_registration_nr', regYear + '/' + regSeq + '/07');
            Gen.setFieldValue('#site_name', company + ' Head Office');

            // ── Sub-client: leave unchecked for simplicity ─
            Gen.setCheckbox('#is_sub_client', false);

            // ── Address: Cascading Province → Town → Suburb ──
            // These are synchronous (pre-loaded hierarchy), so trigger change events sequentially
            var provinceSelect = form.querySelector('.js-province-select');
            if (provinceSelect) {
                Gen.pickRandomOption(provinceSelect);
                await Gen.delay(300); // Allow DOM to update town options

                var townSelect = form.querySelector('.js-town-select');
                if (townSelect) {
                    await Gen.delay(200);
                    Gen.pickRandomOption(townSelect);
                    await Gen.delay(300); // Allow DOM to update suburb options

                    var suburbSelect = form.querySelector('.js-suburb-select');
                    if (suburbSelect) {
                        await Gen.delay(200);
                        Gen.pickRandomOption(suburbSelect);
                    }
                }
            }

            // Wait for postal code and address to auto-populate from suburb selection
            await Gen.delay(500);

            // ── Contact Information ────────────────────────
            Gen.setFieldValue('#contact_person', person.firstName + ' ' + person.surname);
            Gen.setFieldValue('#contact_person_email', Gen.generateEmail(person.firstName, person.surname));
            Gen.setFieldValue('#contact_person_cellphone', Gen.generatePhone());
            Gen.setFieldValue('#contact_person_tel', Math.random() > 0.5 ? Gen.generatePhone() : '');
            Gen.setFieldValue('#contact_person_position', Gen.pickRandom([
                'HR Manager', 'Training Coordinator', 'Operations Director',
                'Skills Development Facilitator', 'CEO', 'CFO', 'Managing Director'
            ]));

            // ── Business Information ───────────────────────
            Gen.pickRandomOption('#seta');
            Gen.pickRandomOption('#client_status');
            Gen.setFieldValue('#financial_year_end', Gen.formatDate(Gen.generateFutureDate(12)));
            Gen.setFieldValue('#bbbee_verification_date', Gen.formatDate(Gen.generateRecentDate(6)));

            console.log('[DevToolbar] Client form filled');
        }
    };
})();
