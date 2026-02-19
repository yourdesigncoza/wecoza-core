-- =============================================================================
-- Seed: Learner Progression Test Data
-- Tables: learner_lp_tracking (12 rows), learner_hours_log (~36 rows)
--
-- Prerequisites: 7 learners (IDs 1-7), class_type_subjects, classes 5 & 6
-- Run manually against PostgreSQL AFTER migration_products_to_subjects.sql.
-- =============================================================================

BEGIN;

-- -------------------------------------------------------------------------
-- Clear existing data (safe for dev/test only)
-- -------------------------------------------------------------------------
DELETE FROM learner_hours_log;
DELETE FROM learner_lp_tracking;

-- -------------------------------------------------------------------------
-- learner_lp_tracking — 12 progressions across 7 learners
-- -------------------------------------------------------------------------
-- Real subjects from class_type_subjects:
--   1=COMM (120h), 2=NUM (120h), 3=COMM_NUM (240h), 4=CL4 (120h),
--   5=NL4 (120h), 6=LO4 (90h), 8=EMS4 (94h), 16=BA2LP1 (72h)

INSERT INTO learner_lp_tracking
    (tracking_id, learner_id, class_type_subject_id, class_id,
     hours_trained, hours_present, hours_absent,
     status, start_date, completion_date,
     marked_complete_by, marked_complete_date, notes,
     created_at, updated_at)
VALUES
-- #1  Learner 1 / COMM (120h) / Class 5 — COMPLETED 100%
(1, 1, 1, 5,
 120.00, 120.00, 0.00,
 'completed', '2025-11-04', '2025-12-20',
 1, '2025-12-20 09:00:00', 'Completed all hours with excellent attendance.',
 '2025-11-04 08:00:00', '2025-12-20 09:00:00'),

-- #2  Learner 1 / COMM_NUM (240h) / Class 6 — IN_PROGRESS 67%
(2, 1, 3, 6,
 168.00, 160.00, 8.00,
 'in_progress', '2026-01-13', NULL,
 NULL, NULL, 'Progressing well through Communication & Numeracy.',
 '2026-01-13 08:00:00', '2026-02-14 16:00:00'),

-- #3  Learner 2 / NUM (120h) / Class 5 — IN_PROGRESS 58%
(3, 2, 2, 5,
 76.00, 70.00, 6.00,
 'in_progress', '2025-12-02', NULL,
 NULL, NULL, NULL,
 '2025-12-02 08:00:00', '2026-02-10 16:00:00'),

-- #4  Learner 2 / NL4 (120h) / No class — COMPLETED 100%
(4, 2, 5, NULL,
 120.00, 120.00, 0.00,
 'completed', '2025-10-14', '2025-11-15',
 1, '2025-11-15 10:00:00', 'Self-study completion, no class assignment.',
 '2025-10-14 08:00:00', '2025-11-15 10:00:00'),

-- #5  Learner 3 / COMM (120h) / Class 5 — ON_HOLD 38%
(5, 3, 1, 5,
 50.00, 45.00, 5.00,
 'on_hold', '2026-01-06', NULL,
 NULL, NULL, 'Paused due to learner personal circumstances.',
 '2026-01-06 08:00:00', '2026-02-03 14:00:00'),

-- #6  Learner 4 / CL4 (120h) / Class 6 — IN_PROGRESS 20%
(6, 4, 4, 6,
 28.00, 24.00, 4.00,
 'in_progress', '2026-02-03', NULL,
 NULL, NULL, 'Early stage, just started Communication level 4.',
 '2026-02-03 08:00:00', '2026-02-17 16:00:00'),

-- #7  Learner 4 / LO4 (90h) / No class — COMPLETED 100%
(7, 4, 6, NULL,
 90.00, 90.00, 0.00,
 'completed', '2025-09-15', '2025-11-01',
 1, '2025-11-01 11:00:00', 'Life Orientation level 4 completed independently.',
 '2025-09-15 08:00:00', '2025-11-01 11:00:00'),

-- #8  Learner 5 / NUM (120h) / Class 5 — COMPLETED 100%
(8, 5, 2, 5,
 120.00, 120.00, 0.00,
 'completed', '2025-10-07', '2026-01-10',
 1, '2026-01-10 09:30:00', 'Completed Numeracy with full attendance.',
 '2025-10-07 08:00:00', '2026-01-10 09:30:00'),

