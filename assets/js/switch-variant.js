(function( $ ) {
    'use strict';

    jQuery(document).ready(function($) {
        function updateCart() {
            let selectedAttributes = {};
            $('.yourpropfirm-switch').each(function() {
                let attribute = $(this).data('attribute');
                let value = $(this).val();
                selectedAttributes[attribute] = value;
            });

            $.ajax({
                type: 'POST',
                url: yourpropfirmAjax.ajaxurl,
                data: {
                    action: 'yourpropfirm_update_cart',
                    security: yourpropfirmAjax.nonce,
                    variation_attributes: selectedAttributes
                },
                beforeSend: function() {
                    $('#yourpropfirm-update-cart').prop('disabled', true).text('Updating...');
                },
                success: function(response) {
                    if (response.success) {
                        // Update cart fragments without reloading
                        $(document.body).trigger('wc_fragment_refresh');
                        // Store selected variation in localStorage
                        localStorage.setItem('yourpropfirm_selected_variation', JSON.stringify(selectedAttributes));
                    } else {
                        alert(response.data.message);
                    }
                },
                complete: function() {
                    $('#yourpropfirm-update-cart').prop('disabled', false).text('Update Cart');
                }
            });
        }

        $('.yourpropfirm-switch').on('change', function() {
            updateCart();
        });

        $('#yourpropfirm-update-cart').on('click', function(e) {
            e.preventDefault();
            updateCart();
        });
    });

})( jQuery );