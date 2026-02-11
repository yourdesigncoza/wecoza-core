(function ($) {
    $(function () {
        // Create namespace to avoid conflicts with other plugins
        if (typeof window.WeCozaClients === 'undefined') {
            window.WeCozaClients = {};
        }
        if (typeof window.WeCozaClients.Location === 'undefined') {
            window.WeCozaClients.Location = {
                initialized: false
            };
        }

        // Prevent multiple initialization
        if (window.WeCozaClients.Location.initialized) {
            return;
        }

        var config = window.wecoza_locations || {};
        var container = document.getElementById('wecozaClients_google_address_container');
        var searchInput = document.getElementById('wecozaClients_google_address_search');
        var form = $('.wecoza-clients-form-container form');

        if (!form.length || !container || !searchInput || !config.googleMapsEnabled) {
            return;
        }

        var provinceSelect = form.find('#province');
        var streetAddressInput = form.find('#street_address');
        var suburbInput = form.find('#suburb');
        var townInput = form.find('#town');
        var postalInput = form.find('#postal_code');
        var latitudeInput = form.find('#latitude');
        var longitudeInput = form.find('#longitude');

        var provinceLookup = {};
        if ($.isArray(config.provinces)) {
            config.provinces.forEach(function (province) {
                provinceLookup[province.toLowerCase()] = province;
            });
        }

        // WeCozaClients.Location methods
        window.WeCozaClients.Location.waitForGoogleMaps = function (callback) {
            var attempts = 0;
            var maxAttempts = 60;

            function check() {
                attempts++;

                if (typeof google !== 'undefined' && google.maps && google.maps.places) {
                    callback();
                    return;
                }

                if (attempts >= maxAttempts) {
                    console.error('Google Maps API failed to load in time.');
                    return;
                }

                setTimeout(check, 100);
            }

            check();
        };

        window.WeCozaClients.Location.initializeAutocomplete = function () {
            if (!google.maps.importLibrary) {
                console.error('Google Maps importLibrary not available - please ensure modern Google Maps API is loaded');
                return;
            }

            google.maps.importLibrary('places').then(function (library) {
                if (!library || !library.PlaceAutocompleteElement) {
                    console.error('PlaceAutocompleteElement not available - please ensure modern Google Maps API is loaded');
                    return;
                }
                window.WeCozaClients.Location.initializeNewAutocomplete(library.PlaceAutocompleteElement);
            }).catch(function (error) {
                console.error('Failed to load Google Places library', error);
            });
        };

        window.WeCozaClients.Location.initializeNewAutocomplete = function (PlaceAutocompleteElement) {
            var originalInput = document.getElementById('wecozaClients_google_address_search');
            if (!originalInput) {
                return;
            }

            originalInput.style.display = 'none';
            originalInput.style.visibility = 'hidden';

            var placeAutocomplete = new PlaceAutocompleteElement({
                includedRegionCodes: ['za'],
                requestedLanguage: 'en',
                requestedRegion: 'za'
            });

            placeAutocomplete.className = 'form-control form-control-sm';
            placeAutocomplete.setAttribute('placeholder', originalInput.getAttribute('placeholder') || '');

            container.replaceChild(placeAutocomplete, originalInput);

            placeAutocomplete.addEventListener('gmp-select', function (event) {
                if (!event || !event.placePrediction) {
                    return;
                }

                var place = event.placePrediction.toPlace();

                place.fetchFields({
                    fields: ['addressComponents', 'formattedAddress', 'location']
                }).then(function () {
                    window.WeCozaClients.Location.populateFromPlace(place.addressComponents || [], place.location || null);
                }).catch(function (error) {
                    console.error('Failed fetching place fields', error);
                });
            });
        };

        // Initialize if all required elements exist and config is valid
        if (form.length && container && searchInput && config.googleMapsEnabled) {
            window.WeCozaClients.Location.initialized = true;
            window.WeCozaClients.Location.waitForGoogleMaps(function () {
                window.WeCozaClients.Location.initializeAutocomplete();
            });
        }

        

        window.WeCozaClients.Location.populateFromPlace = function (components, location) {
            var data = {
                streetAddress: '',
                suburb: '',
                town: '',
                province: '',
                postalCode: ''
            };

            var streetNumber = '';
            var route = '';

            components.forEach(function (component) {
                if (!component || !component.types) {
                    return;
                }

                if (component.types.indexOf('street_number') !== -1) {
                    streetNumber = component.longText || component.long_name || '';
                }

                if (component.types.indexOf('route') !== -1) {
                    route = component.longText || component.long_name || '';
                }

                if (component.types.indexOf('sublocality_level_1') !== -1 || component.types.indexOf('sublocality') !== -1 || component.types.indexOf('neighborhood') !== -1) {
                    data.suburb = component.longText || component.long_name || data.suburb;
                }

                if (component.types.indexOf('locality') !== -1 || component.types.indexOf('administrative_area_level_2') !== -1) {
                    data.town = component.longText || component.long_name || data.town;
                }

                if (component.types.indexOf('administrative_area_level_1') !== -1) {
                    data.province = component.longText || component.long_name || data.province;
                }

                if (component.types.indexOf('postal_code') !== -1) {
                    data.postalCode = component.longText || component.long_name || data.postalCode;
                }
            });

            // Combine street number and route for street address
            if (streetNumber && route) {
                data.streetAddress = streetNumber + ' ' + route;
            } else if (route) {
                data.streetAddress = route;
            }

            if (data.streetAddress && streetAddressInput.length) {
                streetAddressInput.val(data.streetAddress).trigger('change');
            }

            if (data.suburb && suburbInput.length) {
                suburbInput.val(data.suburb).trigger('change');
            }

            if (data.town && townInput.length) {
                townInput.val(data.town).trigger('change');
            }

            if (data.postalCode && postalInput.length) {
                postalInput.val(data.postalCode).trigger('change');
            }

            if (data.province && provinceSelect.length) {
                var canonicalProvince = provinceLookup[data.province.toLowerCase()] || '';
                if (!canonicalProvince && provinceLookup[data.province.replace(/\s+/g, '').toLowerCase()]) {
                    canonicalProvince = provinceLookup[data.province.replace(/\s+/g, '').toLowerCase()];
                }

                if (canonicalProvince) {
                    provinceSelect.val(canonicalProvince).trigger('change');
                }
            }

            if (location) {
                var lat = typeof location.lat === 'function' ? location.lat() : location.lat;
                var lng = typeof location.lng === 'function' ? location.lng() : location.lng;

                if (latitudeInput.length && typeof lat === 'number') {
                    latitudeInput.val(lat.toFixed(6));
                }

                if (longitudeInput.length && typeof lng === 'number') {
                    longitudeInput.val(lng.toFixed(6));
                }
            }
        }
    });
})(jQuery);