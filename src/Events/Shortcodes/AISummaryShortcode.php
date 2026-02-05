<?php
declare(strict_types=1);

namespace WeCoza\Events\Shortcodes;

use RuntimeException;
use WeCoza\Core\Helpers\AjaxSecurity;
use WeCoza\Events\Services\NotificationDashboardService;
use WeCoza\Events\Support\WordPressRequest;
use WeCoza\Events\Views\Presenters\AISummaryPresenter;
use WeCoza\Events\Views\TemplateRenderer;

use function absint;
use function add_action;
use function add_shortcode;
use function check_ajax_referer;
use function current_user_can;
use function esc_html;
use function esc_html__;
use function filter_input;
use function shortcode_atts;
use function sprintf;
use function strtolower;
use function trim;
use function uniqid;
use function wp_create_nonce;
use function wp_send_json_error;
use function wp_send_json_success;

final class AISummaryShortcode
{
    private const DEFAULT_LIMIT = 20;
    private const LAYOUT_CARD = 'card';
    private const LAYOUT_TIMELINE = 'timeline';
    private const NONCE_ACTION = 'wecoza_notification_nonce';

    private NotificationDashboardService $service;
    private AISummaryPresenter $presenter;
    private TemplateRenderer $renderer;
    private WordPressRequest $request;
    private bool $assetsPrinted = false;

    public function __construct(
        ?NotificationDashboardService $service = null,
        ?AISummaryPresenter $presenter = null,
        ?TemplateRenderer $renderer = null,
        ?WordPressRequest $request = null
    ) {
        $this->service = $service ?? NotificationDashboardService::boot();
        $this->presenter = $presenter ?? new AISummaryPresenter();
        $this->renderer = $renderer ?? new TemplateRenderer();
        $this->request = $request ?? new WordPressRequest();
    }

    public static function register(?self $shortcode = null): void
    {
        $instance = $shortcode ?? new self();
        add_shortcode('wecoza_insert_update_ai_summary', [$instance, 'render']);

        add_action('wp_ajax_wecoza_mark_notification_viewed', [$instance, 'ajaxMarkViewed']);
        add_action('wp_ajax_wecoza_mark_notification_acknowledged', [$instance, 'ajaxMarkAcknowledged']);
    }

