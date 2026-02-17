(function ($) {
    $(function () {
        if (typeof window.wecozaClients === 'undefined') {
            return;
        }

        var config = window.wecozaClients;
        var form = $('#clients-form');
        if (!form.length || typeof FormData === 'undefined') {
            return;
        }

        var container = form.closest('.wecoza-clients-form-container');
        var submitButton = form.find('button[type="submit"]');
        var feedback = container.find('.wecoza-clients-feedback');

        if (!feedback.length) {
            feedback = $('<div class="wecoza-clients-feedback mt-3"></div>');
            container.prepend(feedback);
        }

        var renderMessage = function (type, message) {
            var classes = 'alert alert-dismissible fade show';
            if (type === 'success') {
                classes += ' alert-subtle-success';
            } else {
                classes += ' alert-subtle-danger';
            }

            feedback.html(
                '<div class="' + classes + '" role="alert">' +
                    '<div>' + message + '</div>' +
                    '<button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>' +
                '</div>'
            );
        };

        var setSubmittingState = function (isSubmitting) {
            if (!submitButton.length) {
                return;
            }

            if (isSubmitting) {
                submitButton.data('original-text', submitButton.text());
                submitButton.prop('disabled', true).text(config.messages.form.saving);
            } else {
                var original = submitButton.data('original-text');
                if (original) {
                    submitButton.text(original);
                }
                submitButton.prop('disabled', false);
            }
        };

        var clearForm = function () {
            // Clear all form fields
            form[0].reset();
            
            // Clear hidden fields
            form.find('input[name="id"]').val('');
            form.find('input[name="head_site_id"]').val('');
            
            // Reset Bootstrap validation state
            form.removeClass('was-validated');
            
            // Reset location dropdowns to initial state
            initializeLocations();
            
            // Clear any custom field values
            form.find('input[type="text"], input[type="email"], input[type="tel"], textarea').each(function () {
                if ($(this).attr('name') !== 'client_town_id') {
                    $(this).val('');
                }
            });
            
            // Reset select elements to first option
            form.find('select').each(function () {
                $(this).prop('selectedIndex', 0);
            });
            
            // Hide conditional fields that might be shown
            hideTownWrapper();
            hideSuburbWrapper();
            hideAddressWrapper(false);
            hidePostalWrapper(true);
            
            // Clear hidden location fields
            clearHiddenLocation();
        };

        var extractErrors = function (errors) {
            if (!errors) {
                return config.messages.form.error;
            }

            if (errors.general) {
                return errors.general;
            }

            var list = [];
            $.each(errors, function (field, message) {
                if (message) {
                    list.push(message);
                }
            });

            return list.length ? list.join('<br>') : config.messages.form.error;
        };

        var hierarchy = (config.locations && $.isArray(config.locations.hierarchy)) ? config.locations.hierarchy : [];
        var locationRequest = null;
        var loadHierarchyIfNeeded = function () {
            if (hierarchy.length) {
                return $.Deferred().resolve(hierarchy).promise();
            }

            if (!config.actions || !config.actions.locations) {
                return $.Deferred().resolve(hierarchy).promise();
            }

            if (locationRequest) {
                return locationRequest;
            }

            locationRequest = $.ajax({
                url: config.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: config.actions.locations,
                    nonce: config.nonce
                }
            }).done(function (response) {
                if (response && response.success && response.data && $.isArray(response.data.hierarchy)) {
                    hierarchy = response.data.hierarchy;
                } else if (response && response.data && response.data.message) {
                    renderMessage('error', response.data.message);
                }
            }).fail(function () {
                renderMessage('error', config.messages.general.error);
            }).always(function () {
                locationRequest = null;
            });

            return locationRequest;
        };
        var provinceSelect = form.find('.js-province-select');
        var townSelect = form.find('.js-town-select');
        var suburbSelect = form.find('.js-suburb-select');
        var townWrapper = form.find('.js-town-field');
        var suburbWrapper = form.find('.js-suburb-field');
        var addressWrapper = form.find('.js-address-field');
        var postalWrapper = form.find('.js-postal-field');
        var addressInput = addressWrapper.find('input[name="client_street_address"]');
        var postalInput = postalWrapper.find('input[name="client_postal_code"]');
        var suburbHidden = form.find('.js-suburb-hidden');
        var townHidden = form.find('.js-town-hidden');
        var initialProvince = provinceSelect.val();
        var initialTown = townSelect.val();
        var initialSuburb = suburbSelect.val();

        var provinceList = [];
        var townsMap = {};
        var suburbsMap = {};
        var toggleRequired = function (element, isRequired) {
            if (!element || !element.length) {
                return;
            }
            if (isRequired) {
                element.attr('required', 'required');
            } else {
                element.removeAttr('required');
            }
        };

        var resetSelect = function (select) {
            if (!select || !select.length) {
                return;
            }
            select.find('option:not(:first)').remove();
            select.prop('selectedIndex', 0);
        };

        var clearHiddenLocation = function () {
            suburbHidden.val('');
            townHidden.val('');
            if (postalInput.length) {
                postalInput.val('');
            }
        };

        var buildIndexes = function () {
            provinceList = [];
            townsMap = {};
            suburbsMap = {};

            $.each(hierarchy, function (_, provinceData) {
                if (!provinceData) {
                    return;
                }

                var provinceName = provinceData.name || '';
                if (!provinceName) {
                    return;
                }

                provinceList.push(provinceName);

                var towns = [];
                if ($.isArray(provinceData.towns)) {
                    $.each(provinceData.towns, function (_, townData) {
                        if (!townData) {
                            return;
                        }

                        var townName = townData.name || '';
                        if (!townName) {
                            return;
                        }

                        towns.push(townName);

                        var key = provinceName + '||' + townName;
                        var suburbs = [];
                        if ($.isArray(townData.suburbs)) {
                            $.each(townData.suburbs, function (_, suburbData) {
                                if (suburbData && suburbData.id) {
                                    suburbs.push(suburbData);
                                }
                            });
                        }
                        suburbsMap[key] = suburbs;
                    });
                }

                townsMap[provinceName] = towns;
            });
        };

        var populateProvinces = function (selectedProvince) {
            resetSelect(provinceSelect);
            $.each(provinceList, function (_, province) {
                provinceSelect.append($('<option>', { value: province, text: province }));
            });

            if (selectedProvince && provinceList.indexOf(selectedProvince) !== -1) {
                provinceSelect.val(selectedProvince);
            }
        };

        var populateTowns = function (province, selectedTown) {
            resetSelect(townSelect);

            var towns = townsMap[province] || [];
            $.each(towns, function (_, town) {
                townSelect.append($('<option>', { value: town, text: town }));
            });

            if (selectedTown && towns.indexOf(selectedTown) !== -1) {
                townSelect.val(selectedTown);
            }
        };

        var populateSuburbs = function (province, town, selectedSuburb) {
            resetSelect(suburbSelect);

            var suburbs = suburbsMap[province + '||' + town] || [];
            $.each(suburbs, function (_, suburb) {
                var displayText = suburb.name || '';
                if (suburb.street_address) {
                    displayText += ' - ' + suburb.street_address;
                }

                var option = $('<option>', {
                    value: suburb.id,
                    text: displayText
                });

                option.data('postal_code', suburb.postal_code || '');
                option.data('suburb', suburb.name || '');
                option.data('town', town || '');
                option.data('province', province || '');
                option.data('street_address', suburb.street_address || '');

                suburbSelect.append(option);
            });

            if (selectedSuburb && suburbSelect.find('option[value="' + selectedSuburb + '"]').length) {
                suburbSelect.val(selectedSuburb);
            }
        };

        var showTownWrapper = function () {
            townWrapper.removeClass('d-none');
            toggleRequired(townSelect, true);
        };

        var hideTownWrapper = function () {
            townWrapper.addClass('d-none');
            toggleRequired(townSelect, false);
            resetSelect(townSelect);
        };

        var showSuburbWrapper = function () {
            suburbWrapper.removeClass('d-none');
            toggleRequired(suburbSelect, true);
        };

        var hideSuburbWrapper = function () {
            suburbWrapper.addClass('d-none');
            toggleRequired(suburbSelect, false);
            resetSelect(suburbSelect);
        };

        var showAddressWrapper = function () {
            addressWrapper.removeClass('d-none');
            toggleRequired(addressInput, true);
        };

        var hideAddressWrapper = function (clearValue) {
            addressWrapper.addClass('d-none');
            toggleRequired(addressInput, false);
            if (clearValue && addressInput.length) {
                addressInput.val('');
                addressInput.prop('readonly', false);
                addressInput.removeAttr('title');
            }
        };

        var showPostalWrapper = function () {
            postalWrapper.removeClass('d-none');
            toggleRequired(postalInput, true);
        };

        var hidePostalWrapper = function (clearValue) {
            postalWrapper.addClass('d-none');
            toggleRequired(postalInput, false);
            if (clearValue && postalInput.length) {
                postalInput.val('');
            }
        };

        var handleSuburbChange = function (isInitial) {
            if (!suburbSelect.length) {
                return;
            }

            var locationId = suburbSelect.val();
            
            if (!locationId) {
                clearHiddenLocation();
                hideAddressWrapper(true);
            }

            if (!locationId) {
                hidePostalWrapper(true);
                return;
            }

            var option = suburbSelect.find('option:selected');
            var suburbName = option.data('suburb') || option.text();
            var postalCode = option.data('postal_code') || '';
            var streetAddress = option.data('street_address') || '';
            var townName = option.data('town') || (townSelect.val() || '');

            suburbHidden.val(suburbName);
            townHidden.val(townName);
            if (postalInput.length) {
                postalInput.val(postalCode);
            }

            // Auto-populate street address fields from location data
            if (addressInput.length && streetAddress) {
                addressInput.val(streetAddress);
                addressInput.prop('readonly', true);
                addressInput.attr('title', 'Address auto-populated from location data');
            } else if (addressInput.length) {
                addressInput.prop('readonly', false);
                addressInput.removeAttr('title');
            }

            showPostalWrapper();
            showAddressWrapper();

            if (!isInitial && addressInput.length && !addressInput.val() && !streetAddress) {
                addressInput.focus();
            }
        };

        var refreshAfterTown = function (province, town, selectedSuburb) {
            clearHiddenLocation();
            hideAddressWrapper(true);
            hidePostalWrapper(true);

            if (!province || !town || !suburbsMap[province + '||' + town] || !suburbsMap[province + '||' + town].length) {
                hideSuburbWrapper();
                return;
            }

            showSuburbWrapper();
            populateSuburbs(province, town, selectedSuburb);

            if (suburbSelect.val()) {
                handleSuburbChange(true);
            } else {
                hideAddressWrapper(false);
                hidePostalWrapper(true);
            }
        };

        var refreshAfterProvince = function (province, selectedTown, selectedSuburb) {
            clearHiddenLocation();
            hideAddressWrapper(true);
            hidePostalWrapper(true);
            hideSuburbWrapper();
            if (!province || provinceList.indexOf(province) === -1) {
                hideTownWrapper();
                return;
            }

            var hasTowns = townsMap[province] && townsMap[province].length;
            if (!hasTowns) {
                hideTownWrapper();
                return;
            }

            showTownWrapper();
            populateTowns(province, selectedTown);

            var currentTown = townSelect.val();
            if (!currentTown) {
                hideSuburbWrapper();
                return;
            }

            refreshAfterTown(province, currentTown, selectedSuburb);
        };

        var handleTownChange = function () {
            refreshAfterTown(provinceSelect.val(), townSelect.val(), null);
        };

        var handleProvinceChange = function () {
            refreshAfterProvince(provinceSelect.val(), null, null);
        };

        var initializeLocations = function () {
            buildIndexes();
            populateProvinces(initialProvince);

            if (!provinceSelect.length) {
                return;
            }

            if (!provinceSelect.val()) {
                toggleRequired(townSelect, false);
                toggleRequired(suburbSelect, false);
                toggleRequired(addressInput, false);
                toggleRequired(postalInput, false);
                hideTownWrapper();
                hideSuburbWrapper();
                hideAddressWrapper(false);
                hidePostalWrapper(true);
                return;
            }

            refreshAfterProvince(provinceSelect.val(), initialTown, initialSuburb);

            if (suburbSelect.val()) {
                handleSuburbChange(true);
            }

            initialProvince = null;
            initialTown = null;
            initialSuburb = null;
        };

        if (provinceSelect.length) {
            provinceSelect.on('change', handleProvinceChange);
        }

        if (townSelect.length) {
            townSelect.on('change', handleTownChange);
        }

        if (suburbSelect.length) {
            suburbSelect.on('change', function () {
                handleSuburbChange(false);
            });
        }

        initializeLocations();

        if (config.locations && config.locations.lazyLoad && !hierarchy.length) {
            loadHierarchyIfNeeded().done(function () {
                initializeLocations();
            });
        }

        var scrollToFeedback = function () {
            if (container.length) {
                $('html, body').animate({ scrollTop: container.offset().top - 80 }, 400);
            }
        };

        form.on('submit', function (event) {
            if (!form[0].checkValidity()) {
                form.addClass('was-validated');
                return;
            }

            event.preventDefault();
            form.addClass('was-validated');

            var formData = new FormData(form[0]);

            formData.append('action', config.actions.save);
            formData.append('nonce', config.nonce);

            setSubmittingState(true);

            $.ajax({
                url: config.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json'
            }).done(function (response) {
                if (response && response.success) {
                    var data = response.data || {};
                    var message = data.message || config.messages.form.saved;
                    renderMessage('success', message);
                    scrollToFeedback();

                    // Remove validation state to clear tick marks
                    form.removeClass('was-validated');

                    // Check if this was a new client and form should be cleared
                    var isNewClient = !form.find('input[name="id"]').val();

                    if (data.client && data.client.id) {
                        if (isNewClient && config.clear_form_on_success) {
                            // NEW CLIENT: clear immediately, auto-dismiss banner after 5 seconds
                            clearForm();
                            form.find('input[name="id"]').remove();
                            form.find('input[name="head_site_id"]').val('');
                            setTimeout(function () {
                                feedback.fadeOut(300, function () {
                                    $(this).empty().show();
                                });
                            }, 5000);
                        } else if (isNewClient) {
                            // NEW CLIENT but clear_form_on_success is false: set id so form becomes edit mode
                            var idInput = form.find('input[name="id"]');
                            if (!idInput.length) {
                                idInput = $('<input>', { type: 'hidden', name: 'id' }).appendTo(form);
                            }
                            idInput.val(data.client.id);

                            if (data.client.head_site && data.client.head_site.site_id) {
                                form.find('input[name="head_site_id"]').val(data.client.head_site.site_id);
                                if (data.client.head_site.site_name && !form.find('input[name="site_name"]').val()) {
                                    form.find('input[name="site_name"]').val(data.client.head_site.site_name);
                                }
                            }
                        } else {
                            // UPDATE: reload page after brief delay to show saved data
                            setTimeout(function () {
                                window.location.reload();
                            }, 1500);
                        }
                    }

                    form.trigger('wecoza:client-saved', [response]);
                } else if (response && response.data && response.data.errors) {
                    renderMessage('error', extractErrors(response.data.errors));
                    scrollToFeedback();
                } else {
                    renderMessage('error', config.messages.form.error);
                    scrollToFeedback();
                }
            }).fail(function (xhr, status, error) {
                renderMessage('error', config.messages.form.error);
                scrollToFeedback();
            }).always(function () {
                setSubmittingState(false);
            });
        });
    });
})(jQuery);
