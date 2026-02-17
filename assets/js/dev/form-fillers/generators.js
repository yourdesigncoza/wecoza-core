/**
 * WeCoza Dev Toolbar — Data Generators
 *
 * Functions to generate realistic SA test data from data pools.
 * Only loaded when WP_DEBUG is true.
 */
window.WeCozaDevGen = (function () {
    'use strict';

    var D = window.WeCozaDevData;

    // ── Utilities ──────────────────────────────────────────

    function pickRandom(arr) {
        if (!arr || !arr.length) return '';
        return arr[Math.floor(Math.random() * arr.length)];
    }

    function randomInt(min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    function padZero(num, len) {
        return String(num).padStart(len || 2, '0');
    }

    // ── SA ID Number (Luhn checksum) ───────────────────────

    function luhnChecksum(partial) {
        var digits = partial.split('').map(Number);
        var sum = 0;
        for (var i = digits.length - 1; i >= 0; i--) {
            var d = digits[i];
            if ((digits.length - i) % 2 !== 0) {
                d *= 2;
                if (d > 9) d -= 9;
            }
            sum += d;
        }
        return (10 - (sum % 10)) % 10;
    }

    function generateSAID(dob, isMale) {
        // dob = Date object
        var yy = padZero(dob.getFullYear() % 100);
        var mm = padZero(dob.getMonth() + 1);
        var dd = padZero(dob.getDate());
        var gender = isMale ? randomInt(5000, 9999) : randomInt(0, 4999);
        var citizenship = '0'; // SA citizen
        var a = '8';
        var partial = yy + mm + dd + padZero(gender, 4) + citizenship + a;
        var checkDigit = luhnChecksum(partial);
        return partial + checkDigit;
    }

    // ── Contact Generators ─────────────────────────────────

    function generatePhone() {
        var prefix = pickRandom(D.phonePrefixes);
        var rest = '';
        for (var i = 0; i < 7; i++) rest += randomInt(0, 9);
        return prefix + ' ' + rest.substring(0, 3) + ' ' + rest.substring(3);
    }

    function generateEmail(firstName, surname) {
        var domain = pickRandom(D.emailDomains);
        var clean = function (s) {
            return s.toLowerCase().replace(/[^a-z]/g, '');
        };
        return clean(firstName) + '.' + clean(surname) + '@' + domain;
    }

    // ── Date Generators ────────────────────────────────────

    function generateDate(minYearsAgo, maxYearsAgo) {
        var now = new Date();
        var minDate = new Date(now.getFullYear() - maxYearsAgo, 0, 1);
        var maxDate = new Date(now.getFullYear() - minYearsAgo, 11, 31);
        var ts = minDate.getTime() + Math.random() * (maxDate.getTime() - minDate.getTime());
        return new Date(ts);
    }

    function formatDate(date) {
        if (!date) return '';
        return date.getFullYear() + '-' + padZero(date.getMonth() + 1) + '-' + padZero(date.getDate());
    }

    function generateRecentDate(monthsBack) {
        var now = new Date();
        var past = new Date(now);
        past.setMonth(past.getMonth() - (monthsBack || 6));
        var ts = past.getTime() + Math.random() * (now.getTime() - past.getTime());
        return new Date(ts);
    }

    function generateFutureDate(monthsAhead) {
        var now = new Date();
        var future = new Date(now);
        future.setMonth(future.getMonth() + (monthsAhead || 12));
        var ts = now.getTime() + Math.random() * (future.getTime() - now.getTime());
        return new Date(ts);
    }

    // ── Person Generator ───────────────────────────────────

    function generatePerson() {
        var isMale = Math.random() > 0.5;
        var firstName = pickRandom(isMale ? D.firstNamesMale : D.firstNamesFemale);
        var secondName = Math.random() > 0.5 ? pickRandom(isMale ? D.firstNamesMale : D.firstNamesFemale) : '';
        var surname = pickRandom(D.surnames);
        var dob = generateDate(18, 55);
        var saId = generateSAID(dob, isMale);

        return {
            isMale: isMale,
            title: pickRandom(isMale ? ['Mr'] : ['Mrs', 'Ms', 'Miss']),
            firstName: firstName,
            secondName: secondName,
            surname: surname,
            initials: firstName.charAt(0) + (secondName ? secondName.charAt(0) : ''),
            dob: dob,
            saId: saId,
            phone: generatePhone(),
            altPhone: Math.random() > 0.5 ? generatePhone() : '',
            email: generateEmail(firstName, surname),
            gender: isMale ? 'M' : 'F',
            learnerGender: isMale ? 'Male' : 'Female',
            race: pickRandom(D.races),
            learnerRace: pickRandom(D.learnerRaces)
        };
    }

    // ── Address Generator ──────────────────────────────────

    function generateAddress() {
        var coord = pickRandom(D.coordinates);
        return {
            street: pickRandom(D.streets),
            unit: Math.random() > 0.7 ? 'Unit ' + randomInt(1, 50) : '',
            suburb: pickRandom(D.suburbs),
            town: pickRandom(D.towns),
            province: pickRandom(D.provinces),
            postalCode: pickRandom(D.postalCodes),
            latitude: coord.lat,
            longitude: coord.lng
        };
    }

    // ── Banking Generator ──────────────────────────────────

    function generateBanking(holderName) {
        var bank = pickRandom(D.banks);
        var accNum = '';
        for (var i = 0; i < 10; i++) accNum += randomInt(0, 9);
        return {
            bankName: bank.name,
            branchCode: bank.branchCode,
            accountHolder: holderName,
            accountNumber: accNum,
            accountType: pickRandom(D.accountTypes)
        };
    }

    // ── DOM Helpers ────────────────────────────────────────

    function setFieldValue(selector, value) {
        var el = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!el) {
            console.warn('[DevToolbar] Field not found:', selector);
            return false;
        }
        if (el.readOnly && !el.id.match(/initials/i)) return false; // skip readonly except initials

        var nativeInputValueSetter = Object.getOwnPropertyDescriptor(
            window.HTMLInputElement.prototype, 'value'
        );
        if (nativeInputValueSetter && nativeInputValueSetter.set) {
            nativeInputValueSetter.set.call(el, value);
        } else {
            el.value = value;
        }
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }

    function setSelectValue(selector, value) {
        var el = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!el) {
            console.warn('[DevToolbar] Select not found:', selector);
            return false;
        }
        el.value = value;
        el.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }

    function pickRandomOption(selector) {
        var el = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!el) {
            console.warn('[DevToolbar] Select not found:', selector);
            return null;
        }
        var options = Array.from(el.options).filter(function (o) {
            return o.value !== '' && !o.disabled;
        });
        if (!options.length) {
            console.warn('[DevToolbar] No options available for:', selector);
            return null;
        }
        var pick = options[Math.floor(Math.random() * options.length)];
        el.value = pick.value;
        el.dispatchEvent(new Event('change', { bubbles: true }));
        return pick.value;
    }

    function setRadioValue(name, value) {
        var radio = document.querySelector('input[name="' + name + '"][value="' + value + '"]');
        if (!radio) {
            console.warn('[DevToolbar] Radio not found:', name, value);
            return false;
        }
        radio.checked = true;
        radio.dispatchEvent(new Event('change', { bubbles: true }));
        // Also trigger click for jQuery handlers
        if (window.jQuery) {
            window.jQuery(radio).trigger('change');
        }
        return true;
    }

    function setCheckbox(selector, checked) {
        var el = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!el) return false;
        el.checked = checked;
        el.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }

    function clickElement(selector) {
        var el = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!el) return false;
        el.click();
        return true;
    }

    function setFileInput(selector, filename, mimeType) {
        var el = typeof selector === 'string' ? document.querySelector(selector) : selector;
        if (!el) {
            console.warn('[DevToolbar] File input not found:', selector);
            return false;
        }
        mimeType = mimeType || 'application/pdf';
        var blob = new Blob(['%PDF-1.4 fake test file'], { type: mimeType });
        var file = new File([blob], filename, { type: mimeType, lastModified: Date.now() });
        var dt = new DataTransfer();
        dt.items.add(file);
        el.files = dt.files;
        el.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }

    // ── Async Helpers ──────────────────────────────────────

    function waitForAjaxIdle(timeout) {
        timeout = timeout || 5000;
        return new Promise(function (resolve) {
            if (!window.jQuery || jQuery.active === 0) {
                setTimeout(resolve, 200);
                return;
            }
            var start = Date.now();
            var check = function () {
                if (jQuery.active === 0) {
                    setTimeout(resolve, 200); // 200ms debounce after last AJAX
                } else if (Date.now() - start < timeout) {
                    setTimeout(check, 100);
                } else {
                    console.warn('[DevToolbar] AJAX timeout after ' + timeout + 'ms');
                    resolve();
                }
            };
            check();
        });
    }

    function waitForOptions(selector, timeout) {
        timeout = timeout || 5000;
        return new Promise(function (resolve) {
            var el = typeof selector === 'string' ? document.querySelector(selector) : selector;
            if (!el) { resolve(); return; }

            var hasOptions = function () {
                return Array.from(el.options).filter(function (o) {
                    return o.value !== '' && !o.textContent.match(/loading|select/i);
                }).length > 0;
            };

            if (hasOptions()) { resolve(); return; }

            var start = Date.now();
            var check = function () {
                if (hasOptions()) {
                    resolve();
                } else if (Date.now() - start < timeout) {
                    setTimeout(check, 150);
                } else {
                    console.warn('[DevToolbar] Options timeout for:', selector);
                    resolve();
                }
            };
            check();
        });
    }

    function delay(ms) {
        return new Promise(function (resolve) { setTimeout(resolve, ms); });
    }

    // ── Public API ─────────────────────────────────────────

    return {
        // Data generators
        pickRandom: pickRandom,
        randomInt: randomInt,
        generatePerson: generatePerson,
        generateAddress: generateAddress,
        generateBanking: generateBanking,
        generateSAID: generateSAID,
        generatePhone: generatePhone,
        generateEmail: generateEmail,
        generateDate: generateDate,
        generateRecentDate: generateRecentDate,
        generateFutureDate: generateFutureDate,
        formatDate: formatDate,

        // DOM helpers
        setFieldValue: setFieldValue,
        setSelectValue: setSelectValue,
        pickRandomOption: pickRandomOption,
        setRadioValue: setRadioValue,
        setCheckbox: setCheckbox,
        clickElement: clickElement,
        setFileInput: setFileInput,

        // Async helpers
        waitForAjaxIdle: waitForAjaxIdle,
        waitForOptions: waitForOptions,
        delay: delay
    };
})();
