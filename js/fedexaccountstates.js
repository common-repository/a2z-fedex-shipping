jQuery(document).ready(function(){
    // Initialize Select2
    jQuery('#hitshippo_fedex_state').select2();
    var dhlexpressStateValue = jQuery('#fedex_state').val();

    // Function to update states based on the selected country
    function updateStates() {
        var countryCode = jQuery('#hitshippo_fedex_country').val();
        jQuery('#hitshippo_fedex_state').empty();

        // Filter states based on the selected country
        var states = states_list.Data.filter(function(state){
            return state.country === countryCode;
        });

        // Add states to the dropdown
        states.forEach(function(state){
            var stateCode = state.code.split('-')[1]; // Get the part after the hyphen
            jQuery('#hitshippo_fedex_state').append('<option value="' + stateCode + '">' + state.name + '</option>');
        });
        
        // Show/hide the hidden input field based on the presence of states
        if (states.length == 0) {
           
            // Enable the hidden field
            jQuery("#fedex_state").css("display", "block");
            jQuery("#hitshippo_fedex_state").css("display", "none");
            jQuery('#hitshippo_fedex_state').select2('destroy');
            // Replace the value (replace 'new_value' with the desired value)
            jQuery("#fedex_state").val(countryCode);
        }
    
    }

    // Bind the updateStates function to the change event of the country dropdown
    jQuery('#hitshippo_fedex_country').change(updateStates).change();
  
    // Set the selected state on page load
    if (dhlexpressStateValue !== '') {
        jQuery('#hitshippo_fedex_state').val(dhlexpressStateValue);
    }
});
