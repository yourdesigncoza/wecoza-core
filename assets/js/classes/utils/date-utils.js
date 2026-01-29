/**
 * WeCoza Date/Time Utilities
 * Consolidated date and time formatting functions for the WeCoza Classes Plugin
 *
 * @package WeCozaClasses
 */

(function(window) {
    'use strict';

    // Create namespace
    window.WeCozaUtils = window.WeCozaUtils || {};

    /**
     * Day names array (Sunday = 0)
     */
    var DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /**
     * Get day index from day name
     *
     * @param {string} dayName - The name of the day (e.g., 'Monday')
     * @returns {number} - The index of the day (0-6, where 0 is Sunday)
     */
    window.WeCozaUtils.getDayIndex = function(dayName) {
        return DAYS.indexOf(dayName);
    };

    /**
     * Get day name from day index
     *
     * @param {number} dayIndex - The index of the day (0-6, where 0 is Sunday)
     * @returns {string} - The name of the day
     */
    window.WeCozaUtils.getDayName = function(dayIndex) {
        return DAYS[dayIndex] || '';
    };

    /**
     * Get day of week from date
     *
     * @param {Date} date - The date object
     * @returns {string} - The name of the day
     */
    window.WeCozaUtils.getDayOfWeek = function(date) {
        return DAYS[date.getDay()];
    };

    /**
     * Format date as YYYY-MM-DD (ISO format)
     *
     * @param {Date|string} date - The date object or date string
     * @returns {string} - The formatted date string (YYYY-MM-DD)
     */
    window.WeCozaUtils.formatDate = function(date) {
        var d = new Date(date);
        if (isNaN(d.getTime())) {
            return '';
        }

        var month = '' + (d.getMonth() + 1);
        var day = '' + d.getDate();
        var year = d.getFullYear();

        if (month.length < 2) month = '0' + month;
        if (day.length < 2) day = '0' + day;

        return [year, month, day].join('-');
    };

    /**
     * Format date for display (DD/MM/YYYY)
     *
     * @param {Date|string} date - The date object or date string
     * @returns {string} - The formatted date string (DD/MM/YYYY)
     */
    window.WeCozaUtils.formatDateDisplay = function(date) {
        var d = new Date(date);
        if (isNaN(d.getTime())) {
            return '';
        }

        var month = '' + (d.getMonth() + 1);
        var day = '' + d.getDate();
        var year = d.getFullYear();

        if (month.length < 2) month = '0' + month;
        if (day.length < 2) day = '0' + day;

        return [day, month, year].join('/');
    };

    /**
     * Format time in 12-hour format
     *
     * @param {Date|number} dateOrHour - Either a Date object or an hour (0-23)
     * @param {number} [minute] - The minute (0-59), only used if dateOrHour is a number
     * @returns {string} - The formatted time string (e.g., "6:30 AM")
     */
    window.WeCozaUtils.formatTime = function(dateOrHour, minute) {
        var hours, minutes;

        if (dateOrHour instanceof Date) {
            hours = dateOrHour.getHours();
            minutes = dateOrHour.getMinutes();
        } else {
            hours = parseInt(dateOrHour, 10);
            minutes = parseInt(minute, 10) || 0;
        }

        if (isNaN(hours)) {
            return '';
        }

        var ampm = hours >= 12 ? 'PM' : 'AM';
        var hour12 = hours % 12 || 12; // Convert 0 to 12 for 12 AM
        var minuteStr = minutes < 10 ? '0' + minutes : minutes;

        return hour12 + ':' + minuteStr + ' ' + ampm;
    };

    /**
     * Format time in 24-hour format (HH:MM)
     *
     * @param {Date|number} dateOrHour - Either a Date object or an hour (0-23)
     * @param {number} [minute] - The minute (0-59), only used if dateOrHour is a number
     * @returns {string} - The formatted time string (e.g., "14:30")
     */
    window.WeCozaUtils.formatTime24 = function(dateOrHour, minute) {
        var hours, minutes;

        if (dateOrHour instanceof Date) {
            hours = dateOrHour.getHours();
            minutes = dateOrHour.getMinutes();
        } else {
            hours = parseInt(dateOrHour, 10);
            minutes = parseInt(minute, 10) || 0;
        }

        if (isNaN(hours)) {
            return '';
        }

        var hourStr = hours < 10 ? '0' + hours : hours;
        var minuteStr = minutes < 10 ? '0' + minutes : minutes;

        return hourStr + ':' + minuteStr;
    };

    /**
     * Parse time string (HH:MM or H:MM AM/PM) to hours and minutes
     *
     * @param {string} timeStr - Time string to parse
     * @returns {object|null} - Object with hours and minutes, or null if invalid
     */
    window.WeCozaUtils.parseTime = function(timeStr) {
        if (!timeStr || typeof timeStr !== 'string') {
            return null;
        }

        // Try 24-hour format first (HH:MM)
        var match24 = timeStr.match(/^(\d{1,2}):(\d{2})$/);
        if (match24) {
            return {
                hours: parseInt(match24[1], 10),
                minutes: parseInt(match24[2], 10)
            };
        }

        // Try 12-hour format (H:MM AM/PM)
        var match12 = timeStr.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
        if (match12) {
            var hours = parseInt(match12[1], 10);
            var minutes = parseInt(match12[2], 10);
            var period = match12[3].toUpperCase();

            if (period === 'PM' && hours !== 12) {
                hours += 12;
            } else if (period === 'AM' && hours === 12) {
                hours = 0;
            }

            return { hours: hours, minutes: minutes };
        }

        return null;
    };

    /**
     * Add days to a date
     *
     * @param {Date|string} date - The starting date
     * @param {number} days - Number of days to add (can be negative)
     * @returns {Date} - New date with days added
     */
    window.WeCozaUtils.addDays = function(date, days) {
        var result = new Date(date);
        result.setDate(result.getDate() + days);
        return result;
    };

    /**
     * Check if a date falls on a specific day of the week
     *
     * @param {Date|string} date - The date to check
     * @param {string|number} day - Day name (e.g., 'Monday') or index (0-6)
     * @returns {boolean} - True if date is on the specified day
     */
    window.WeCozaUtils.isDay = function(date, day) {
        var d = new Date(date);
        if (isNaN(d.getTime())) {
            return false;
        }

        var dayIndex = typeof day === 'string' ? DAYS.indexOf(day) : day;
        return d.getDay() === dayIndex;
    };

    /**
     * Check if a date is a weekend (Saturday or Sunday)
     *
     * @param {Date|string} date - The date to check
     * @returns {boolean} - True if date is on a weekend
     */
    window.WeCozaUtils.isWeekend = function(date) {
        var d = new Date(date);
        if (isNaN(d.getTime())) {
            return false;
        }
        var day = d.getDay();
        return day === 0 || day === 6;
    };

    /**
     * Get the difference in days between two dates
     *
     * @param {Date|string} date1 - First date
     * @param {Date|string} date2 - Second date
     * @returns {number} - Number of days between dates (can be negative)
     */
    window.WeCozaUtils.daysBetween = function(date1, date2) {
        var d1 = new Date(date1);
        var d2 = new Date(date2);

        if (isNaN(d1.getTime()) || isNaN(d2.getTime())) {
            return NaN;
        }

        // Set to midnight to ignore time component
        d1.setHours(0, 0, 0, 0);
        d2.setHours(0, 0, 0, 0);

        var timeDiff = d2.getTime() - d1.getTime();
        return Math.round(timeDiff / (1000 * 60 * 60 * 24));
    };

    /**
     * Expose DAYS array for external use
     */
    window.WeCozaUtils.DAYS = DAYS;

})(window);
