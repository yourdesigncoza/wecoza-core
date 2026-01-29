/**
 * WeCoza HTML Escape Utilities
 * Prevents XSS attacks by escaping HTML entities in user-provided data
 *
 * @package WeCozaClasses
 */

(function(window) {
    'use strict';

    // Create namespace
    window.WeCozaUtils = window.WeCozaUtils || {};

    /**
     * Escape HTML entities to prevent XSS attacks
     *
     * @param {*} str - The string to escape (non-strings converted to string)
     * @returns {string} - HTML-escaped string safe for DOM insertion
     */
    window.WeCozaUtils.escapeHtml = function(str) {
        if (str === null || str === undefined) {
            return '';
        }

        // Convert to string if not already
        var text = String(str);

        // Use textContent for escaping (browser-native, secure)
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    /**
     * Escape a string for use in HTML attributes
     *
     * @param {*} str - The string to escape
     * @returns {string} - Attribute-safe escaped string
     */
    window.WeCozaUtils.escapeAttr = function(str) {
        if (str === null || str === undefined) {
            return '';
        }

        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    };

    /**
     * Shorthand aliases for convenience
     */
    window.WeCozaUtils.esc = window.WeCozaUtils.escapeHtml;
    window.WeCozaUtils.escAttr = window.WeCozaUtils.escapeAttr;

})(window);
