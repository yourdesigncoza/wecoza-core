<?php
declare(strict_types=1);

/**
 * WeCoza Core - Report Service
 *
 * Business logic for class report data aggregation and CSV formatting.
 * Enriches raw repository data with calculated fields (initials, percentages,
 * schedule parsing) and formats for CSV export.
 *
 * @package WeCoza\Classes\Services
 * @since 1.0.0
 */

namespace WeCoza\Classes\Services;

use WeCoza\Classes\Repositories\ReportRepository;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

class ReportService
{
    /**
     * @var ReportRepository
     */
    private ReportRepository $repository;

    /**
     * Constructor
     *
     * @param ReportRepository|null $repository Optional repository instance (DI)
     */
    public function __construct(?ReportRepository $repository = null)
    {
        $this->repository = $repository ?? new ReportRepository();
    }

    /**
     * Generate a complete class report for a given month.
     *
     * Returns structured report data with enriched header, learner rows,
     * and metadata ready for CSV formatting or other output.
     *
     * @param int $classId Class ID
     * @param int $year Report year
     * @param int $month Report month (1-12)
     * @return array Report structure with 'header', 'learners', 'meta' keys
     */
    public function generateClassReport(int $classId, int $year, int $month): array
    {
        $header = $this->repository->getClassHeader($classId);

        if ($header === null) {
            return [
                'header' => null,
                'learners' => [],
                'meta' => [
                    'generated_at' => wp_date('Y-m-d H:i:s'),
                    'month_label' => $this->formatMonthLabel($year, $month),
                    'class_id' => $classId,
                    'error' => 'Class not found',
                ],
            ];
        }

        // Enrich header
        $scheduleData = $header['schedule_data'] ?? null;
        $parsed = $this->parseScheduleData($scheduleData);

        $enrichedHeader = [
            'client_name' => $header['client_name'] ?? '',
            'site_name' => $header['site_name'] ?? '',
            'class_type_name' => $header['class_type_name'] ?? '',
            'subject_name' => $header['subject_name'] ?? '',
            'facilitator' => $header['class_agent_name'] ?? '',
            'class_days' => $parsed['days'],
            'class_times' => $parsed['times'],
            'class_code' => $header['class_code'] ?? '',
        ];

        // Get learner rows and enrich
        $rawLearners = $this->repository->getClassLearnerReport($classId, $year, $month);
        $enrichedLearners = array_map([$this, 'enrichLearnerRow'], $rawLearners);

        return [
            'header' => $enrichedHeader,
            'learners' => $enrichedLearners,
            'meta' => [
                'generated_at' => wp_date('Y-m-d H:i:s'),
                'month_label' => $this->formatMonthLabel($year, $month),
                'class_id' => $classId,
            ],
        ];
    }

