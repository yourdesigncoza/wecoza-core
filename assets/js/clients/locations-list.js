/* Locations list search with Google Places autocomplete */
(function($){
  $(function(){
    var $input = $('#location-search-input');
    if (!$input.length) return;

    var submitForm = function(){
      var $form = $input.closest('form');
      if ($form.length) {
        $form.trigger('submit');
      }
    };

    if (window.wecoza_locations_list && window.wecoza_locations_list.googleMapsEnabled && window.google && google.maps && google.maps.places) {
      try {
        var autocomplete = new google.maps.places.Autocomplete($input.get(0), {
          fields: ['address_components', 'formatted_address'],
          types: ['geocode']
        });
        autocomplete.addListener('place_changed', function(){
          var place = autocomplete.getPlace();
          if (!place) return submitForm();
          var comps = place.address_components || [];
          var get = function(type){
            for (var i=0;i<comps.length;i++){
              if (comps[i].types && comps[i].types.indexOf(type) !== -1) return comps[i].long_name || comps[i].short_name || '';
            }
            return '';
          };
          var streetNum = get('street_number');
          var route = get('route');
          var street = (streetNum ? streetNum + ' ' : '') + route;
          var suburb = get('sublocality') || get('neighborhood') || get('sublocality_level_1');
          var town = get('locality');
          var province = get('administrative_area_level_1');
          var postal = get('postal_code');
          var parts = [street, suburb, town, province, postal].filter(function(s){ return !!s; });
          if (!parts.length && place.formatted_address) parts = [place.formatted_address];
          $input.val(parts.join(', '));
          submitForm();
        });
      } catch(e) {
        // fail silently
      }
    }
  });
})(jQuery);
