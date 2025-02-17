(function( $ ) {
    'use strict';

    jQuery(document).ready(function($) {
        $('#yourpropfirm-update-cart').on('click', function(e) {
            e.preventDefault();
            
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
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                },
                complete: function() {
                    $('#yourpropfirm-update-cart').prop('disabled', false).text('Update Cart');
                }
            });
        });
    });

})( jQuery );