    /**
     * Format report data into CSV-ready rows.
     *
     * Returns array of arrays where each inner array is one CSV row.
     * All rows are padded to 12 columns for consistent Excel output.
     *
     * @param array $reportData Output from generateClassReport()
     * @return array Array of row arrays ready for fputcsv()
     */
    public function formatCsvRows(array $reportData): array
    {
        $header = $reportData['header'];
        $learners = $reportData['learners'];
        $meta = $reportData['meta'];
        $colCount = 12;

        $rows = [];

        // Metadata rows (padded to 12 columns)
        $rows[] = $this->padRow(['Client', $header['client_name'] ?? ''], $colCount);
        $rows[] = $this->padRow(['Site', $header['site_name'] ?? ''], $colCount);
        $rows[] = $this->padRow([
            'Class Type & Subject',
            ($header['class_type_name'] ?? '') . ' - ' . ($header['subject_name'] ?? ''),
        ], $colCount);
        $rows[] = $this->padRow(['Month', $meta['month_label'] ?? ''], $colCount);
        $rows[] = $this->padRow(['Class Days', $header['class_days'] ?? ''], $colCount);
        $rows[] = $this->padRow(['Class Times', $header['class_times'] ?? ''], $colCount);
        $rows[] = $this->padRow(['Facilitator', $header['facilitator'] ?? ''], $colCount);

        // Empty row separator
        $rows[] = array_fill(0, $colCount, '');

        // Column headers
        $rows[] = [
            'Surname',
            'Initials',
            'Current Level/Module',
            'Start Date',
            'Race',
            'Gender',
            'Month Trained',
            'Month Present',
            'Total Trained',
            'Total Present',
            'Hours Progress %',
            'Page Progress %',
        ];

        // Learner data rows
        if (empty($learners)) {
            $noDataRow = ['No learners found'];
            $rows[] = $this->padRow($noDataRow, $colCount);
        } else {
            foreach ($learners as $learner) {
                $rows[] = [
                    $learner['surname'] ?? '',
                    $learner['initials'] ?? '',
                    $learner['subject_name'] ?? '',
                    $this->formatDate($learner['start_date'] ?? null),
                    $learner['race'] ?? '',
                    $learner['gender'] ?? '',
                    $this->formatNumber($learner['month_hours_trained'] ?? 0),
                    $this->formatNumber($learner['month_hours_present'] ?? 0),
                    $this->formatNumber($learner['hours_trained'] ?? 0),
                    $this->formatNumber($learner['hours_present'] ?? 0),
                    $this->formatPercentage($learner['hours_progress_pct'] ?? null),
                    $this->formatPercentage($learner['page_progress_pct'] ?? null),
                ];
            }
        }

        return $rows;
    }

    /**
     * Enrich a raw learner row with calculated fields.
     *
     * Adds initials, hours progress %, and page progress %.
     *
     * @param array $row Raw learner row from repository
     * @return array Enriched learner row
     */
    private function enrichLearnerRow(array $row): array
    {
        // Initials: first letter of first_name with dot
        $firstName = $row['first_name'] ?? '';
        $row['initials'] = $firstName !== '' ? strtoupper(mb_substr($firstName, 0, 1)) . '.' : '';

        // Hours progress %: guard division by zero, cap at 100
        $subjectDuration = (float) ($row['subject_duration'] ?? 0);
        $hoursPresent = (float) ($row['hours_present'] ?? 0);
        if ($subjectDuration > 0) {
            $row['hours_progress_pct'] = min(round(($hoursPresent / $subjectDuration) * 100, 1), 100.0);
        } else {
            $row['hours_progress_pct'] = null;
        }

        // Page progress %: guard division by zero, cap at 100
        $totalPages = (int) ($row['total_pages'] ?? 0);
        $lastPageNumber = (int) ($row['last_page_number'] ?? 0);
        if ($totalPages > 0 && $lastPageNumber > 0) {
            $row['page_progress_pct'] = min(round(($lastPageNumber / $totalPages) * 100, 1), 100.0);
        } else {
            $row['page_progress_pct'] = null;
        }

        return $row;
    }

