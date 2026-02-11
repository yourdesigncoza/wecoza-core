(function ($) {
    $(function () {
        if (typeof window.wecozaClients === 'undefined') {
            return;
        }

        var config = window.wecozaClients;
        var displayRoot = $('.wecoza-clients-display');
        var noticeContainer = displayRoot.find('.wecoza-clients-display-feedback');

        if (displayRoot.length && !noticeContainer.length) {
            noticeContainer = $('<div class="wecoza-clients-display-feedback mb-3"></div>');
            displayRoot.prepend(noticeContainer);
        }

        var renderNotice = function (type, message) {
            if (!noticeContainer || !noticeContainer.length || !message) {
                return;
            }

            var classes = 'alert alert-dismissible fade show';
            switch (type) {
                case 'success':
                    classes += ' alert-subtle-success';
                    break;
                case 'info':
                    classes += ' alert-subtle-primary';
                    break;
                default:
                    classes += ' alert-subtle-danger';
            }

            noticeContainer.html(
                '<div class="' + classes + '" role="alert">' +
                    '<div>' + message + '</div>' +
                    '<button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>' +
                '</div>'
            );
        };

        var exportButton = $('#wecoza-clients-export');

        if (exportButton.length) {
            exportButton.on('click', function (event) {
                event.preventDefault();

                var params = {
                    action: config.actions.export,
                    nonce: config.nonce
                };

                var searchField = $('#client_search');
                var statusField = $('#client_status');
                var setaField = $('#client_seta');

                if (searchField.length && searchField.val()) {
                    params.client_search = searchField.val();
                }

                if (statusField.length && statusField.val()) {
                    params.client_status = statusField.val();
                }

                if (setaField.length && setaField.val()) {
                    params.client_seta = setaField.val();
                }

                var query = $.param(params);
                renderNotice('info', config.messages.list.exporting);
                window.location.href = config.ajaxUrl + (config.ajaxUrl.indexOf('?') === -1 ? '?' : '&') + query;
            });
        }

        $(document).on('click', '[data-client-action="delete"]', function (event) {
            event.preventDefault();

            var trigger = $(this);
            var clientId = trigger.data('client-id');

            if (!clientId) {
                return;
            }

            if (!window.confirm(config.messages.list.confirmDelete)) {
                return;
            }

            renderNotice('info', config.messages.list.deleting);

            $.ajax({
                url: config.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: config.actions.delete,
                    nonce: config.nonce,
                    id: clientId
                }
            }).done(function (response) {
                if (response && response.success) {
                    renderNotice('success', config.messages.list.deleted);
                    var row = trigger.closest('tr');
                    if (row.length) {
                        row.fadeOut(200, function () {
                            $(this).remove();
                        });
                    }
                    $(document).trigger('wecoza:client-deleted', [clientId, response]);
                } else {
                    renderNotice('error', config.messages.list.error);
                }
            }).fail(function () {
                renderNotice('error', config.messages.list.error);
            });
        });
    });
})(jQuery);
