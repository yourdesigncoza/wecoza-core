(function ($) {
    $(function () {
        if (typeof window.wecozaClients === 'undefined') {
            return;
        }

        var config = window.wecozaClients;
        var input = $('#client_search');
        if (!input.length) {
            return;
        }

        var searchForm = input.closest('form');
        var suggestions = $('<div class="wecoza-clients-search-suggestions list-group mt-1"></div>').insertAfter(input).hide();
        var debounceTimer = null;
        var activeRequest = null;

        var clearSuggestions = function () {
            suggestions.empty().hide();
        };

        var populateSuggestions = function (clients) {
            suggestions.empty();

            if (!clients || !clients.length) {
                suggestions.hide();
                return;
            }

            clients.forEach(function (client) {
                if (!client || !client.client_name) {
                    return;
                }

                var item = $('<button type="button" class="list-group-item list-group-item-action"></button>')
                    .text(client.client_name)
                    .data('client-id', client.id || '')
                    .appendTo(suggestions);

                item.on('click', function () {
                    input.val(client.client_name);
                    clearSuggestions();
                    if (searchForm.length) {
                        searchForm.trigger('submit');
                    }
                });
            });

            suggestions.show();
        };

        var executeSearch = function (query) {
            if (activeRequest) {
                activeRequest.abort();
                activeRequest = null;
            }

            activeRequest = $.ajax({
                url: config.ajaxUrl,
                method: 'GET',
                dataType: 'json',
                data: {
                    action: config.actions.search,
                    nonce: config.nonce,
                    search: query,
                    limit: 8
                }
            }).done(function (response) {
                if (response && response.success) {
                    populateSuggestions((response.data && response.data.clients) || []);
                } else {
                    clearSuggestions();
                }
            }).fail(function () {
                clearSuggestions();
            }).always(function () {
                activeRequest = null;
            });
        };

        input.on('input', function () {
            var value = $.trim(input.val());

            if (!value || value.length < 2) {
                clearSuggestions();
                return;
            }

            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(function () {
                executeSearch(value);
            }, 250);
        });

        input.on('focus', function () {
            if (suggestions.children().length) {
                suggestions.show();
            }
        });

        input.on('blur', function () {
            window.setTimeout(clearSuggestions, 150);
        });
    });
})(jQuery);