    public function render(array $atts = [], string $content = '', string $tag = ''): string
    {
        $atts = shortcode_atts([
            'limit' => self::DEFAULT_LIMIT,
            'layout' => self::LAYOUT_CARD,
            'class_id' => null,
            'operation' => null,
            'unread_only' => 'false',
            'show_filters' => 'true',
        ], $atts, $tag);

        $limit = absint($atts['limit']);
        if ($limit <= 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        $layout = strtolower(trim($atts['layout']));
        if (!in_array($layout, [self::LAYOUT_CARD, self::LAYOUT_TIMELINE], true)) {
            $layout = self::LAYOUT_CARD;
        }

        $classId = $atts['class_id'] !== null ? absint($atts['class_id']) : null;
        if ($classId !== null && $classId <= 0) {
            $classId = null;
        }

        $unreadOnly = filter_var($atts['unread_only'], FILTER_VALIDATE_BOOLEAN);
        $showFilters = filter_var($atts['show_filters'], FILTER_VALIDATE_BOOLEAN);

        $operation = $atts['operation'] !== null ? strtoupper(trim($atts['operation'])) : null;
        if ($operation !== null && !in_array($operation, ['INSERT', 'UPDATE'], true)) {
            $operation = null;
        }

        try {
            if ($classId !== null) {
                $events = $this->service->getByEntity('class', $classId, $limit);
            } else {
                $events = $this->service->getTimeline($limit, null, $unreadOnly);
            }

            if ($operation !== null) {
                $operationMap = [
                    'INSERT' => 'CLASS_INSERT',
                    'UPDATE' => 'CLASS_UPDATE',
                ];
                $eventTypeFilter = $operationMap[$operation] ?? null;
                if ($eventTypeFilter !== null) {
                    $events = array_filter($events, fn($e) => $e->eventType->value === $eventTypeFilter);
                    $events = array_values($events);
                }
            }

            $records = $this->service->transformManyForDisplay($events);
        } catch (RuntimeException $exception) {
            return $this->wrapMessage(
                sprintf(
                    esc_html__('Unable to load notifications: %s', 'wecoza-events'),
                    esc_html($exception->getMessage())
                )
            );
        }

        if ($records === []) {
            return $this->wrapMessage(esc_html__('No notifications available.', 'wecoza-events'));
        }

        $summaries = $this->presenter->present($records);
        $instanceId = uniqid('wecoza-ai-summary-');
        $unreadCount = $this->service->getUnreadCount();

        return $this->renderer->render('ai-summary/main', [
            'assets' => $this->getAssets(),
            'summaries' => $summaries,
            'layout' => $layout,
            'instanceId' => $instanceId,
            'searchInputId' => $instanceId . '-search',
            'operationFilterId' => $instanceId . '-operation',
            'unreadFilterId' => $instanceId . '-unread',
            'classIdFilter' => $classId,
            'operationFilter' => $operation,
            'unreadOnly' => $unreadOnly,
            'showFilters' => $showFilters,
            'unreadCount' => $unreadCount,
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
        ]);
    }

    /**
     * AJAX handler for marking notification as viewed
     */
    public function ajaxMarkViewed(): void
    {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
            return;
        }

        if (!current_user_can('read')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
            return;
        }

        $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
        if ($eventId === null || $eventId === false || $eventId <= 0) {
            wp_send_json_error(['message' => 'Invalid event ID'], 400);
            return;
        }

        $success = $this->service->markAsViewed($eventId);

        if ($success) {
            wp_send_json_success([
                'message' => 'Notification marked as viewed',
                'event_id' => $eventId,
                'unread_count' => $this->service->getUnreadCount(),
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to mark notification as viewed'], 500);
        }
    }

    /**
     * AJAX handler for marking notification as acknowledged
     */
    public function ajaxMarkAcknowledged(): void
    {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
            return;
        }

        if (!current_user_can('read')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
            return;
        }

        $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
        if ($eventId === null || $eventId === false || $eventId <= 0) {
            wp_send_json_error(['message' => 'Invalid event ID'], 400);
            return;
        }

        $viewedSuccess = $this->service->markAsViewed($eventId);
        $ackSuccess = $this->service->markAsAcknowledged($eventId);

        if ($ackSuccess) {
            wp_send_json_success([
                'message' => 'Notification acknowledged',
                'event_id' => $eventId,
                'unread_count' => $this->service->getUnreadCount(),
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to acknowledge notification'], 500);
        }
    }

    private function wrapMessage(string $message): string
    {
        return '<div class="alert alert-warning" role="alert">' . $message . '</div>';
    }

    private function getAssets(): string
    {
        if ($this->assetsPrinted) {
            return '';
        }

        $this->assetsPrinted = true;

        ob_start();
        ?>
        <script>
            (function() {
                function normaliseSearch(value) {
                    return String(value || '')
                        .toLowerCase()
                        .replace(/\s+/g, ' ')
                        .trim();
                }

                function applyFilters(container) {
                    if (!container) {
                        return;
                    }

                    var searchInput = container.querySelector('[data-role="ai-search"]');
                    var operationSelect = container.querySelector('[data-role="operation-filter"]');
                    var unreadToggle = container.querySelector('[data-role="unread-filter"]');
                    var status = container.querySelector('[data-role="filter-status"]');
                    var noResultsEl = container.querySelector('[data-role="no-results"]');

                    var searchTerm = searchInput ? normaliseSearch(searchInput.value) : '';
                    var selectedOperation = operationSelect ? operationSelect.value : '';
                    var unreadOnly = unreadToggle ? unreadToggle.checked : false;
                    var filtersActive = searchTerm !== '' || selectedOperation !== '' || unreadOnly;

                    var items = container.querySelectorAll('[data-role="summary-item"]');
                    var total = items.length;
                    var visible = 0;

                    items.forEach(function(item) {
                        var matches = true;
                        var searchIndex = item.getAttribute('data-search-index') || '';
                        var itemOperation = item.getAttribute('data-operation') || '';
                        var isRead = item.getAttribute('data-is-read') === '1';

                        if (searchTerm !== '') {
                            matches = searchIndex.indexOf(searchTerm) !== -1;
                        }

                        if (matches && selectedOperation !== '') {
                            matches = itemOperation === selectedOperation;
                        }

                        if (matches && unreadOnly) {
                            matches = !isRead;
                        }

                        if (matches) {
                            item.removeAttribute('hidden');
                            visible += 1;
                        } else {
                            item.setAttribute('hidden', 'hidden');
                        }
                    });

                    if (noResultsEl) {
                        if (visible === 0 && total > 0) {
                            noResultsEl.removeAttribute('hidden');
                        } else {
                            noResultsEl.setAttribute('hidden', 'hidden');
                        }
                    }

                    if (status) {
                        if (!filtersActive) {
                            status.setAttribute('hidden', 'hidden');
                        } else {
                            var message;
                            if (visible === 0) {
                                message = status.getAttribute('data-empty-message') || 'No matches found';
                            } else {
                                message = 'Showing ' + visible + ' of ' + total + ' notifications';
                            }

                            status.textContent = message;
                            status.className = 'badge badge-phoenix badge-phoenix-primary text-uppercase fs-9 mb-3';
                            status.removeAttribute('hidden');
                        }
                    }
                }

                function markAsViewed(container, eventId) {
                    var nonce = container.getAttribute('data-nonce');
                    if (!nonce || !eventId) return;

                    var formData = new FormData();
                    formData.append('action', 'wecoza_mark_notification_viewed');
                    formData.append('nonce', nonce);
                    formData.append('event_id', eventId);

                    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.success) {
                            var item = container.querySelector('[data-event-id="' + eventId + '"]');
                            if (item) {
                                item.setAttribute('data-is-read', '1');
                                item.classList.remove('notification-unread');
                                item.classList.add('notification-read');
                            }
                            updateUnreadBadge(container, data.data.unread_count);
                        }
                    })
                    .catch(function(error) {
                        console.error('Failed to mark notification as viewed:', error);
                    });
                }

                function markAsAcknowledged(container, eventId) {
                    var nonce = container.getAttribute('data-nonce');
                    if (!nonce || !eventId) return;

                    var formData = new FormData();
                    formData.append('action', 'wecoza_mark_notification_acknowledged');
                    formData.append('nonce', nonce);
                    formData.append('event_id', eventId);

                    fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.success) {
                            var item = container.querySelector('[data-event-id="' + eventId + '"]');
                            if (item) {
                                item.setAttribute('data-is-read', '1');
                                item.classList.remove('notification-unread');
                                item.classList.add('notification-read', 'notification-acknowledged');
                                var ackBtn = item.querySelector('[data-role="acknowledge-btn"]');
                                if (ackBtn) {
                                    ackBtn.setAttribute('disabled', 'disabled');
                                    ackBtn.textContent = 'Acknowledged';
                                }
                            }
                            updateUnreadBadge(container, data.data.unread_count);
                        }
                    })
                    .catch(function(error) {
                        console.error('Failed to acknowledge notification:', error);
                    });
                }

