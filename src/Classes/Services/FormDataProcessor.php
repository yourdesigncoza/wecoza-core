<?php
/**
 * WeCoza Core - Form Data Processor
 *
 * Service for processing and validating form data for class management.
 * Migrated from wecoza-classes-plugin.
 *
 * @package WeCoza\Classes\Services
 * @since 1.0.0
 */

namespace WeCoza\Classes\Services;

use WeCoza\Classes\Models\ClassModel;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class FormDataProcessor
{
    /**
     * Process form data from POST and FILES
     */
    public static function processFormData(array $data, array $files = []): array
    {
        $processed = [];

        try {
            // Basic fields
            $processed['id'] = isset($data['class_id']) && $data['class_id'] !== 'auto-generated' ? intval($data['class_id']) : null;
            $processed['client_id'] = isset($data['client_id']) && !empty($data['client_id']) ? intval($data['client_id']) : null;
            $processed['site_id'] = isset($data['site_id']) && !empty($data['site_id']) ? intval($data['site_id']) : null;
            $processed['site_address'] = isset($data['site_address']) && !is_array($data['site_address']) ? self::sanitizeText($data['site_address']) : null;
            $processed['class_type'] = isset($data['class_type']) && !is_array($data['class_type']) ? self::sanitizeText($data['class_type']) : null;
            $processed['class_subject'] = isset($data['class_subject']) && !is_array($data['class_subject']) ? self::sanitizeText($data['class_subject']) : null;
            $processed['class_code'] = isset($data['class_code']) && !is_array($data['class_code']) ? self::sanitizeText($data['class_code']) : null;
            $processed['class_duration'] = isset($data['class_duration']) && !empty($data['class_duration']) ? intval($data['class_duration']) : null;

            // Map schedule_start_date to original_start_date
            $processed['original_start_date'] = isset($data['schedule_start_date']) && !is_array($data['schedule_start_date'])
                ? self::sanitizeText($data['schedule_start_date'])
                : (isset($data['original_start_date']) && !is_array($data['original_start_date'])
                    ? self::sanitizeText($data['original_start_date'])
                    : null);

            // Handle boolean fields
            $processed['seta_funded'] = false;
            if (isset($data['seta_funded']) && !empty($data['seta_funded'])) {
                $processed['seta_funded'] = ($data['seta_funded'] === 'Yes' || $data['seta_funded'] === '1' || $data['seta_funded'] === true);
            }

            $processed['seta'] = isset($data['seta_id']) && !is_array($data['seta_id'])
                ? self::sanitizeText($data['seta_id'])
                : (isset($data['seta']) && !is_array($data['seta'])
                    ? self::sanitizeText($data['seta'])
                    : null);

            $processed['exam_class'] = false;
            if (isset($data['exam_class']) && !empty($data['exam_class'])) {
                $processed['exam_class'] = ($data['exam_class'] === 'Yes' || $data['exam_class'] === '1' || $data['exam_class'] === true);
            }

            $processed['exam_type'] = isset($data['exam_type']) && !is_array($data['exam_type']) ? self::sanitizeText($data['exam_type']) : null;
            $processed['class_agent'] = isset($data['class_agent']) && !empty($data['class_agent']) ? intval($data['class_agent']) : null;
            $processed['initial_class_agent'] = isset($data['initial_class_agent']) && !empty($data['initial_class_agent']) ? intval($data['initial_class_agent']) : null;

            // CLS-02: Initialize class_agent from initial_class_agent on create
            if (empty($processed['class_agent']) && !empty($processed['initial_class_agent'])) {
                $processed['class_agent'] = $processed['initial_class_agent'];
            }

            $processed['initial_agent_start_date'] = isset($data['initial_agent_start_date']) && !is_array($data['initial_agent_start_date']) ? self::sanitizeText($data['initial_agent_start_date']) : null;
            $processed['project_supervisor'] = isset($data['project_supervisor']) && !empty($data['project_supervisor']) ? intval($data['project_supervisor']) : null;

            // Order number
            $processed['order_nr'] = isset($data['order_nr']) && !is_array($data['order_nr']) ? self::sanitizeText($data['order_nr']) : null;

            // Array fields
            $processed['class_notes'] = isset($data['class_notes']) && is_array($data['class_notes']) ? array_map([self::class, 'sanitizeText'], $data['class_notes']) : [];

            // Process learner IDs
            $learnerIds = [];
            if (isset($data['class_learners_data']) && is_string($data['class_learners_data']) && !empty($data['class_learners_data'])) {
                $learnerData = json_decode(stripslashes($data['class_learners_data']), true);
                if (is_array($learnerData)) {
                    $learnerIds = array_filter(array_map('intval', $learnerData), fn($id) => $id > 0);
                }
            }
            $processed['learner_ids'] = $learnerIds;

            // Process exam learners
            $examLearners = [];
            if (isset($data['exam_learners']) && is_string($data['exam_learners']) && !empty($data['exam_learners'])) {
                $examLearnerData = json_decode(stripslashes($data['exam_learners']), true);
                if (is_array($examLearnerData)) {
                    $examLearners = array_filter(array_map('intval', $examLearnerData), fn($id) => $id > 0);
                }
            }
            $processed['exam_learners'] = $examLearners;

            // Process backup agents
            $backupAgents = [];
            if (isset($data['backup_agent_ids']) && is_array($data['backup_agent_ids'])) {
                $agentIds = $data['backup_agent_ids'];
                $agentDates = isset($data['backup_agent_dates']) ? $data['backup_agent_dates'] : [];

                for ($i = 0; $i < count($agentIds); $i++) {
                    if (!empty($agentIds[$i])) {
                        $backupAgents[] = [
                            'agent_id' => intval($agentIds[$i]),
                            'date' => isset($agentDates[$i]) && self::isValidDate(self::sanitizeText($agentDates[$i])) ? self::sanitizeText($agentDates[$i]) : ''
                        ];
                    }
                }
            }
            $processed['backup_agent_ids'] = $backupAgents;

            // Process agent replacements
            $agentReplacements = [];
            if (isset($data['replacement_agent_ids']) && is_array($data['replacement_agent_ids'])) {
                $replacementAgentIds = $data['replacement_agent_ids'];
                $replacementAgentDates = isset($data['replacement_agent_dates']) ? $data['replacement_agent_dates'] : [];

                for ($i = 0; $i < count($replacementAgentIds); $i++) {
                    if (!empty($replacementAgentIds[$i]) && isset($replacementAgentDates[$i]) && !empty($replacementAgentDates[$i])) {
                        $agentReplacements[] = [
                            'agent_id' => intval($replacementAgentIds[$i]),
                            'date' => $replacementAgentDates[$i]
                        ];
                    }
                }
            }
            $processed['agent_replacements'] = $agentReplacements;

            // Process schedule data
            $processed['schedule_data'] = self::processJsonField($data, 'schedule_data');

            // Process stop/restart dates
            $stopRestartDates = [];
            if (isset($data['stop_dates']) && is_array($data['stop_dates'])) {
                $stopDates = $data['stop_dates'];
                $restartDates = isset($data['restart_dates']) ? $data['restart_dates'] : [];

                for ($i = 0; $i < count($stopDates); $i++) {
                    if (!empty($stopDates[$i]) && isset($restartDates[$i]) && !empty($restartDates[$i])) {
                        $stopDate = self::sanitizeText($stopDates[$i]);
                        $restartDate = self::sanitizeText($restartDates[$i]);
                        if (self::isValidDate($stopDate) && self::isValidDate($restartDate)) {
                            $stopRestartDates[] = [
                                'stop_date' => $stopDate,
                                'restart_date' => $restartDate
                            ];
                        }
                    }
                }
            }
            $processed['stop_restart_dates'] = $stopRestartDates;

            // Process event dates
            $eventDates = [];
            $allowedStatuses = ['Pending', 'Completed', 'Cancelled'];
            if (isset($data['event_types']) && is_array($data['event_types'])) {
                $types = $data['event_types'];
                $descriptions = isset($data['event_descriptions']) ? $data['event_descriptions'] : [];
                $dates = isset($data['event_dates_input']) ? $data['event_dates_input'] : [];
                $statuses = isset($data['event_statuses']) ? $data['event_statuses'] : [];
                $notes = isset($data['event_notes']) ? $data['event_notes'] : [];
                // Extract completion metadata arrays (SYNC-04: preserve dashboard completions)
                $completedByArr = isset($data['event_completed_by']) ? $data['event_completed_by'] : [];
                $completedAtArr = isset($data['event_completed_at']) ? $data['event_completed_at'] : [];

                for ($i = 0; $i < count($types); $i++) {
                    $currentType = $types[$i] ?? '';
                    $currentDate = $dates[$i] ?? '';
                    if (!empty($currentType) && !empty($currentDate)) {
                        $status = self::sanitizeText($statuses[$i] ?? 'Pending');
                        $event = [
                            'type' => self::sanitizeText($currentType),
                            'description' => self::sanitizeText($descriptions[$i] ?? ''),
                            'date' => self::sanitizeText($currentDate),
                            'status' => in_array($status, $allowedStatuses) ? $status : 'Pending',
                            'notes' => self::sanitizeText($notes[$i] ?? '')
                        ];

                        // Preserve completion metadata if present
                        if (isset($completedByArr[$i]) && $completedByArr[$i] !== '') {
                            $event['completed_by'] = intval($completedByArr[$i]);
                        }
                        if (isset($completedAtArr[$i]) && $completedAtArr[$i] !== '') {
                            $event['completed_at'] = self::sanitizeText($completedAtArr[$i]);
                        }

                        $eventDates[] = $event;
                    }
                }
            }
            $processed['event_dates'] = $eventDates;

            return $processed;

        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Process JSON field from form data
     */
    public static function processJsonField(array $data, string $field): array
    {
        if (!isset($data[$field])) {
            return [];
        }

        $value = $data[$field];

        if (is_array($value)) {
            if ($field === 'schedule_data') {
                $scheduleData = self::reconstructScheduleData($data);
                return self::processScheduleData($scheduleData);
            }

            return $value;
        }

        if (is_string($value)) {
            if (empty($value)) {
                return [];
            }

            $value = stripslashes($value);
            $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');

            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }

            if ($field === 'schedule_data' && !empty($decoded)) {
                $decoded = self::processScheduleData($decoded);
            }

            return $decoded ?: [];
        }

        return [];
    }

    /**
     * Reconstruct schedule data from form's nested array structure
     */
    public static function reconstructScheduleData(array $data): array
    {
        $scheduleData = [];

        if (isset($data['schedule_data']) && is_array($data['schedule_data'])) {
            foreach ($data['schedule_data'] as $key => $value) {
                if (!is_array($value)) {
                    $scheduleData[$key] = $value;
                }
            }

            if (isset($data['schedule_data']['per_day_times'])) {
                $scheduleData['per_day_times'] = $data['schedule_data']['per_day_times'];
            }
            if (isset($data['schedule_data']['selected_days'])) {
                $scheduleData['selected_days'] = $data['schedule_data']['selected_days'];
            }
            if (isset($data['schedule_data']['exception_dates'])) {
                $scheduleData['exception_dates'] = $data['schedule_data']['exception_dates'];
            }
            if (isset($data['schedule_data']['holiday_overrides']) && is_array($data['schedule_data']['holiday_overrides'])) {
                $overrides = [];
                foreach ($data['schedule_data']['holiday_overrides'] as $date => $value) {
                    $overrides[$date] = ($value === '1' || $value === 'true' || $value === true);
                }
                $scheduleData['holiday_overrides'] = $overrides;
            }
        }

        if (!isset($scheduleData['start_date']) && isset($data['schedule_start_date'])) {
            $scheduleData['start_date'] = $data['schedule_start_date'];
        }

        if (isset($data['schedule_end_date']) && !empty($data['schedule_end_date'])) {
            $scheduleData['end_date'] = $data['schedule_end_date'];
        } elseif (isset($data['schedule_data']['end_date']) && !empty($data['schedule_data']['end_date'])) {
            $scheduleData['end_date'] = $data['schedule_data']['end_date'];
        } elseif (isset($data['schedule_data']['endDate']) && !empty($data['schedule_data']['endDate'])) {
            $scheduleData['end_date'] = $data['schedule_data']['endDate'];
        }

        if (empty($scheduleData['selected_days']) && isset($data['schedule_days']) && is_array($data['schedule_days'])) {
            $scheduleData['selected_days'] = array_values(array_filter($data['schedule_days']));
        }

        if (empty($scheduleData['per_day_times']) && isset($data['day_start_time']) && isset($data['day_end_time'])) {
            $scheduleData['per_day_times'] = [];
            foreach ($data['day_start_time'] as $day => $startTime) {
                if (!empty($startTime) && isset($data['day_end_time'][$day])) {
                    $endTime = $data['day_end_time'][$day];
                    $duration = self::calculateDuration($startTime, $endTime);
                    $scheduleData['per_day_times'][$day] = [
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'duration' => number_format($duration, 2)
                    ];
                }
            }
        }

        if (empty($scheduleData['exception_dates']) && isset($data['exception_dates']) && is_array($data['exception_dates'])) {
            $scheduleData['exception_dates'] = [];
            $exceptionDates = $data['exception_dates'];
            $exceptionReasons = isset($data['exception_reasons']) ? $data['exception_reasons'] : [];

            for ($i = 0; $i < count($exceptionDates); $i++) {
                $currentExceptionDate = $exceptionDates[$i] ?? '';
                if (!empty($currentExceptionDate)) {
                    $scheduleData['exception_dates'][] = [
                        'date' => $currentExceptionDate,
                        'reason' => $exceptionReasons[$i] ?? ''
                    ];
                }
            }
        }

        if (!isset($scheduleData['metadata'])) {
            $scheduleData['metadata'] = [
                'lastUpdated' => wp_date('c'),
                'validatedAt' => wp_date('c')
            ];
        }

        if (!isset($scheduleData['timeData'])) {
            $scheduleData['timeData'] = [
                'mode' => isset($scheduleData['time_mode']) ? $scheduleData['time_mode'] : 'per_day'
            ];
        }

        unset($scheduleData['time_mode']);

        return $scheduleData;
    }

    /**
     * Process schedule data with format detection and validation
     */
    public static function processScheduleData(array $scheduleData): array
    {
        if (!is_array($scheduleData)) {
            return [];
        }

        return self::validateScheduleDataV2($scheduleData);
    }

    /**
     * Validate and sanitize v2.0 schedule data format
     */
    public static function validateScheduleDataV2(array $data): array
    {
        $validated = [
            'version' => '2.0',
            'pattern' => 'weekly',
            'startDate' => '',
            'endDate' => '',
            'timeData' => ['mode' => 'single'],
            'selectedDays' => [],
            'dayOfMonth' => null,
            'exceptionDates' => [],
            'holidayOverrides' => [],
            'metadata' => [
                'lastUpdated' => wp_date('c'),
                'validatedAt' => wp_date('c')
            ]
        ];

        if (isset($data['version'])) {
            $validated['version'] = sanitize_text_field($data['version']);
        }

        $allowedPatterns = ['weekly', 'biweekly', 'monthly', 'custom'];
        if (isset($data['pattern']) && in_array($data['pattern'], $allowedPatterns)) {
            $validated['pattern'] = $data['pattern'];
        }

        if (isset($data['startDate']) && self::isValidDate($data['startDate'])) {
            $validated['startDate'] = sanitize_text_field($data['startDate']);
        } elseif (isset($data['start_date']) && self::isValidDate($data['start_date'])) {
            $validated['startDate'] = sanitize_text_field($data['start_date']);
        }

        if (isset($data['endDate']) && self::isValidDate($data['endDate'])) {
            $validated['endDate'] = sanitize_text_field($data['endDate']);
        } elseif (isset($data['end_date']) && self::isValidDate($data['end_date'])) {
            $validated['endDate'] = sanitize_text_field($data['end_date']);
        }

        if (isset($data['dayOfMonth']) && is_numeric($data['dayOfMonth'])) {
            $dayOfMonth = intval($data['dayOfMonth']);
            if ($dayOfMonth >= 1 && $dayOfMonth <= 31) {
                $validated['dayOfMonth'] = $dayOfMonth;
            }
        }

        if (isset($data['timeData']) && is_array($data['timeData'])) {
            $validated['timeData'] = self::validateTimeData($data['timeData']);
        } else {
            if (isset($data['per_day_times']) && is_array($data['per_day_times'])) {
                $validated['timeData'] = [
                    'mode' => 'per-day',
                    'perDayTimes' => $data['per_day_times']
                ];
            }
        }

        if (isset($data['per_day_times']) && is_array($data['per_day_times']) && !empty($data['per_day_times'])) {
            $validated['timeData'] = [
                'mode' => 'per-day',
                'perDayTimes' => $data['per_day_times']
            ];
        }

        if (isset($data['selectedDays']) && is_array($data['selectedDays'])) {
            $allowedDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            $validated['selectedDays'] = array_intersect($data['selectedDays'], $allowedDays);
        } elseif (isset($data['selected_days']) && is_array($data['selected_days'])) {
            $allowedDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            $validated['selectedDays'] = array_intersect($data['selected_days'], $allowedDays);
        }

        if (isset($data['exceptionDates']) && is_array($data['exceptionDates'])) {
            $validated['exceptionDates'] = self::validateExceptionDates($data['exceptionDates']);
        } elseif (isset($data['exception_dates']) && is_array($data['exception_dates'])) {
            $validated['exceptionDates'] = self::validateExceptionDates($data['exception_dates']);
        }

        if (isset($data['holidayOverrides']) && is_array($data['holidayOverrides'])) {
            $validated['holidayOverrides'] = self::validateHolidayOverrides($data['holidayOverrides']);
        } elseif (isset($data['holiday_overrides']) && is_array($data['holiday_overrides'])) {
            $validated['holidayOverrides'] = self::validateHolidayOverrides($data['holiday_overrides']);
        }

        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $validated['metadata'] = array_merge($validated['metadata'], $data['metadata']);
        }

        if (isset($data['generatedSchedule']) && is_array($data['generatedSchedule'])) {
            $validated['generatedSchedule'] = $data['generatedSchedule'];
        }

        return $validated;
    }

    /**
     * Validate time data structure
     */
    public static function validateTimeData(array $timeData): array
    {
        $validated = ['mode' => 'single'];

        $allowedModes = ['single', 'per-day'];
        if (isset($timeData['mode']) && in_array($timeData['mode'], $allowedModes)) {
            $validated['mode'] = $timeData['mode'];
        }

        if ($validated['mode'] === 'single' && isset($timeData['single'])) {
            $validated['single'] = self::validateSingleTimeData($timeData['single']);
        }

        if ($validated['mode'] === 'per-day' && isset($timeData['perDay'])) {
            $validated['perDay'] = self::validatePerDayTimeData($timeData['perDay']);
        }

        return $validated;
    }

    /**
     * Validate single time data
     */
    public static function validateSingleTimeData(array $singleData): array
    {
        $validated = [
            'startTime' => '',
            'endTime' => '',
            'duration' => 0
        ];

        if (isset($singleData['startTime']) && self::isValidTime($singleData['startTime'])) {
            $validated['startTime'] = sanitize_text_field($singleData['startTime']);
        }

        if (isset($singleData['endTime']) && self::isValidTime($singleData['endTime'])) {
            $validated['endTime'] = sanitize_text_field($singleData['endTime']);
        }

        if (isset($singleData['duration']) && is_numeric($singleData['duration'])) {
            $validated['duration'] = floatval($singleData['duration']);
        } elseif ($validated['startTime'] && $validated['endTime']) {
            $validated['duration'] = self::calculateDuration($validated['startTime'], $validated['endTime']);
        }

        return $validated;
    }

    /**
     * Validate per-day time data
     */
    public static function validatePerDayTimeData(array $perDayData): array
    {
        $validated = [];
        $allowedDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        foreach ($perDayData as $day => $dayData) {
            if (in_array($day, $allowedDays) && is_array($dayData)) {
                $validated[$day] = self::validateSingleTimeData($dayData);
            }
        }

        return $validated;
    }

    /**
     * Validate exception dates array
     */
    public static function validateExceptionDates(array $exceptionDates): array
    {
        $validated = [];

        foreach ($exceptionDates as $exception) {
            if (is_array($exception) && isset($exception['date']) && self::isValidDate($exception['date'])) {
                $validException = [
                    'date' => sanitize_text_field($exception['date']),
                    'reason' => isset($exception['reason']) ? sanitize_text_field($exception['reason']) : 'No reason specified'
                ];
                $validated[] = $validException;
            }
        }

        return $validated;
    }

    /**
     * Validate holiday overrides
     */
    public static function validateHolidayOverrides(array $holidayOverrides): array
    {
        $validated = [];

        foreach ($holidayOverrides as $date => $override) {
            if (self::isValidDate($date)) {
                $validated[sanitize_text_field($date)] = (bool)$override;
            }
        }

        return $validated;
    }

    /**
     * Populate a ClassModel with processed form data
     */
    public static function populateClassModel(ClassModel $class, array $formData): ClassModel
    {
        if (isset($formData['client_id'])) $class->setClientId($formData['client_id']);
        if (isset($formData['site_id'])) $class->setSiteId($formData['site_id']);
        if (isset($formData['site_address'])) $class->setClassAddressLine($formData['site_address']);
        if (isset($formData['class_type'])) $class->setClassType($formData['class_type']);
        if (isset($formData['class_subject'])) $class->setClassSubject($formData['class_subject']);
        if (isset($formData['class_code'])) $class->setClassCode($formData['class_code']);
        if (isset($formData['class_duration'])) $class->setClassDuration($formData['class_duration']);
        if (isset($formData['original_start_date'])) $class->setOriginalStartDate($formData['original_start_date']);
        if (isset($formData['seta_funded'])) $class->setSetaFunded($formData['seta_funded']);
        if (isset($formData['seta'])) $class->setSeta($formData['seta']);
        if (isset($formData['exam_class'])) $class->setExamClass($formData['exam_class']);
        if (isset($formData['exam_type'])) $class->setExamType($formData['exam_type']);
        if (isset($formData['class_agent'])) $class->setClassAgent($formData['class_agent']);
        if (isset($formData['initial_class_agent'])) $class->setInitialClassAgent($formData['initial_class_agent']);
        if (isset($formData['initial_agent_start_date'])) $class->setInitialAgentStartDate($formData['initial_agent_start_date']);
        if (isset($formData['project_supervisor'])) $class->setProjectSupervisorId($formData['project_supervisor']);
        if (isset($formData['learner_ids'])) $class->setLearnerIds($formData['learner_ids']);
        if (isset($formData['exam_learners'])) $class->setExamLearners($formData['exam_learners']);
        if (isset($formData['backup_agent_ids'])) $class->setBackupAgentIds($formData['backup_agent_ids']);
        if (isset($formData['agent_replacements'])) $class->setAgentReplacements($formData['agent_replacements']);
        if (isset($formData['schedule_data'])) $class->setScheduleData($formData['schedule_data']);
        if (isset($formData['stop_restart_dates'])) $class->setStopRestartDates($formData['stop_restart_dates']);
        if (isset($formData['event_dates'])) $class->setEventDates($formData['event_dates']);
        if (isset($formData['class_notes']) && !empty($formData['class_notes'])) {
            $class->setClassNotesData($formData['class_notes']);
        }

        if (isset($formData['order_nr'])) {
            $class->setOrderNr($formData['order_nr']);
        }

        return $class;
    }

    /**
     * Sanitize text input
     */
    public static function sanitizeText(mixed $text): string
    {
        if ($text === null) {
            return '';
        }

        if (function_exists('sanitize_text_field')) {
            return sanitize_text_field((string)$text);
        }

        return htmlspecialchars(strip_tags((string)$text), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Check if a date string is valid
     */
    public static function isValidDate(mixed $date): bool
    {
        if (!is_string($date)) {
            return false;
        }

        $timestamp = strtotime($date);
        return $timestamp !== false && wp_date('Y-m-d', $timestamp) === $date;
    }

    /**
     * Check if a time string is valid (HH:MM format)
     */
    public static function isValidTime(mixed $time): bool
    {
        if (!is_string($time)) {
            return false;
        }

        return preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time) === 1;
    }

    /**
     * Calculate duration in hours from start and end time
     */
    public static function calculateDuration(string $startTime, string $endTime): float
    {
        $start = strtotime($startTime);
        $end = strtotime($endTime);

        if ($start === false || $end === false || $end <= $start) {
            return 0;
        }

        return ($end - $start) / 3600;
    }
}
