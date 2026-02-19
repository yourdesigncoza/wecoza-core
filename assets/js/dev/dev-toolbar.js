/**
 * WeCoza Dev Toolbar
 *
 * Floating toolbar for auto-filling forms during development.
 * Only loaded when WP_DEBUG is true.
 */
(function () {
    'use strict';

    var Gen = window.WeCozaDevGen;
    var Fillers = window.WeCozaDevFillers = window.WeCozaDevFillers || {};
    var config = window.wecoza_dev_toolbar || {};

    // ── Form Detection Map ─────────────────────────────────
    var FORM_MAP = {
        // Location forms
        '.wecoza-clients-form-container form[action*="locations"], .wecoza-clients-form-container form:has(#street_address):has(#latitude)': 'location',
        // Client forms
        '#clients-form': 'client',
        // Learner forms
        '#learners-form': 'learner',
        // Agent forms
        '#agents-form': 'agent',
        // Class forms
        '#classes-form': 'class',
    };

    // ── Detect Current Form ────────────────────────────────

    function detectForm() {
        for (var selector in FORM_MAP) {
            try {
                var el = document.querySelector(selector);
                if (el) {
                    return { type: FORM_MAP[selector], element: el };
                }
            } catch (e) {
                // :has() not supported — fallback
            }
        }

        // Fallback detection for location form (no :has support)
        var locForm = document.querySelector('.wecoza-clients-form-container form');
        if (locForm && locForm.querySelector('#latitude')) {
            return { type: 'location', element: locForm };
        }

        return null;
    }

    // ── Create Toolbar DOM ─────────────────────────────────

    function createToolbar() {
        var detected = detectForm();
        var formLabel = detected ? detected.type.charAt(0).toUpperCase() + detected.type.slice(1) + ' form' : 'No WeCoza form';

        var toolbar = document.createElement('div');
        toolbar.id = 'wecoza-dev-toolbar';
        toolbar.innerHTML =
            '<div class="wdt-header">' +
                '<span class="wdt-title">WeCoza Dev Tools</span>' +
                '<button class="wdt-toggle" title="Collapse">_</button>' +
            '</div>' +
            '<div class="wdt-body">' +
                '<div class="wdt-buttons">' +
                    '<button class="wdt-btn wdt-btn-fill" ' + (detected ? '' : 'disabled') + '>Fill</button>' +
                    '<button class="wdt-btn wdt-btn-fill-submit" ' + (detected ? '' : 'disabled') + '>Fill + Submit</button>' +
                    '<button class="wdt-btn wdt-btn-wipe">Wipe All</button>' +
                '</div>' +
                '<div class="wdt-status">' + formLabel + ' detected</div>' +
            '</div>';

        // Inline styles
        toolbar.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:999999;' +
            'background:rgba(30,30,40,0.95);color:#e0e0e0;border-radius:8px;' +
            'box-shadow:0 4px 20px rgba(0,0,0,0.4);font-family:-apple-system,BlinkMacSystemFont,sans-serif;' +
            'font-size:13px;min-width:280px;backdrop-filter:blur(10px);';

        var header = toolbar.querySelector('.wdt-header');
        header.style.cssText = 'display:flex;justify-content:space-between;align-items:center;' +
            'padding:8px 12px;background:rgba(255,255,255,0.08);border-radius:8px 8px 0 0;cursor:move;';

        toolbar.querySelector('.wdt-title').style.cssText = 'font-weight:600;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;';

        var toggleBtn = toolbar.querySelector('.wdt-toggle');
        toggleBtn.style.cssText = 'background:none;border:none;color:#e0e0e0;cursor:pointer;font-size:16px;padding:0 4px;';

        var body = toolbar.querySelector('.wdt-body');
        body.style.cssText = 'padding:10px 12px;';

        toolbar.querySelector('.wdt-buttons').style.cssText = 'display:flex;gap:6px;margin-bottom:8px;';

        var buttons = toolbar.querySelectorAll('.wdt-btn');
        buttons.forEach(function (btn) {
            btn.style.cssText = 'padding:5px 10px;border:none;border-radius:4px;cursor:pointer;' +
                'font-size:12px;font-weight:500;transition:opacity 0.2s;';
            if (btn.disabled) btn.style.opacity = '0.4';
        });

        toolbar.querySelector('.wdt-btn-fill').style.background = '#3b82f6';
        toolbar.querySelector('.wdt-btn-fill').style.color = '#fff';
        toolbar.querySelector('.wdt-btn-fill-submit').style.background = '#22c55e';
        toolbar.querySelector('.wdt-btn-fill-submit').style.color = '#fff';
        toolbar.querySelector('.wdt-btn-wipe').style.background = '#ef4444';
        toolbar.querySelector('.wdt-btn-wipe').style.color = '#fff';

        toolbar.querySelector('.wdt-status').style.cssText = 'font-size:11px;color:#9ca3af;';

        document.body.appendChild(toolbar);

        // ── Toggle collapse ────────────────────────────────
        var collapsed = false;
        toggleBtn.addEventListener('click', function () {
            collapsed = !collapsed;
            body.style.display = collapsed ? 'none' : 'block';
            header.style.borderRadius = collapsed ? '8px' : '8px 8px 0 0';
            toggleBtn.textContent = collapsed ? '+' : '_';
        });

        // ── Make draggable ─────────────────────────────────
        var isDragging = false, dragX, dragY;
        header.addEventListener('mousedown', function (e) {
            isDragging = true;
            dragX = e.clientX - toolbar.getBoundingClientRect().left;
            dragY = e.clientY - toolbar.getBoundingClientRect().top;
            toolbar.style.transition = 'none';
        });
        document.addEventListener('mousemove', function (e) {
            if (!isDragging) return;
            toolbar.style.left = (e.clientX - dragX) + 'px';
            toolbar.style.top = (e.clientY - dragY) + 'px';
            toolbar.style.right = 'auto';
            toolbar.style.bottom = 'auto';
        });
        document.addEventListener('mouseup', function () { isDragging = false; });

        // ── Button handlers ────────────────────────────────
        toolbar.querySelector('.wdt-btn-fill').addEventListener('click', function () {
            if (!detected) return;
            fillForm(detected, toolbar);
        });

        toolbar.querySelector('.wdt-btn-fill-submit').addEventListener('click', function () {
            if (!detected) return;
            fillAndSubmit(detected, toolbar);
        });

        toolbar.querySelector('.wdt-btn-wipe').addEventListener('click', function () {
            wipeData(toolbar);
        });

        return toolbar;
    }

    // ── Fill Form ──────────────────────────────────────────

    function setStatus(toolbar, msg, type) {
        var status = toolbar.querySelector('.wdt-status');
        status.textContent = msg;
        status.style.color = type === 'error' ? '#ef4444' : type === 'success' ? '#22c55e' : '#9ca3af';
    }

    async function fillForm(detected, toolbar) {
        var filler = Fillers[detected.type];
        if (!filler) {
            setStatus(toolbar, 'No filler for: ' + detected.type, 'error');
            return;
        }

        setStatus(toolbar, 'Filling ' + detected.type + ' form...', 'info');

        try {
            await filler.fill(detected.element);
            setStatus(toolbar, detected.type + ' form filled!', 'success');
        } catch (err) {
            console.error('[DevToolbar] Fill error:', err);
            setStatus(toolbar, 'Fill error: ' + err.message, 'error');
        }
    }

    async function fillAndSubmit(detected, toolbar) {
        await fillForm(detected, toolbar);

        // Wait a beat for any final DOM updates
        await Gen.delay(500);

        setStatus(toolbar, 'Submitting...', 'info');

        var filler = Fillers[detected.type];
        if (filler && filler.submit) {
            filler.submit(detected.element);
        } else {
            // Default: find and click the submit button
            var submitBtn = detected.element.querySelector(
                'button[type="submit"]:not(.d-none):not([style*="display: none"]):not([style*="display:none"])'
            );
            if (submitBtn) {
                submitBtn.click();
            } else {
                setStatus(toolbar, 'Submit button not found', 'error');
            }
        }
    }

    // ── Wipe Data ──────────────────────────────────────────

    function wipeData(toolbar) {
        var confirmed = window.confirm(
            'This will DELETE ALL WeCoza transactional data and reset IDs.\n' +
            'Reference tables (locations, class_types, class_type_subjects) will be preserved.\n\n' +
            'This cannot be undone. Continue?'
        );

        if (!confirmed) return;

        setStatus(toolbar, 'Wiping data...', 'info');

        var formData = new FormData();
        formData.append('action', 'wecoza_dev_wipe_data');
        formData.append('nonce', config.nonce || '');

        fetch(config.ajaxUrl || '/wp-admin/admin-ajax.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                setStatus(toolbar, data.data.message, 'success');
                setTimeout(function () { window.location.reload(); }, 2000);
            } else {
                setStatus(toolbar, 'Wipe failed: ' + (data.data.message || 'Unknown error'), 'error');
            }
        })
        .catch(function (err) {
            setStatus(toolbar, 'Wipe error: ' + err.message, 'error');
        });
    }

    // ── Init ───────────────────────────────────────────────

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createToolbar);
    } else {
        // DOM already ready — but wait a tick for AJAX-loaded forms
        setTimeout(createToolbar, 500);
    }
})();
