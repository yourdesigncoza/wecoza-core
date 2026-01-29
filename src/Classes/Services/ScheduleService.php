<?php
/**
 * WeCoza Core - Schedule Service
 *
 * Service for schedule generation and calendar event creation.
 * Migrated from wecoza-classes-plugin.
 *
 * @package WeCoza\Classes\Services
 * @since 1.0.0
 */

namespace WeCoza\Classes\Services;

use DateTime;
use DateInterval;
use DatePeriod;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class ScheduleService
{
    /**
     * Convert V2 schedule data to legacy format
     */
    public static function convertV2ToLegacy(array $v2Data): array
    {
        $legacyData = [];

        $legacyData['pattern'] = $v2Data['pattern'] ?? 'weekly';
        $legacyData['start_date'] = $v2Data['start_date'] ?? $v2Data['startDate'] ?? null;
        $legacyData['end_date'] = $v2Data['end_date'] ?? $v2Data['endDate'] ?? null;
        $legacyData['selected_days'] = $v2Data['selected_days'] ?? $v2Data['selectedDays'] ?? [];
        $legacyData['time_mode'] = $v2Data['timeData']['mode'] ?? 'single';

        if (isset($v2Data['timeData']['perDay'])) {
            $legacyData['per_day_times'] = $v2Data['timeData']['perDay'];
        } elseif (isset($v2Data['per_day_times'])) {
            $legacyData['per_day_times'] = $v2Data['per_day_times'];
        }

        return $legacyData;
    }

    /**
     * Generate schedule entries based on pattern and time data
     */
    public static function generateScheduleEntries(
        string $pattern,
        DateTime $startDate,
        DateTime $endDate,
        array $timeData,
        array $selectedDays = [],
        ?int $dayOfMonth = null
    ): array {
        switch ($pattern) {
            case 'weekly':
                return self::generateWeeklyEntries($startDate, $endDate, $timeData, $selectedDays);
            case 'biweekly':
                return self::generateBiweeklyEntries($startDate, $endDate, $timeData, $selectedDays);
            case 'monthly':
                return self::generateMonthlyEntries($startDate, $endDate, $timeData, $dayOfMonth ?? 1);
            default:
                return [];
        }
    }

    /**
     * Generate weekly schedule entries
     */
    public static function generateWeeklyEntries(
        DateTime $startDate,
        DateTime $endDate,
        array $timeData,
        array $selectedDays
    ): array {
        $entries = [];
        $current = clone $startDate;

        while ($current <= $endDate) {
            $dayName = $current->format('l');

            if (in_array($dayName, $selectedDays)) {
                $times = self::getTimesForDay($timeData, $dayName);
                if ($times) {
                    $entries[] = [
                        'date' => $current->format('Y-m-d'),
                        'start_time' => $times['startTime'],
                        'end_time' => $times['endTime']
                    ];
                }
            }

            $current->add(new DateInterval('P1D'));
        }

        return $entries;
    }

    /**
     * Generate biweekly schedule entries
     */
    public static function generateBiweeklyEntries(
        DateTime $startDate,
        DateTime $endDate,
        array $timeData,
        array $selectedDays
    ): array {
        $entries = [];
        $current = clone $startDate;
        $weekCount = 0;

        while ($current <= $endDate) {
            $dayName = $current->format('l');

            if ($weekCount % 2 === 0 && in_array($dayName, $selectedDays)) {
                $times = self::getTimesForDay($timeData, $dayName);
                if ($times) {
                    $entries[] = [
                        'date' => $current->format('Y-m-d'),
                        'start_time' => $times['startTime'],
                        'end_time' => $times['endTime']
                    ];
                }
            }

            if ($current->format('N') == 7) {
                $weekCount++;
            }

            $current->add(new DateInterval('P1D'));
        }

        return $entries;
    }

    /**
     * Generate monthly schedule entries
     */
    public static function generateMonthlyEntries(
        DateTime $startDate,
        DateTime $endDate,
        array $timeData,
        int $dayOfMonth
    ): array {
        $entries = [];
        $current = clone $startDate;

        $current->setDate($current->format('Y'), $current->format('n'), $dayOfMonth);

        if ($current < $startDate) {
            $current->add(new DateInterval('P1M'));
            $current->setDate($current->format('Y'), $current->format('n'), $dayOfMonth);
        }

        while ($current <= $endDate) {
            $times = self::getTimesForDay($timeData, null);
            if ($times) {
                $entries[] = [
                    'date' => $current->format('Y-m-d'),
                    'start_time' => $times['startTime'],
                    'end_time' => $times['endTime']
                ];
            }

            $current->add(new DateInterval('P1M'));
            $targetDay = min($dayOfMonth, $current->format('t'));
            $current->setDate($current->format('Y'), $current->format('n'), $targetDay);
        }

        return $entries;
    }

    /**
     * Get times for a specific day from time data
     */
    public static function getTimesForDay(array $timeData, ?string $dayName = null): ?array
    {
        $mode = $timeData['mode'] ?? 'single';

        if ($mode === 'per-day' && $dayName && isset($timeData['perDay'][$dayName])) {
            $dayData = $timeData['perDay'][$dayName];
            return [
                'startTime' => $dayData['startTime'] ?? '09:00',
                'endTime' => $dayData['endTime'] ?? '17:00'
            ];
        } elseif ($mode === 'single' && isset($timeData['single'])) {
            return [
                'startTime' => $timeData['single']['startTime'] ?? '09:00',
                'endTime' => $timeData['single']['endTime'] ?? '17:00'
            ];
        }

        return null;
    }

    /**
     * Generate calendar events from class schedule data
     */
    public function generateCalendarEvents(array $class): array
    {
        $events = [];

        $classCode = $class['class_code'] ?? 'Unknown';
        $classSubject = $class['class_subject'] ?? 'Unknown Subject';
        $startDate = $class['original_start_date'] ?? null;
        $deliveryDate = $this->getEarliestDeliveryDate($class);

        $scheduleData = null;
        if (!empty($class['schedule_data'])) {
            $scheduleData = is_string($class['schedule_data'])
                ? json_decode($class['schedule_data'], true)
                : $class['schedule_data'];
        }

        if ($scheduleData && is_array($scheduleData)) {
            $events = $this->generateEventsFromScheduleData($scheduleData, $class);
        } else {
            if ($startDate && $deliveryDate) {
                $events = $this->generateSampleEvents($class, $startDate, $deliveryDate, $classCode, $classSubject);
            }
        }

        if (!empty($class['exception_dates'])) {
            $events = array_merge($events, $this->generateExceptionDateEvents($class));
        }

        if (!empty($class['stop_restart_dates'])) {
            $events = array_merge($events, $this->generateStopRestartEvents($class, $classSubject));
        }

        return $events;
    }

    private function generateSampleEvents(
        array $class,
        string $startDate,
        string $deliveryDate,
        string $classCode,
        string $classSubject
    ): array {
        $events = [];
        $start = new DateTime($startDate);
        $end = new DateTime($deliveryDate);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end);

        $eventCount = 0;
        foreach ($period as $date) {
            if ($date->format('N') >= 6) {
                continue;
            }

            if ($eventCount >= 10) {
                break;
            }

            $events[] = [
                'id' => 'class_' . $class['class_id'] . '_' . $date->format('Y-m-d'),
                'title' => '09:00 - 17:00',
                'start' => $date->format('Y-m-d') . 'T09:00:00',
                'end' => $date->format('Y-m-d') . 'T17:00:00',
                'classNames' => ['wecoza-class-event', 'text-primary'],
                'extendedProps' => [
                    'type' => 'class_session',
                    'classCode' => $classCode,
                    'classSubject' => $classSubject,
                    'notes' => 'Sample class session'
                ]
            ];

            $eventCount++;
        }

        return $events;
    }

    /**
     * Generate events from schedule data
     */
    public function generateEventsFromScheduleData(array $scheduleData, array $class): array
    {
        $events = $this->generateEventsFromV2Data($scheduleData, $class);

        if (isset($scheduleData['exceptionDates'])) {
            $events = array_merge($events, $this->generateExceptionEvents($scheduleData['exceptionDates'], $class));
        }

        return $events;
    }

    /**
     * Generate events from v2.0 schedule data
     */
    public function generateEventsFromV2Data(array $scheduleData, array $class): array
    {
        return $this->generateEventsFromV2Pattern($scheduleData, $class);
    }

    /**
     * Generate events from V2.0 schedule pattern
     */
    public function generateEventsFromV2Pattern(array $scheduleData, array $class): array
    {
        $events = [];
        $classCode = $class['class_code'] ?? 'Unknown';
        $classSubject = $class['class_subject'] ?? 'Unknown Subject';

        // Check for direct schedule entries
        $hasDirectEntries = false;
        foreach ($scheduleData as $key => $value) {
            if (is_numeric($key) && is_array($value) && isset($value['date']) && isset($value['start_time']) && isset($value['end_time'])) {
                $hasDirectEntries = true;
                break;
            }
        }

        if ($hasDirectEntries) {
            foreach ($scheduleData as $key => $schedule) {
                if (is_numeric($key) && is_array($schedule) && isset($schedule['date']) && isset($schedule['start_time']) && isset($schedule['end_time'])) {
                    $duration = $this->calculateEventDuration($schedule['start_time'], $schedule['end_time']);
                    $dayName = $schedule['day'] ?? date('l', strtotime($schedule['date']));

                    $events[] = [
                        'id' => 'class_' . $class['class_id'] . '_' . $schedule['date'],
                        'title' => $dayName . ': ' . $schedule['start_time'] . ' - ' . $schedule['end_time'] . ' (' . $duration . 'h)',
                        'start' => $schedule['date'] . 'T' . $schedule['start_time'],
                        'end' => $schedule['date'] . 'T' . $schedule['end_time'],
                        'classNames' => ['wecoza-class-event', 'text-primary'],
                        'extendedProps' => [
                            'type' => 'class_session',
                            'classCode' => $classCode,
                            'classSubject' => $classSubject,
                            'notes' => $schedule['notes'] ?? '',
                            'scheduleFormat' => 'v2.0',
                            'dayOfWeek' => $dayName,
                            'duration' => $duration
                        ]
                    ];
                }
            }
        } else {
            // Handle pattern-based generation
            $pattern = $scheduleData['pattern'] ?? 'weekly';
            $startDate = isset($scheduleData['startDate']) ? new DateTime($scheduleData['startDate']) : null;
            $endDate = isset($scheduleData['endDate']) ? new DateTime($scheduleData['endDate']) : null;
            $timeData = $scheduleData['timeData'] ?? [];
            $selectedDays = $scheduleData['selectedDays'] ?? [];

            if ($startDate && $endDate) {
                $scheduleEntries = [];
                switch ($pattern) {
                    case 'weekly':
                        $scheduleEntries = self::generateWeeklyEntries($startDate, $endDate, $timeData, $selectedDays);
                        break;
                    case 'biweekly':
                        $scheduleEntries = self::generateBiweeklyEntries($startDate, $endDate, $timeData, $selectedDays);
                        break;
                    case 'monthly':
                        $scheduleEntries = self::generateMonthlyEntries($startDate, $endDate, $timeData, $scheduleData['dayOfMonth'] ?? 1);
                        break;
                    case 'custom':
                    default:
                        if (isset($timeData['single'])) {
                            $scheduleEntries[] = [
                                'date' => $scheduleData['startDate'],
                                'start_time' => $timeData['single']['startTime'] ?? '09:00',
                                'end_time' => $timeData['single']['endTime'] ?? '17:00'
                            ];
                        }
                        break;
                }

                foreach ($scheduleEntries as $schedule) {
                    if (isset($schedule['date']) && isset($schedule['start_time']) && isset($schedule['end_time'])) {
                        $date = new DateTime($schedule['date']);
                        $dayName = $date->format('l');

                        $events[] = [
                            'id' => 'class_' . $class['class_id'] . '_' . $schedule['date'],
                            'title' => $this->formatV2EventTitle($schedule, $dayName, $timeData),
                            'start' => $schedule['date'] . 'T' . $schedule['start_time'],
                            'end' => $schedule['date'] . 'T' . $schedule['end_time'],
                            'classNames' => ['wecoza-class-event', 'text-primary'],
                            'extendedProps' => [
                                'type' => 'class_session',
                                'classCode' => $classCode,
                                'classSubject' => $classSubject,
                                'notes' => $schedule['notes'] ?? '',
                                'scheduleFormat' => 'v2.0',
                                'dayOfWeek' => $dayName,
                                'pattern' => $pattern,
                                'timeMode' => $timeData['mode'] ?? 'single',
                                'duration' => $this->calculateEventDuration($schedule['start_time'], $schedule['end_time'])
                            ]
                        ];
                    }
                }
            }
        }

        return $events;
    }

    /**
     * Generate exception date events
     */
    public function generateExceptionEvents(array $exceptionDates, array $class): array
    {
        $events = [];

        foreach ($exceptionDates as $exception) {
            if (isset($exception['date']) && isset($exception['reason'])) {
                $events[] = [
                    'id' => 'exception_' . $class['class_id'] . '_' . $exception['date'],
                    'title' => 'Exception - ' . $exception['reason'],
                    'start' => $exception['date'],
                    'allDay' => true,
                    'display' => 'background',
                    'classNames' => ['wecoza-exception-event'],
                    'extendedProps' => [
                        'type' => 'exception',
                        'reason' => $exception['reason'],
                        'scheduleFormat' => 'v2.0'
                    ]
                ];
            }
        }

        return $events;
    }

    private function generateExceptionDateEvents(array $class): array
    {
        $events = [];
        $exceptionDates = is_string($class['exception_dates'])
            ? json_decode($class['exception_dates'], true)
            : $class['exception_dates'];

        if (is_array($exceptionDates)) {
            foreach ($exceptionDates as $exception) {
                if (isset($exception['date']) && isset($exception['reason'])) {
                    $events[] = [
                        'id' => 'exception_' . $class['class_id'] . '_' . $exception['date'],
                        'title' => 'Exception - ' . $exception['reason'],
                        'start' => $exception['date'],
                        'allDay' => true,
                        'display' => 'background',
                        'classNames' => ['wecoza-exception-event'],
                        'extendedProps' => [
                            'type' => 'exception',
                            'reason' => $exception['reason']
                        ]
                    ];
                }
            }
        }

        return $events;
    }

    private function generateStopRestartEvents(array $class, string $classSubject): array
    {
        $events = [];
        $stopRestartDates = is_string($class['stop_restart_dates'])
            ? json_decode($class['stop_restart_dates'], true)
            : $class['stop_restart_dates'];

        if (is_array($stopRestartDates)) {
            foreach ($stopRestartDates as $index => $stopRestart) {
                if (isset($stopRestart['stop_date']) && isset($stopRestart['restart_date'])) {
                    $stopDate = $stopRestart['stop_date'];
                    $restartDate = $stopRestart['restart_date'];

                    $events[] = [
                        'id' => 'class_stop_' . $class['class_id'] . '_' . $index,
                        'title' => 'Class Stopped',
                        'start' => $stopDate,
                        'allDay' => true,
                        'display' => 'block',
                        'classNames' => ['text-danger', 'wecoza-stop'],
                        'extendedProps' => [
                            'type' => 'stop_date',
                            'class_id' => $class['class_id'],
                            'description' => sprintf('Class Stopped: %s\nClass: %s', $stopDate, $classSubject),
                            'interactive' => false
                        ]
                    ];

                    $events[] = [
                        'id' => 'class_restart_' . $class['class_id'] . '_' . $index,
                        'title' => 'Restart',
                        'start' => $restartDate,
                        'allDay' => true,
                        'display' => 'block',
                        'classNames' => ['text-danger', 'wecoza-restart'],
                        'extendedProps' => [
                            'type' => 'restart_date',
                            'class_id' => $class['class_id'],
                            'description' => sprintf('Class Restart: %s\nClass: %s', $restartDate, $classSubject),
                            'interactive' => false
                        ]
                    ];

                    try {
                        $currentDate = new DateTime($stopDate);
                        $endDate = new DateTime($restartDate);
                        $currentDate->add(new DateInterval('P1D'));

                        while ($currentDate < $endDate) {
                            $dateStr = $currentDate->format('Y-m-d');

                            $events[] = [
                                'id' => 'stop_period_' . $class['class_id'] . '_' . $index . '_' . $dateStr,
                                'title' => '',
                                'start' => $dateStr,
                                'allDay' => true,
                                'display' => 'block',
                                'classNames' => ['text-danger', 'wecoza-stop-period'],
                                'extendedProps' => [
                                    'type' => 'stop_period',
                                    'class_id' => $class['class_id'],
                                    'description' => sprintf(
                                        'Class Stopped Period: %s\nClass: %s\nStopped from %s to %s',
                                        $dateStr,
                                        $classSubject,
                                        $stopDate,
                                        $restartDate
                                    ),
                                    'interactive' => false
                                ]
                            ];

                            $currentDate->add(new DateInterval('P1D'));
                        }
                    } catch (Exception $e) {
                        // Log error silently
                    }
                }
            }
        }

        return $events;
    }

    /**
     * Format event title based on schedule format
     */
    public function formatEventTitle(array $schedule, string $format): string
    {
        $startTime = $schedule['start_time'];
        $endTime = $schedule['end_time'];

        if ($format === 'v2.0') {
            $duration = $this->calculateEventDuration($startTime, $endTime);
            return sprintf('%s - %s (%.1fh)', $startTime, $endTime, $duration);
        } else {
            return $startTime . ' - ' . $endTime;
        }
    }

    /**
     * Format event title for v2.0 events
     */
    public function formatV2EventTitle(array $schedule, string $dayName, array $timeData): string
    {
        $startTime = $schedule['start_time'];
        $endTime = $schedule['end_time'];
        $duration = $this->calculateEventDuration($startTime, $endTime);

        $mode = $timeData['mode'] ?? 'single';
        if ($mode === 'per-day') {
            return sprintf('%s: %s - %s (%.1fh)', $dayName, $startTime, $endTime, $duration);
        } else {
            return sprintf('%s - %s (%.1fh)', $startTime, $endTime, $duration);
        }
    }

    /**
     * Calculate event duration in hours
     */
    public function calculateEventDuration(string $startTime, string $endTime): float
    {
        $start = strtotime($startTime);
        $end = strtotime($endTime);

        if ($start === false || $end === false || $end <= $start) {
            return 0;
        }

        return ($end - $start) / 3600;
    }

    /**
     * Get the earliest delivery date from event_dates
     */
    public function getEarliestDeliveryDate(array $class): ?string
    {
        $eventDates = $class['event_dates'] ?? [];
        if (is_string($eventDates)) {
            $eventDates = json_decode($eventDates, true) ?? [];
        }

        $deliveryDates = [];
        foreach ($eventDates as $event) {
            if (($event['type'] ?? '') === 'Deliveries' && !empty($event['date'])) {
                $deliveryDates[] = $event['date'];
            }
        }

        if (!empty($deliveryDates)) {
            return min($deliveryDates);
        }

        return $class['original_start_date'] ?? null;
    }
}
