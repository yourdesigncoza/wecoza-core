/**
 * WeCoza Dev Toolbar — Class Form Filler
 *
 * Fills the class capture form (#classes-form) comprehensively.
 * Handles async cascades: Client → Site, Class Type → Subject.
 * Fills all sections: details, schedule, times, exception dates, event dates,
 * stop/restart dates, SETA/exam, agents, learner selection with varied
 * levels/statuses, and exam learners.
 */
(function () {
    'use strict';

    var Gen = window.WeCozaDevGen;
    var D = window.WeCozaDevData;
    var Fillers = window.WeCozaDevFillers = window.WeCozaDevFillers || {};

    // ── Local data pools for class-specific fields ──────────────
    var classData = {
        eventTypes: ['Deliveries', 'Collections', 'Exams', 'Mock Exams', 'SBA Collection', 'Learner Packs', 'QA Visit', 'SETA Exit'],
        eventDescriptions: {
            'Deliveries': ['Deliver Initial Material', 'Deliver Supplementary Books', 'Deliver Assessment Packs'],
            'Collections': ['Collect Completed Portfolios', 'Collect Assessment Reports', 'Collect Learner Materials'],
            'Exams': ['Final Examination', 'Mid-term Assessment', 'Written Exam'],
            'Mock Exams': ['Mock Exam Round 1', 'Mock Exam Round 2', 'Practice Assessment'],
            'SBA Collection': ['SBA Portfolio Collection', 'SBA Evidence Collection'],
            'Learner Packs': ['Distribute Learner Packs', 'Learner Starter Kits'],
            'QA Visit': ['QA Site Visit', 'Quality Assurance Check', 'Compliance Audit'],
            'SETA Exit': ['SETA Exit Review', 'Final SETA Verification']
        },
        exceptionReasons: ['Client Cancelled', 'Agent Absent', 'Public Holiday', 'Other'],
        timeSlots: {
            morning: { start: '08:00', end: '12:00' },
            afternoon: { start: '13:00', end: '17:00' },
            fullDay: { start: '08:00', end: '16:00' }
        },
        // Learner levels commonly used — mix of AET, soft skills, and progression
        learnerLevels: ['COMM', 'NUM', 'COMM_NUM', 'CL4', 'NL4', 'RLC', 'RLN', 'WALK', 'HEXA', 'RUN', 'IPC', 'EQ', 'TM', 'SS', 'BA2LP1', 'BA3LP1', 'BA4LP1'],
        learnerStatuses: [
            'CIC - Currently in Class',
            'RBE - Removed by Employer',
            'DRO - Drop Out'
        ]
    };

    // ── Helper: pick a sensible time pair ─────────────────────
    function pickTimePair(startEl, endEl) {
        if (!startEl || !endEl) return;
        var slot = Gen.pickRandom(['morning', 'afternoon', 'fullDay']);
        var times = classData.timeSlots[slot];
        Gen.setSelectValue(startEl, times.start);
        Gen.setSelectValue(endEl, times.end);
    }

    // ── Helper: generate a future date offset from a base ─────
    function addDays(baseDate, days) {
        var d = new Date(baseDate);
        d.setDate(d.getDate() + days);
        return d;
    }

    // ── Helper: click a button by selector ─────────────────────
    function clickButton(selector) {
        var btn = document.querySelector(selector);
        if (btn) {
            btn.click();
            return true;
        }
        return false;
    }

    Fillers['class'] = {
        fill: async function (form) {
            console.log('[DevToolbar] Starting comprehensive class form fill...');

            // ── 1. Client Details ─────────────────────────────────
            Gen.pickRandomOption('#client_id');
            await Gen.delay(500);
            await Gen.waitForAjaxIdle(5000);
            await Gen.delay(300);

            // Pick a visible site belonging to the selected client
            var siteEl = document.querySelector('#site_id');
            if (siteEl) {
                var visibleOpts = Array.from(siteEl.options).filter(function (o) {
                    return o.value !== '' && !o.disabled && !o.parentElement.hidden && o.style.display !== 'none';
                });
                if (visibleOpts.length) {
                    siteEl.value = visibleOpts[Math.floor(Math.random() * visibleOpts.length)].value;
                    siteEl.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    Gen.pickRandomOption('#site_id');
                }
            }
            await Gen.delay(300);

            // ── 2. Class Details ──────────────────────────────────
            Gen.pickRandomOption('#class_type');
            await Gen.delay(500);

            // Check if this is a progression type (subject auto-set)
            var classTypeEl = document.querySelector('#class_type');
            var isProgression = classTypeEl && ['GETC', 'BA2', 'BA3', 'BA4'].indexOf(classTypeEl.value) !== -1;

            if (!isProgression) {
                // Wait for subject options to load via AJAX
                await Gen.waitForOptions('#class_subject', 8000);
                var subjectEl = document.querySelector('#class_subject');
                if (subjectEl && subjectEl.disabled) {
                    subjectEl.disabled = false;
                }
                Gen.pickRandomOption('#class_subject');
            }
            await Gen.delay(500);
            await Gen.waitForAjaxIdle(3000);

            // Class start date: 1-4 weeks in the future for realistic demo
            var startDate = addDays(new Date(), Gen.randomInt(7, 28));
            // Avoid weekends
            while (startDate.getDay() === 0 || startDate.getDay() === 6) {
                startDate = addDays(startDate, 1);
            }
            Gen.setFieldValue('#class_start_date', Gen.formatDate(startDate));
            if (window.jQuery) {
                jQuery('#class_start_date').trigger('change');
            }
            await Gen.delay(500);

            // ── 3. Schedule ───────────────────────────────────────
            // Prefer weekly (most common real-world pattern)
            var pattern = Gen.pickRandom(['weekly', 'weekly', 'weekly', 'biweekly', 'monthly']);
            Gen.setSelectValue('#schedule_pattern', pattern);
            if (window.jQuery) {
                jQuery('#schedule_pattern').trigger('change');
            }
            await Gen.delay(500);

            if (pattern === 'weekly' || pattern === 'biweekly') {
                // Select 2-3 weekdays (realistic class schedule)
                var numDays = Gen.randomInt(2, 3);
                var availableDays = D.weekdays.slice();
                for (var i = 0; i < numDays && availableDays.length; i++) {
                    var dayIdx = Math.floor(Math.random() * availableDays.length);
                    var day = availableDays.splice(dayIdx, 1)[0];
                    var checkbox = document.querySelector('#schedule_day_' + day.toLowerCase());
                    if (checkbox && !checkbox.checked) {
                        checkbox.checked = true;
                        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                        if (window.jQuery) jQuery(checkbox).trigger('change');
                    }
                }
                await Gen.delay(500);

                // Trigger change on each checked day to create time controls
                var checkedBoxes = form.querySelectorAll('.schedule-day-checkbox:checked');
                checkedBoxes.forEach(function (cb) {
                    if (window.jQuery) jQuery(cb).trigger('change');
                });
                await Gen.delay(500);
            }

            if (pattern === 'monthly') {
                // Pick a realistic day of month (1-28 to avoid edge cases)
                var dayOfMonth = Gen.randomInt(1, 28);
                Gen.setSelectValue('#schedule_day_of_month', String(dayOfMonth));
                await Gen.delay(300);
            }

            // ── 4. Per-day times (sensible pairs) ─────────────────
            var startTimes = form.querySelectorAll('.day-start-time');
            var endTimes = form.querySelectorAll('.day-end-time');
            for (var t = 0; t < startTimes.length; t++) {
                if (startTimes[t].offsetParent !== null) {
                    pickTimePair(startTimes[t], endTimes[t]);
                }
            }
            await Gen.delay(300);

            // ── 5. Exception Dates ────────────────────────────────
            // Add 2 exception dates with varied reasons
            var exceptionScenarios = [
                { daysOffset: Gen.randomInt(14, 30), reason: 'Client Cancelled' },
                { daysOffset: Gen.randomInt(35, 60), reason: 'Agent Absent' }
            ];

            for (var exc = 0; exc < exceptionScenarios.length; exc++) {
                clickButton('#add-exception-date-btn');
                await Gen.delay(300);

                var excRows = form.querySelectorAll('#exception-dates-container .exception-date-row:not(.d-none):not([id])');
                var lastExcRow = excRows[excRows.length - 1];
                if (lastExcRow) {
                    var excDateInput = lastExcRow.querySelector('input[name="exception_dates[]"]');
                    var excReasonSelect = lastExcRow.querySelector('select[name="exception_reasons[]"]');
                    var excDate = addDays(startDate, exceptionScenarios[exc].daysOffset);
                    // Skip to a weekday
                    while (excDate.getDay() === 0 || excDate.getDay() === 6) {
                        excDate = addDays(excDate, 1);
                    }
                    if (excDateInput) Gen.setFieldValue(excDateInput, Gen.formatDate(excDate));
                    if (excReasonSelect) Gen.setSelectValue(excReasonSelect, exceptionScenarios[exc].reason);
                }
            }
            console.log('[DevToolbar] Added 2 exception dates');
            await Gen.delay(300);

            // ── 6. Stop/Restart Dates ─────────────────────────────
            // Add 2 stop/restart periods to test the functionality
            var stopRestartScenarios = [
                { stopOffset: Gen.randomInt(20, 35), restartOffset: Gen.randomInt(40, 50) },
                { stopOffset: Gen.randomInt(55, 70), restartOffset: Gen.randomInt(75, 85) }
            ];

            for (var sr = 0; sr < stopRestartScenarios.length; sr++) {
                clickButton('#add-date-history-btn');
                await Gen.delay(300);

                var historyRows = form.querySelectorAll('#date-history-container .date-history-row:not(.d-none):not([id])');
                var lastHistRow = historyRows[historyRows.length - 1];
                if (lastHistRow) {
                    var stopInput = lastHistRow.querySelector('input[name="stop_dates[]"]');
                    var restartInput = lastHistRow.querySelector('input[name="restart_dates[]"]');

                    var stopDate = addDays(startDate, stopRestartScenarios[sr].stopOffset);
                    var restartDate = addDays(startDate, stopRestartScenarios[sr].restartOffset);

                    // Skip weekends
                    while (stopDate.getDay() === 0 || stopDate.getDay() === 6) {
                        stopDate = addDays(stopDate, 1);
                    }
                    while (restartDate.getDay() === 0 || restartDate.getDay() === 6) {
                        restartDate = addDays(restartDate, 1);
                    }

                    if (stopInput) Gen.setFieldValue(stopInput, Gen.formatDate(stopDate));
                    if (restartInput) Gen.setFieldValue(restartInput, Gen.formatDate(restartDate));
                }
            }
            console.log('[DevToolbar] Added 2 stop/restart date pairs');
            await Gen.delay(300);

            // ── 7. Calculate End Date ──────────────────────────────
            clickButton('#calculate_schedule_end_date-btn');
            await Gen.delay(1000);
            await Gen.waitForAjaxIdle(3000);

            // ── 8. Event Dates ─────────────────────────────────────
            // Always add a Deliveries event (form warns if missing)
            // Always add Exams + Mock Exams since exam_class is set to Yes
            var eventsToAdd = [
                { type: 'Deliveries', daysOffset: 0 },
                { type: 'QA Visit', daysOffset: Gen.randomInt(30, 60) },
                { type: 'Mock Exams', daysOffset: Gen.randomInt(60, 90) },
                { type: 'Exams', daysOffset: Gen.randomInt(20, 30) }
            ];

            for (var e = 0; e < eventsToAdd.length; e++) {
                clickButton('#add-event-date-btn');
                await Gen.delay(300);

                var eventRows = form.querySelectorAll('#event-dates-container .event-date-row:not(.d-none):not([id])');
                var lastRow = eventRows[eventRows.length - 1];
                if (lastRow) {
                    var typeSelect = lastRow.querySelector('select[name="event_types[]"]');
                    var descInput = lastRow.querySelector('input[name="event_descriptions[]"]');
                    var dateInput = lastRow.querySelector('input[name="event_dates_input[]"]');
                    var statusSelect = lastRow.querySelector('select[name="event_statuses[]"]');

                    var evType = eventsToAdd[e].type;
                    if (typeSelect) Gen.setSelectValue(typeSelect, evType);

                    var descriptions = classData.eventDescriptions[evType] || ['Event'];
                    if (descInput) Gen.setFieldValue(descInput, Gen.pickRandom(descriptions));

                    var eventDate = addDays(startDate, eventsToAdd[e].daysOffset);
                    if (dateInput) Gen.setFieldValue(dateInput, Gen.formatDate(eventDate));

                    if (statusSelect) Gen.setSelectValue(statusSelect, 'Pending');
                }
            }
            await Gen.delay(300);

            // ── 9. SETA Funding ────────────────────────────────────
            var setaFunded = Gen.pickRandom(['Yes', 'No', 'No']); // 2/3 chance No
            Gen.setSelectValue('#seta_funded', setaFunded);
            if (window.jQuery) jQuery('#seta_funded').trigger('change');
            await Gen.delay(300);

            if (setaFunded === 'Yes') {
                Gen.pickRandomOption('#seta_id');
            }

            // ── 10. Exam Class (always Yes to test exam learners) ──
            Gen.setSelectValue('#exam_class', 'Yes');
            if (window.jQuery) jQuery('#exam_class').trigger('change');
            await Gen.delay(500);

            var examTypes = ['Written Assessment', 'Practical Assessment', 'Oral Assessment', 'Portfolio Assessment'];
            Gen.setFieldValue('#exam_type', Gen.pickRandom(examTypes));

            // ── 11. Agents ──────────────────────────────────────────
            Gen.pickRandomOption('#initial_class_agent');

            // Agent start date syncs from class start date, but set explicitly
            var agentStartEl = document.querySelector('#initial_agent_start_date');
            if (agentStartEl) {
                Gen.setFieldValue('#initial_agent_start_date', Gen.formatDate(startDate));
            }

            Gen.pickRandomOption('#project_supervisor');
            await Gen.delay(300);

            // Add 1 backup agent
            if (clickButton('#add-backup-agent-btn')) {
                await Gen.delay(300);
                var backupRows = form.querySelectorAll('.backup-agent-row:not(.d-none):not([id*="template"])');
                var lastBackup = backupRows[backupRows.length - 1];
                if (lastBackup) {
                    var backupSelect = lastBackup.querySelector('select[name="backup_agent_ids[]"]');
                    var backupDate = lastBackup.querySelector('input[name="backup_agent_dates[]"]');
                    if (backupSelect) Gen.pickRandomOption(backupSelect);
                    if (backupDate) Gen.setFieldValue(backupDate, Gen.formatDate(addDays(startDate, Gen.randomInt(7, 30))));
                }
            }
            await Gen.delay(300);

            // ── 12. Learner Selection ──────────────────────────────
            var selectionTable = window.learnerSelectionTable;
            if (selectionTable && selectionTable.allLearners && selectionTable.allLearners.length > 0) {
                // Select 5-10 random learners for a realistic class
                var numLearners = Math.min(Gen.randomInt(5, 10), selectionTable.allLearners.length);
                var available = selectionTable.allLearners.filter(function (l) {
                    return !selectionTable.assignedLearners.has(l.id);
                });
                var shuffled = available.slice().sort(function () { return Math.random() - 0.5; });
                var toSelect = shuffled.slice(0, numLearners);

                selectionTable.selectedLearners.clear();
                toSelect.forEach(function (learner) {
                    selectionTable.selectedLearners.add(learner.id);
                });
                selectionTable.render();
                await Gen.delay(200);

                // Click "Add Selected Learners" button
                clickButton('#add-selected-learners-btn');
                await Gen.delay(800);

                // Wait for user to handle LP collision modal if it appeared
                var collisionModal = document.querySelector('#lpCollisionWarningModal');
                if (collisionModal) {
                    console.log('[DevToolbar] LP collision modal detected — waiting for user...');
                    await new Promise(function (resolve) {
                        var timeout = 300000; // 5 min
                        var elapsed = 0;
                        var interval = setInterval(function () {
                            elapsed += 500;
                            if (!document.getElementById('lpCollisionWarningModal') || elapsed >= timeout) {
                                clearInterval(interval);
                                resolve();
                            }
                        }, 500);
                    });
                    await Gen.delay(500);
                }

                // ── 12a. Randomize learner levels & statuses ──────
                // Make it creative: mix of levels and 1-2 non-CIC statuses
                var classLearnerRows = form.querySelectorAll('#class-learners-tbody tr[data-learner-id]');
                var statusMix = classLearnerRows.length;
                // Decide which learners get non-default statuses (1-2 of them)
                var nonDefaultCount = Math.min(Gen.randomInt(1, 2), statusMix);
                var nonDefaultIndices = [];
                while (nonDefaultIndices.length < nonDefaultCount) {
                    var randIdx = Gen.randomInt(0, statusMix - 1);
                    if (nonDefaultIndices.indexOf(randIdx) === -1) {
                        nonDefaultIndices.push(randIdx);
                    }
                }

                classLearnerRows.forEach(function (row, idx) {
                    // Set a random level/module
                    var levelSelect = row.querySelector('.learner-level-select');
                    if (levelSelect) {
                        var randomLevel = Gen.pickRandom(classData.learnerLevels);
                        levelSelect.value = randomLevel;
                        levelSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }

                    // Set status — mostly CIC, but 1-2 get creative statuses
                    var statusSelect = row.querySelector('.learner-status-select');
                    if (statusSelect) {
                        if (nonDefaultIndices.indexOf(idx) !== -1) {
                            // Pick RBE or DRO for testing
                            var altStatus = Gen.pickRandom([
                                'RBE - Removed by Employer',
                                'DRO - Drop Out'
                            ]);
                            statusSelect.value = altStatus;
                        } else {
                            statusSelect.value = 'CIC - Currently in Class';
                        }
                        statusSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });

                // Update the hidden learners data field
                if (selectionTable.updateLearnersDataField) {
                    selectionTable.updateLearnersDataField();
                }
                console.log('[DevToolbar] Set varied levels/statuses on ' + classLearnerRows.length + ' learners (' + nonDefaultCount + ' with non-CIC status)');
                await Gen.delay(300);

                // ── 12b. Add Exam Learners ─────────────────────────
                // Sync exam learner options from class learners
                if (typeof window.classes_sync_exam_learner_options === 'function') {
                    window.classes_sync_exam_learner_options();
                }
                await Gen.delay(300);

                var examSelect = document.querySelector('#exam_learner_select');
                if (examSelect) {
                    var examOpts = Array.from(examSelect.options).filter(function (o) { return o.value !== ''; });
                    // Select 2-3 learners for exams
                    var numExam = Math.min(Gen.randomInt(2, 3), examOpts.length);
                    for (var ex = 0; ex < numExam; ex++) {
                        examOpts[ex].selected = true;
                    }
                    examSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    if (window.jQuery) jQuery(examSelect).trigger('change');
                    await Gen.delay(200);

                    // Click "Add Selected Exam Learners" button
                    clickButton('#add-selected-exam-learners-btn');
                    await Gen.delay(500);

                    // Now randomize exam learner levels and statuses
                    var examLearnerRows = form.querySelectorAll('#exam-learners-tbody tr');
                    examLearnerRows.forEach(function (row, idx) {
                        var examLevelSelect = row.querySelector('.learner-level-select');
                        if (examLevelSelect) {
                            var examLevel = Gen.pickRandom(classData.learnerLevels);
                            examLevelSelect.value = examLevel;
                            examLevelSelect.dispatchEvent(new Event('change', { bubbles: true }));
                            if (window.jQuery) jQuery(examLevelSelect).trigger('change');
                        }

                        var examStatusSelect = row.querySelector('.exam-learner-status-select');
                        if (examStatusSelect) {
                            // First exam learner CIC, others get mixed statuses
                            if (idx === 0) {
                                examStatusSelect.value = 'CIC - Currently in Class';
                            } else {
                                examStatusSelect.value = Gen.pickRandom(classData.learnerStatuses);
                            }
                            examStatusSelect.dispatchEvent(new Event('change', { bubbles: true }));
                            if (window.jQuery) jQuery(examStatusSelect).trigger('change');
                        }
                    });
                    console.log('[DevToolbar] Added ' + examLearnerRows.length + ' exam learners with varied levels/statuses');
                }
                await Gen.delay(300);
            } else {
                console.log('[DevToolbar] No learner selection table found or no learners available');
            }

            // ── 13. Final end date recalculation ──────────────────
            clickButton('#calculate_schedule_end_date-btn');
            await Gen.delay(800);

            console.log('[DevToolbar] ✓ Class form filled comprehensively');
            console.log('[DevToolbar] Sections: Client, Type/Subject, Schedule, Times, Exceptions(2), Stop/Restart(2), Events(4), SETA, Exam, Agents, Learners(varied), Exam Learners(varied)');
        }
    };
})();
