<?php
declare(strict_types=1);

/**
 * WeCoza Core - Class Types Controller
 *
 * Controller for handling class types and durations.
 * Reads from PostgreSQL class_types / class_type_subjects tables.
 * Migrated from wecoza-classes-plugin.
 *
 * @package WeCoza\Classes\Controllers
 * @since 1.0.0
 */

namespace WeCoza\Classes\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

class ClassTypesController
{
    private const CACHE_TTL = 2 * HOUR_IN_SECONDS;

    /**
     * Get all active class types.
     *
     * @return array [['id' => 'AET', 'name' => '...', 'mode' => 'own', 'progression_hours' => null], ...]
     */
    public static function getClassTypes(): array
    {
        $key = 'wecoza_class_types';
        $cached = get_transient($key);
        if ($cached !== false) {
            return apply_filters('wecoza_classes_get_class_types', $cached);
        }

        try {
            $db = wecoza_db();
            $stmt = $db->query(
                "SELECT class_type_code, class_type_name, subject_selection_mode, progression_total_hours
                 FROM public.class_types
                 WHERE is_active = TRUE
                 ORDER BY display_order"
            );

            $types = [];
            while ($row = $stmt->fetch()) {
                $types[] = [
                    'id'               => $row['class_type_code'],
                    'name'             => $row['class_type_name'],
                    'mode'             => $row['subject_selection_mode'],
                    'progression_hours'=> $row['progression_total_hours'] ? (int) $row['progression_total_hours'] : null,
                ];
            }

            set_transient($key, $types, self::CACHE_TTL);
            return apply_filters('wecoza_classes_get_class_types', $types);

        } catch (\Exception $e) {
            error_log('WeCoza Core ClassTypes: getClassTypes error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get subjects for a class type.
     *
     * - Empty $classTypeId → all subjects grouped by type code
     * - mode 'own'          → subjects for that type
     * - mode 'all_subjects' → every subject flattened
     * - mode 'progression'  → single placeholder with total hours
     *
     * @param  string $classTypeId
     * @return array
     */
    public static function getClassSubjects(string $classTypeId = ''): array
    {
        if ($classTypeId === '') {
            return self::getAllSubjectsGrouped();
        }

        $classTypeId = sanitize_text_field($classTypeId);
        if (strlen($classTypeId) > 20) {
            return [];
        }

        $key = 'wecoza_class_subjects_' . $classTypeId;
        $cached = get_transient($key);
        if ($cached !== false) {
            return apply_filters('wecoza_classes_get_subjects', $cached, $classTypeId);
        }

        try {
            $db = wecoza_db();

            $typeStmt = $db->query(
                "SELECT class_type_id, subject_selection_mode, progression_total_hours
                 FROM public.class_types
                 WHERE class_type_code = ? AND is_active = TRUE",
                [$classTypeId]
            );
            $type = $typeStmt->fetch();

            if (!$type) {
                return [];
            }

            switch ($type['subject_selection_mode']) {
                case 'progression':
                    $subjects = [[
                        'id'       => 'LP',
                        'name'     => 'Learner Progression',
                        'duration' => (int) ($type['progression_total_hours'] ?? 0),
                    ]];
                    break;

                case 'all_subjects':
                    $subjects = self::getAllSubjectsFlattened();
                    break;

                default: // 'own'
                    $subStmt = $db->query(
                        "SELECT subject_code, subject_name, subject_duration
                         FROM public.class_type_subjects
                         WHERE class_type_id = ? AND is_active = TRUE
                         ORDER BY display_order",
                        [$type['class_type_id']]
                    );
                    $subjects = [];
                    while ($row = $subStmt->fetch()) {
                        $subjects[] = [
                            'id'       => $row['subject_code'],
                            'name'     => $row['subject_name'],
                            'duration' => (int) $row['subject_duration'],
                        ];
                    }
                    break;
            }

            set_transient($key, $subjects, self::CACHE_TTL);
            return apply_filters('wecoza_classes_get_subjects', $subjects, $classTypeId);

        } catch (\Exception $e) {
            error_log('WeCoza Core ClassTypes: getClassSubjects error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get duration for a subject code.
     *
     * @param  string $subjectId
     * @return int    Duration in hours (default 120)
     */
    public static function getClassDuration(string $subjectId): int
    {
        $subjectId = sanitize_text_field($subjectId);
        if ($subjectId === '' || strlen($subjectId) > 20) {
            return 120;
        }

        try {
            $db = wecoza_db();
            $stmt = $db->query(
                "SELECT subject_duration
                 FROM public.class_type_subjects
                 WHERE subject_code = ? AND is_active = TRUE
                 LIMIT 1",
                [$subjectId]
            );
            $row = $stmt->fetch();
            return $row ? (int) $row['subject_duration'] : 120;

        } catch (\Exception $e) {
            error_log('WeCoza Core ClassTypes: getClassDuration error: ' . $e->getMessage());
            return 120;
        }
    }

    /**
     * Clear all class types/subjects transient caches.
     */
    public static function clearCache(): void
    {
        delete_transient('wecoza_class_types');

        try {
            $db = wecoza_db();
            $stmt = $db->query("SELECT class_type_code FROM public.class_types");
            while ($row = $stmt->fetch()) {
                delete_transient('wecoza_class_subjects_' . $row['class_type_code']);
            }
        } catch (\Exception $e) {
            wecoza_log('Failed to query class types for cache clear, using fallback: ' . $e->getMessage(), 'warning');
            foreach (['AET','REALLL','SOFT','GETC','BA2','BA3','BA4','WALK','HEXA','RUN'] as $code) {
                delete_transient('wecoza_class_subjects_' . $code);
            }
        }
    }

    // ── private helpers ──────────────────────────────────────────

    private static function getAllSubjectsGrouped(): array
    {
        try {
            $db = wecoza_db();
            $stmt = $db->query(
                "SELECT ct.class_type_code, s.subject_code, s.subject_name, s.subject_duration
                 FROM public.class_type_subjects s
                 JOIN public.class_types ct ON s.class_type_id = ct.class_type_id
                 WHERE s.is_active = TRUE AND ct.is_active = TRUE
                 ORDER BY ct.display_order, s.display_order"
            );

            $grouped = [];
            while ($row = $stmt->fetch()) {
                $grouped[$row['class_type_code']][] = [
                    'id'       => $row['subject_code'],
                    'name'     => $row['subject_name'],
                    'duration' => (int) $row['subject_duration'],
                ];
            }
            return $grouped;

        } catch (\Exception $e) {
            error_log('WeCoza Core ClassTypes: getAllSubjectsGrouped error: ' . $e->getMessage());
            return [];
        }
    }

    private static function getAllSubjectsFlattened(): array
    {
        try {
            $db = wecoza_db();
            $stmt = $db->query(
                "SELECT subject_code, subject_name, subject_duration
                 FROM public.class_type_subjects
                 WHERE is_active = TRUE
                 ORDER BY display_order"
            );

            $subjects = [];
            while ($row = $stmt->fetch()) {
                $subjects[] = [
                    'id'       => $row['subject_code'],
                    'name'     => $row['subject_name'],
                    'duration' => (int) $row['subject_duration'],
                ];
            }
            return $subjects;

        } catch (\Exception $e) {
            error_log('WeCoza Core ClassTypes: getAllSubjectsFlattened error: ' . $e->getMessage());
            return [];
        }
    }
}