                function updateUnreadBadge(container, count) {
                    var badge = container.querySelector('[data-role="unread-count"]');
                    if (badge) {
                        badge.textContent = count;
                        if (count === 0) {
                            badge.setAttribute('hidden', 'hidden');
                        } else {
                            badge.removeAttribute('hidden');
                        }
                    }
                }

                function initFilters(container) {
                    if (!container || container.dataset.filtersInitialised === '1') {
                        return;
                    }

                    container.dataset.filtersInitialised = '1';

                    var searchInput = container.querySelector('[data-role="ai-search"]');
                    var operationSelect = container.querySelector('[data-role="operation-filter"]');
                    var unreadToggle = container.querySelector('[data-role="unread-filter"]');
                    var form = container.querySelector('[data-role="ai-filter-form"]');

                    var handler = function() {
                        applyFilters(container);
                    };

                    if (form) {
                        form.addEventListener('submit', function(event) {
                            event.preventDefault();
                            handler();
                        });
                    }

                    if (searchInput) {
                        searchInput.addEventListener('input', handler);
                    }

                    if (operationSelect) {
                        operationSelect.addEventListener('change', handler);
                    }

                    if (unreadToggle) {
                        unreadToggle.addEventListener('change', handler);
                    }

                    container.addEventListener('click', function(event) {
                        var target = event.target;

                        if (target.hasAttribute('data-role') && target.getAttribute('data-role') === 'acknowledge-btn') {
                            var eventId = target.getAttribute('data-event-id');
                            if (eventId) {
                                markAsAcknowledged(container, eventId);
                            }
                            return;
                        }

                        var summaryItem = target.closest('[data-role="summary-item"]');
                        if (summaryItem) {
                            var isRead = summaryItem.getAttribute('data-is-read') === '1';
                            if (!isRead) {
                                var eventId = summaryItem.getAttribute('data-event-id');
                                if (eventId) {
                                    markAsViewed(container, eventId);
                                }
                            }
                        }
                    });

                    handler();
                }

                function ready(callback) {
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', function onReady() {
                            document.removeEventListener('DOMContentLoaded', onReady);
                            callback();
                        });
                    } else {
                        callback();
                    }
                }

                ready(function() {
                    document.querySelectorAll('.wecoza-ai-summary-wrapper').forEach(initFilters);
                });
            })();
        </script>
        <?php

        return trim((string) ob_get_clean());
    }
}