    /**
     * Parse schedule_data JSONB into human-readable days and times.
     *
     * @param string|null $scheduleJson Raw JSONB string from database
     * @return array ['days' => 'Mon, Wed, Fri', 'times' => '08:00-12:00']
     */
    private function parseScheduleData(?string $scheduleJson): array
    {
        $default = ['days' => '', 'times' => ''];

        if (empty($scheduleJson)) {
            return $default;
        }

        $schedule = json_decode($scheduleJson, true);
        if (!is_array($schedule) || empty($schedule)) {
            return $default;
        }

        $dayAbbreviations = [
            'Monday' => 'Mon',
            'Tuesday' => 'Tue',
            'Wednesday' => 'Wed',
            'Thursday' => 'Thu',
            'Friday' => 'Fri',
            'Saturday' => 'Sat',
            'Sunday' => 'Sun',
        ];

        $days = [];
        $times = [];

        // v2.0 format: { selectedDays: [...], timeData: { perDayTimes: { Day: {start_time, end_time} } } }
        if (isset($schedule['version']) && version_compare((string) $schedule['version'], '2.0', '>=')) {
            $selectedDays = $schedule['selectedDays'] ?? [];
            foreach ($selectedDays as $dayName) {
                $abbr = $dayAbbreviations[$dayName] ?? $dayName;
                if ($abbr !== '' && !in_array($abbr, $days, true)) {
                    $days[] = $abbr;
                }
            }

            $perDayTimes = $schedule['timeData']['perDayTimes'] ?? [];
            $dayTimeMap = [];
            foreach ($perDayTimes as $dayName => $dayTimes) {
                $abbr = $dayAbbreviations[$dayName] ?? $dayName;
                $ranges = [];

                // Newer format: intervals array with multiple sessions per day
                if (!empty($dayTimes['intervals']) && is_array($dayTimes['intervals'])) {
                    foreach ($dayTimes['intervals'] as $interval) {
                        $start = $interval['startTime'] ?? '';
                        $end = $interval['endTime'] ?? '';
                        if ($start !== '' && $end !== '') {
                            $ranges[] = $start . ' - ' . $end;
                        }
                    }
                } else {
                    // Older format: single start_time / end_time
                    $start = $dayTimes['start_time'] ?? '';
                    $end = $dayTimes['end_time'] ?? '';
                    if ($start !== '' && $end !== '') {
                        $ranges[] = $start . ' - ' . $end;
                    }
                }

                if (!empty($ranges)) {
                    $dayTimeMap[$abbr] = implode(', ', $ranges);
                }
            }

            // If all days share the same times, show once; otherwise show per-day
            $uniqueTimes = array_unique(array_values($dayTimeMap));
            if (count($uniqueTimes) === 1) {
                $timesStr = $uniqueTimes[0];
            } else {
                $parts = [];
                foreach ($dayTimeMap as $abbr => $timeRange) {
                    $parts[] = $abbr . ': ' . $timeRange;
                }
                $timesStr = implode('; ', $parts);
            }

            return [
                'days' => implode(', ', $days),
                'times' => $timesStr,
            ];
        }

        // v1 format: flat array of { day, start_time, end_time }
        foreach ($schedule as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $dayName = $entry['day'] ?? '';
            $abbr = $dayAbbreviations[$dayName] ?? $dayName;
            if ($abbr !== '' && !in_array($abbr, $days, true)) {
                $days[] = $abbr;
            }

            $startTime = $entry['start_time'] ?? '';
            $endTime = $entry['end_time'] ?? '';
            if ($startTime !== '' && $endTime !== '') {
                $timeRange = $startTime . ' - ' . $endTime;
                if (!in_array($timeRange, $times, true)) {
                    $times[] = $timeRange;
                }
            }
        }

        return [
            'days' => implode(', ', $days),
            'times' => implode('; ', $times),
        ];
    }

    /**
     * Format a month label from year and month number.
     *
     * @param int $year Year
     * @param int $month Month (1-12)
     * @return string e.g. "March 2026"
     */
    private function formatMonthLabel(int $year, int $month): string
    {
        $timestamp = mktime(0, 0, 0, $month, 1, $year);
        return date('F Y', $timestamp);
    }

    /**
     * Format a date string as d/m/Y.
     *
     * @param string|null $date Date string
     * @return string Formatted date or empty string
     */
    private function formatDate(?string $date): string
    {
        if (empty($date)) {
            return '';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '';
        }

        return date('d/m/Y', $timestamp);
    }

    /**
     * Format a numeric value for CSV output.
     *
     * @param mixed $value Numeric value
     * @return string Formatted number
     */
    private function formatNumber(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }
        $num = (float) $value;
        // Show integer if whole number, otherwise 1 decimal
        return $num == (int) $num ? (string) (int) $num : number_format($num, 1, '.', '');
    }

    /**
     * Format a percentage value for CSV output.
     *
     * @param float|null $value Percentage value
     * @return string Formatted percentage with % suffix, or dash if null
     */
    private function formatPercentage(?float $value): string
    {
        if ($value === null) {
            return '-';
        }
        return number_format($value, 1, '.', '') . '%';
    }

    /**
     * Pad a row array to a specified column count with empty strings.
     *
     * @param array $row Row data
     * @param int $colCount Target column count
     * @return array Padded row
     */
    private function padRow(array $row, int $colCount): array
    {
        return array_pad($row, $colCount, '');
    }
}
