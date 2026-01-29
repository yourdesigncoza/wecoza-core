<?php
/**
 * WeCoza Core - Public Holidays Controller
 *
 * Controller for managing South African public holidays.
 * Migrated from wecoza-classes-plugin.
 *
 * @package WeCoza\Classes\Controllers
 * @since 1.0.0
 */

namespace WeCoza\Classes\Controllers;

use WeCoza\Core\Abstract\BaseController;

if (!defined('ABSPATH')) {
    exit;
}

class PublicHolidaysController extends BaseController
{
    /**
     * @var PublicHolidaysController Singleton instance
     */
    private static $instance = null;

    /**
     * Get the singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the controller
     */
    public function initialize(): void
    {
        $this->registerHooks();
    }

    /**
     * Register hooks for the controller
     */
    public function registerHooks(): void
    {
        add_action('wp_ajax_get_public_holidays', [__CLASS__, 'handlePublicHolidaysAjax']);
        add_action('wp_ajax_nopriv_get_public_holidays', [__CLASS__, 'handlePublicHolidaysAjax']);
    }

    /**
     * Get public holidays for a specific year
     *
     * @param int $year The year to get holidays for
     * @return array Array of holiday data
     */
    public function getHolidaysByYear(int $year): array
    {
        return [
            ['date' => $year . '-01-01', 'name' => 'New Year\'s Day'],
            ['date' => $year . '-03-21', 'name' => 'Human Rights Day'],
            ['date' => $year . '-04-27', 'name' => 'Freedom Day'],
            ['date' => $year . '-05-01', 'name' => 'Workers\' Day'],
            ['date' => $year . '-06-16', 'name' => 'Youth Day'],
            ['date' => $year . '-08-09', 'name' => 'National Women\'s Day'],
            ['date' => $year . '-09-24', 'name' => 'Heritage Day'],
            ['date' => $year . '-12-16', 'name' => 'Day of Reconciliation'],
            ['date' => $year . '-12-25', 'name' => 'Christmas Day'],
            ['date' => $year . '-12-26', 'name' => 'Day of Goodwill'],
        ];
    }

    /**
     * Get all public holidays as an array of dates
     *
     * @return array Array of dates in Y-m-d format
     */
    public function getAllHolidayDates(): array
    {
        $currentYear = (int) date('Y');
        $nextYear = $currentYear + 1;

        $currentYearHolidays = $this->getHolidaysByYear($currentYear);
        $nextYearHolidays = $this->getHolidaysByYear($nextYear);

        $allHolidays = array_merge($currentYearHolidays, $nextYearHolidays);

        return array_map(function ($holiday) {
            return $holiday['date'];
        }, $allHolidays);
    }

    /**
     * Check if a date is a public holiday
     *
     * @param string $date Date in Y-m-d format
     * @return bool True if the date is a public holiday
     */
    public function isPublicHoliday(string $date): bool
    {
        $holidayDates = $this->getAllHolidayDates();
        return in_array($date, $holidayDates);
    }

    /**
     * Get public holidays within a date range
     *
     * @param string $startDate Start date in Y-m-d format
     * @param string $endDate End date in Y-m-d format
     * @return array Array of holiday data
     */
    public function getHolidaysInRange(string $startDate, string $endDate): array
    {
        $startYear = (int) date('Y', strtotime($startDate));
        $endYear = (int) date('Y', strtotime($endDate));

        $holidays = [];
        for ($year = $startYear; $year <= $endYear; $year++) {
            $yearHolidays = $this->getHolidaysByYear($year);
            $holidays = array_merge($holidays, $yearHolidays);
        }

        return array_filter($holidays, function ($holiday) use ($startDate, $endDate) {
            return $holiday['date'] >= $startDate && $holiday['date'] <= $endDate;
        });
    }

    /**
     * Get public holidays formatted for FullCalendar
     *
     * @param int $year The year to get holidays for
     * @return array Array of FullCalendar-compatible event objects
     */
    public function getHolidaysForCalendar(int $year): array
    {
        $holidays = $this->getHolidaysByYear($year);
        $calendarEvents = [];

        foreach ($holidays as $holiday) {
            $calendarEvents[] = [
                'id' => 'holiday_' . $holiday['date'],
                'title' => $holiday['name'],
                'start' => $holiday['date'],
                'allDay' => true,
                'display' => 'background',
                'classNames' => ['wecoza-public-holiday'],
                'extendedProps' => [
                    'type' => 'public_holiday',
                    'interactive' => false
                ]
            ];
        }

        return $calendarEvents;
    }

    /**
     * AJAX handler for getting public holidays
     */
    public static function handlePublicHolidaysAjax(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wecoza_calendar_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        $year = intval($_POST['year'] ?? date('Y'));

        if ($year < 2020 || $year > 2030) {
            $year = (int) date('Y');
        }

        try {
            $controller = self::getInstance();
            $holidays = $controller->getHolidaysForCalendar($year);
            wp_send_json($holidays);

        } catch (\Exception $e) {
            error_log('WeCoza Core Public Holidays Error: ' . $e->getMessage());
            wp_send_json_error('Failed to load public holidays: ' . $e->getMessage());
        }
    }
}
