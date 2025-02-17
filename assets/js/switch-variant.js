(function( $ ) {
    'use strict';

    jQuery(document).ready(function($) {
        function updateCart() {
            let selectedAttributes = {};
            $('.yourpropfirm-radio-group input[type="radio"]:checked').each(function() {
                let attribute = $(this).closest('.yourpropfirm-radio-group').data('attribute');
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
                        $(document.body).trigger('update_checkout');
                    } else {
                        alert(response.data.message);
                    }
                },
                complete: function() {
                    $('#yourpropfirm-update-cart').prop('disabled', false).text('Update Cart');
                }
            });
        }

        $('.yourpropfirm-radio-group input[type="radio"]').on('change', function() {
            updateCart();
        });

        $('#yourpropfirm-update-cart').on('click', function(e) {
            e.preventDefault();
            updateCart();
        });
    });

})( jQuery );
