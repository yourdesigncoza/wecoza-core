/**
 * WeCoza Dev Toolbar — Class Form Filler
 *
 * Fills the class capture form (#classes-form).
 * Most complex form — handles async Client → Site cascade and Class Type → Subject cascade.
 * Focuses on fields needed for initial class creation.
 */
(function () {
    'use strict';

    var Gen = window.WeCozaDevGen;
    var D = window.WeCozaDevData;
    var Fillers = window.WeCozaDevFillers = window.WeCozaDevFillers || {};

    Fillers['class'] = {
        fill: async function (form) {
            // ── Client Details ──────────────────────────────
            // Client dropdown → triggers AJAX to filter site options
            Gen.pickRandomOption('#client_id');
            await Gen.delay(500);

            // Wait for site dropdown to update (filtered by jQuery change handler)
            await Gen.waitForAjaxIdle(5000);
            await Gen.delay(300);

            // Pick a site that belongs to the selected client
            Gen.pickRandomOption('#site_id');
            await Gen.delay(300);

            // ── Class Details ───────────────────────────────
            // Class Type → triggers loading of Class Subject options
            Gen.pickRandomOption('#class_type');
            await Gen.delay(500);

            // Wait for class_subject to be populated and enabled
            await Gen.waitForOptions('#class_subject', 5000);
            var subjectEl = document.querySelector('#class_subject');
            if (subjectEl && subjectEl.disabled) {
                subjectEl.disabled = false;
            }
            Gen.pickRandomOption('#class_subject');
            await Gen.delay(300);

            // Class duration is auto-calculated (readonly) — skip

            // Class start date
            Gen.setFieldValue('#class_start_date', Gen.formatDate(Gen.generateRecentDate(2)));
            // Trigger change to sync schedule_start_date
            var startDateEl = document.querySelector('#class_start_date');
            if (startDateEl && window.jQuery) {
                jQuery('#class_start_date').trigger('change');
            }
            await Gen.delay(300);

            // ── Schedule ────────────────────────────────────
            Gen.setSelectValue('#schedule_pattern', Gen.pickRandom(D.schedulePatterns));
            var patternEl = document.querySelector('#schedule_pattern');
            if (patternEl && window.jQuery) {
                jQuery('#schedule_pattern').trigger('change');
            }
            await Gen.delay(500);

            // Select random weekdays (for weekly/biweekly patterns)
            var patternVal = patternEl ? patternEl.value : 'weekly';
            if (patternVal === 'weekly' || patternVal === 'biweekly' || patternVal === 'custom') {
                var numDays = Gen.randomInt(1, 3);
                var availableDays = D.weekdays.slice();
                for (var i = 0; i < numDays && availableDays.length; i++) {
                    var dayIdx = Math.floor(Math.random() * availableDays.length);
                    var day = availableDays.splice(dayIdx, 1)[0];
                    var checkbox = document.querySelector('#schedule_day_' + day.toLowerCase());
                    if (checkbox) {
                        Gen.setCheckbox(checkbox, true);
                    }
                }
                // Trigger day checkbox change to update time controls
                await Gen.delay(300);
                var anyChecked = document.querySelector('.schedule-day-checkbox:checked');
                if (anyChecked && window.jQuery) {
                    jQuery(anyChecked).trigger('change');
                }
                await Gen.delay(300);
            }

            if (patternVal === 'monthly') {
                Gen.pickRandomOption('#schedule_day_of_month');
            }

            // ── Time inputs (per-day or single) ─────────────
            // Fill any visible time inputs
            var startTimes = form.querySelectorAll('input[name*="start_time"], .day-start-time');
            var endTimes = form.querySelectorAll('input[name*="end_time"], .day-end-time');
            var hours = ['07:00', '07:30', '08:00', '08:30', '09:00'];
            var endHours = ['14:00', '14:30', '15:00', '15:30', '16:00'];

            startTimes.forEach(function (el) {
                if (el.offsetParent !== null) { // visible
                    Gen.setFieldValue(el, Gen.pickRandom(hours));
                }
            });
            endTimes.forEach(function (el) {
                if (el.offsetParent !== null) {
                    Gen.setFieldValue(el, Gen.pickRandom(endHours));
                }
            });

            console.log('[DevToolbar] Class form filled (basic fields)');
            console.log('[DevToolbar] Note: Post-creation fields (learners, QA, notes, agents) require manual entry after class is saved.');
        }
    };
})();