-- #9  Learner 5 / EMS4 (94h) / Class 6 — IN_PROGRESS 56%
(9, 5, 8, 6,
 56.00, 53.00, 3.00,
 'in_progress', '2026-01-20', NULL,
 NULL, NULL, 'Halfway through Economic & Management Sciences.',
 '2026-01-20 08:00:00', '2026-02-17 16:00:00'),

-- #10 Learner 6 / COMM_NUM (240h) / No class — ON_HOLD 33%
(10, 6, 3, NULL,
 88.00, 80.00, 8.00,
 'on_hold', '2026-01-13', NULL,
 NULL, NULL, 'On hold — awaiting class assignment.',
 '2026-01-13 08:00:00', '2026-02-05 10:00:00'),

-- #11 Learner 7 / COMM (120h) / Class 6 — IN_PROGRESS 75%
(11, 7, 1, 6,
 96.00, 90.00, 6.00,
 'in_progress', '2025-12-09', NULL,
 NULL, NULL, 'Near completion of Communication (separate).',
 '2025-12-09 08:00:00', '2026-02-14 16:00:00'),

-- #12 Learner 7 / BA2LP1 (72h) / Class 5 — COMPLETED 100%
(12, 7, 16, 5,
 72.00, 72.00, 0.00,
 'completed', '2025-08-19', '2025-11-22',
 1, '2025-11-22 14:00:00', 'BA2 LP1 completed successfully.',
 '2025-08-19 08:00:00', '2025-11-22 14:00:00');

-- Reset sequence to next available value
SELECT setval('learner_lp_tracking_tracking_id_seq', 12);


-- -------------------------------------------------------------------------
-- learner_hours_log — 3 entries per progression (36 rows total)
-- source CHECK: 'schedule' | 'attendance' | 'manual'
-- -------------------------------------------------------------------------

INSERT INTO learner_hours_log
    (learner_id, class_type_subject_id, class_id, tracking_id,
     log_date, hours_trained, hours_present,
     source, created_by, notes, created_at)
VALUES
-- Tracking #1: Learner 1 / COMM / completed 120h
(1, 1, 5, 1, '2025-11-11', 40.00, 40.00, 'schedule',  1, 'Week 1-2 sessions',        '2025-11-11 16:00:00'),
(1, 1, 5, 1, '2025-11-25', 40.00, 40.00, 'schedule',  1, 'Week 3-4 sessions',        '2025-11-25 16:00:00'),
(1, 1, 5, 1, '2025-12-16', 40.00, 40.00, 'attendance', 1, 'Final sessions completed', '2025-12-16 16:00:00'),

-- Tracking #2: Learner 1 / COMM_NUM / in_progress 160/240h
(1, 3, 6, 2, '2026-01-20', 56.00, 54.00, 'schedule',  1, 'First block sessions',     '2026-01-20 16:00:00'),
(1, 3, 6, 2, '2026-02-03', 56.00, 54.00, 'schedule',  1, 'Second block sessions',    '2026-02-03 16:00:00'),
(1, 3, 6, 2, '2026-02-14', 56.00, 52.00, 'attendance', 1, 'Third block sessions',     '2026-02-14 16:00:00'),

-- Tracking #3: Learner 2 / NUM / in_progress 70/120h
(2, 2, 5, 3, '2025-12-16', 28.00, 26.00, 'schedule',  1, 'December sessions',        '2025-12-16 16:00:00'),
(2, 2, 5, 3, '2026-01-13', 24.00, 22.00, 'schedule',  1, 'January sessions',         '2026-01-13 16:00:00'),
(2, 2, 5, 3, '2026-02-10', 24.00, 22.00, 'attendance', 1, 'February sessions',        '2026-02-10 16:00:00'),

-- Tracking #4: Learner 2 / NL4 / completed 120h
(2, 5, NULL, 4, '2025-10-21', 40.00, 40.00, 'manual',   1, 'Self-study block 1',      '2025-10-21 16:00:00'),
(2, 5, NULL, 4, '2025-11-04', 40.00, 40.00, 'manual',   1, 'Self-study block 2',      '2025-11-04 16:00:00'),
(2, 5, NULL, 4, '2025-11-15', 40.00, 40.00, 'manual',   1, 'Final self-study block',  '2025-11-15 16:00:00'),

-- Tracking #5: Learner 3 / COMM / on_hold 45/120h
(3, 1, 5, 5, '2026-01-13', 18.00, 16.00, 'schedule',  1, 'First week',               '2026-01-13 16:00:00'),
(3, 1, 5, 5, '2026-01-27', 16.00, 15.00, 'schedule',  1, 'Second week',              '2026-01-27 16:00:00'),
(3, 1, 5, 5, '2026-02-03', 16.00, 14.00, 'attendance', 1, 'Last session before hold', '2026-02-03 16:00:00'),

