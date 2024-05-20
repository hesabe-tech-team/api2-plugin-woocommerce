// jQuery(document).ready(function($) {
//     function toggleDirectOptions() {
//         if ($('#woocommerce_hesabe_direct').is(':checked')) {
//             $('.direct-toggle').prop('checked', true).closest('tr').show();
//         } else {
//             $('.direct-toggle').prop('checked', false).closest('tr').hide();
//         }
//     }

//     // Initial toggle based on the current state
//     toggleDirectOptions();

//     // Toggle options when the direct checkbox is clicked
//     $('#woocommerce_hesabe_direct').change(function() {
//         toggleDirectOptions();
//     });
// });

jQuery(document).ready(function($) {
    function toggleDirectOptions() {
        if ($('#woocommerce_hesabe_direct').is(':checked')) {
            $('.direct-toggle').closest('tr').show();
        } else {
            $('.direct-toggle').closest('tr').hide();
        }
    }

    // Initial toggle based on the current state
    toggleDirectOptions();

    // Toggle options when the direct checkbox is clicked
    $('#woocommerce_hesabe_direct').change(function() {
        toggleDirectOptions();
    });
});
