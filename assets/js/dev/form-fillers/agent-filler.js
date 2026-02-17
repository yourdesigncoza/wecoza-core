/**
 * WeCoza Dev Toolbar — Agent Form Filler
 *
 * Fills the agent capture/edit form (#agents-form).
 * Static province dropdown, text address fields, no async cascading.
 */
(function () {
    'use strict';

    var Gen = window.WeCozaDevGen;
    var D = window.WeCozaDevData;
    var Fillers = window.WeCozaDevFillers = window.WeCozaDevFillers || {};

    Fillers.agent = {
        fill: async function (form) {
            var person = Gen.generatePerson();
            var addr = Gen.generateAddress();
            var banking = Gen.generateBanking(person.firstName + ' ' + person.surname);

            // ── Personal Information ───────────────────────
            Gen.pickRandomOption('#title');
            Gen.setFieldValue('#first_name', person.firstName);
            Gen.setFieldValue('#second_name', person.secondName);
            Gen.setFieldValue('#surname', person.surname);
            // Initials auto-calculated — trigger name change events
            await Gen.delay(200);

            Gen.pickRandomOption('#gender');
            Gen.pickRandomOption('#race');

            // ── ID & Contact ───────────────────────────────
            Gen.setRadioValue('id_type', 'sa_id');
            await Gen.delay(300); // Wait for conditional field toggle

            Gen.setFieldValue('#sa_id_no', person.saId);
            Gen.setFieldValue('#tel_number', person.phone);
            Gen.setFieldValue('#email_address', person.email);

            // ── SACE Details ───────────────────────────────
            Gen.setFieldValue('#sace_number', 'SACE' + Gen.randomInt(100000, 999999));
            Gen.setFieldValue('#sace_registration_date', Gen.formatDate(Gen.generateDate(1, 5)));
            Gen.setFieldValue('#sace_expiry_date', Gen.formatDate(Gen.generateFutureDate(24)));

            // ── Address (text inputs, not cascading) ───────
            Gen.setFieldValue('#address_line_1', addr.street);
            Gen.setFieldValue('#address_line_2', addr.unit);
            Gen.setFieldValue('#residential_suburb', addr.suburb);
            Gen.setFieldValue('#city_town', addr.town);
            Gen.pickRandomOption('#province_region');
            Gen.setFieldValue('#postal_code', addr.postalCode);

            // ── Preferred Working Areas ────────────────────
            Gen.pickRandomOption('#preferred_working_area_1');
            if (Math.random() > 0.3) Gen.pickRandomOption('#preferred_working_area_2');
            if (Math.random() > 0.6) Gen.pickRandomOption('#preferred_working_area_3');

            // ── Phase & Subjects ───────────────────────────
            Gen.pickRandomOption('#phase_registered');
            Gen.setFieldValue('#subjects_registered', Gen.pickRandom(D.subjects) + ', ' + Gen.pickRandom(D.subjects));
            Gen.setFieldValue('#highest_qualification', Gen.pickRandom(D.qualifications));
            Gen.setFieldValue('#agent_training_date', Gen.formatDate(Gen.generateRecentDate(6)));

            // ── Quantum Assessments ────────────────────────
            Gen.setFieldValue('#quantum_assessment', String(Gen.randomInt(40, 95)));
            Gen.setFieldValue('#quantum_maths_score', String(Gen.randomInt(30, 90)));
            Gen.setFieldValue('#quantum_science_score', String(Gen.randomInt(30, 90)));

            // ── Legal ──────────────────────────────────────
            Gen.setFieldValue('#criminal_record_date', Gen.formatDate(Gen.generateRecentDate(12)));
            Gen.setFileInput('#criminal_record_file', 'criminal_record_' + person.surname.toLowerCase() + '.pdf');

            // ── Agreement ──────────────────────────────────
            Gen.setFieldValue('#signed_agreement_date', Gen.formatDate(Gen.generateRecentDate(3)));
            Gen.setFileInput('#signed_agreement_file', 'signed_agreement_' + person.surname.toLowerCase() + '.pdf');

            // ── Banking Details ────────────────────────────
            Gen.setFieldValue('#bank_name', banking.bankName);
            Gen.setFieldValue('#account_holder', banking.accountHolder);
            Gen.setFieldValue('#account_number', banking.accountNumber);
            Gen.setFieldValue('#branch_code', banking.branchCode);
            Gen.pickRandomOption('#account_type');

            console.log('[DevToolbar] Agent form filled');
        }
    };
})();