-- Tracking #6: Learner 4 / CL4 / in_progress 24/120h
(4, 4, 6, 6, '2026-02-10', 14.00, 12.00, 'schedule',  1, 'Introductory sessions',    '2026-02-10 16:00:00'),
(4, 4, 6, 6, '2026-02-17', 14.00, 12.00, 'schedule',  1, 'Week 2 sessions',          '2026-02-17 16:00:00'),

-- Tracking #7: Learner 4 / LO4 / completed 90h
(4, 6, NULL, 7, '2025-09-29', 30.00, 30.00, 'manual',   1, 'Sept block',              '2025-09-29 16:00:00'),
(4, 6, NULL, 7, '2025-10-13', 30.00, 30.00, 'manual',   1, 'Oct block',               '2025-10-13 16:00:00'),
(4, 6, NULL, 7, '2025-10-27', 30.00, 30.00, 'manual',   1, 'Final block',             '2025-10-27 16:00:00'),

-- Tracking #8: Learner 5 / NUM / completed 120h
(5, 2, 5, 8, '2025-10-21', 30.00, 30.00, 'schedule',  1, 'October sessions',         '2025-10-21 16:00:00'),
(5, 2, 5, 8, '2025-11-18', 30.00, 30.00, 'schedule',  1, 'November sessions',        '2025-11-18 16:00:00'),
(5, 2, 5, 8, '2025-12-16', 30.00, 30.00, 'schedule',  1, 'December sessions',        '2025-12-16 16:00:00'),
(5, 2, 5, 8, '2026-01-10', 30.00, 30.00, 'attendance', 1, 'Final January sessions',   '2026-01-10 16:00:00'),

-- Tracking #9: Learner 5 / EMS4 / in_progress 53/94h
(5, 8, 6, 9, '2026-01-27', 20.00, 19.00, 'schedule',  1, 'Week 1 sessions',          '2026-01-27 16:00:00'),
(5, 8, 6, 9, '2026-02-10', 20.00, 19.00, 'schedule',  1, 'Week 3 sessions',          '2026-02-10 16:00:00'),
(5, 8, 6, 9, '2026-02-17', 16.00, 15.00, 'attendance', 1, 'Week 4 sessions',          '2026-02-17 16:00:00'),

-- Tracking #10: Learner 6 / COMM_NUM / on_hold 80/240h
(6, 3, NULL, 10, '2026-01-20', 44.00, 40.00, 'manual',   1, 'Initial self-study',     '2026-01-20 16:00:00'),
(6, 3, NULL, 10, '2026-02-03', 44.00, 40.00, 'manual',   1, 'Second study block',     '2026-02-03 16:00:00'),

-- Tracking #11: Learner 7 / COMM / in_progress 90/120h
(7, 1, 6, 11, '2025-12-16', 32.00, 30.00, 'schedule',  1, 'December sessions',       '2025-12-16 16:00:00'),
(7, 1, 6, 11, '2026-01-13', 32.00, 30.00, 'schedule',  1, 'January sessions',        '2026-01-13 16:00:00'),
(7, 1, 6, 11, '2026-02-14', 32.00, 30.00, 'attendance', 1, 'February sessions',       '2026-02-14 16:00:00'),

-- Tracking #12: Learner 7 / BA2LP1 / completed 72h
(7, 16, 5, 12, '2025-09-02', 24.00, 24.00, 'schedule',  1, 'Sept sessions',           '2025-09-02 16:00:00'),
(7, 16, 5, 12, '2025-09-30', 24.00, 24.00, 'schedule',  1, 'Oct sessions',            '2025-09-30 16:00:00'),
(7, 16, 5, 12, '2025-10-28', 24.00, 24.00, 'schedule',  1, 'Nov sessions',            '2025-10-28 16:00:00');

-- Reset sequence to next available value
SELECT setval('learner_hours_log_log_id_seq', (SELECT MAX(log_id) FROM learner_hours_log));

COMMIT;

-- -------------------------------------------------------------------------
-- Verification queries (run after commit to confirm)
-- -------------------------------------------------------------------------
-- SELECT count(*) AS tracking_rows FROM learner_lp_tracking;
-- SELECT count(*) AS hours_log_rows FROM learner_hours_log;
-- SELECT status, count(*) FROM learner_lp_tracking GROUP BY status;
