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
                        $(document.body).trigger('wc_fragment_refresh');
                        $(document.body).trigger('update_checkout');
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

        function getQueryParams() {
            let params = {};
            window.location.search.replace(/^[?&]/, '').split('&').forEach(function(param) {
                let parts = param.split('=');
                params[decodeURIComponent(parts[0])] = decodeURIComponent(parts[1] || '');
            });
            return params;
        }

        let urlParams = getQueryParams();
        if (urlParams['add-to-cart']) {
            let variationId = urlParams['add-to-cart'];
            
            $.ajax({
                type: 'GET',
                url: yourpropfirmAjax.ajaxurl,
                data: {
                    action: 'yourpropfirm_get_variation_attributes',
                    variation_id: variationId
                },
                success: function(response) {
                    if (response.success) {
                        $('.yourpropfirm-switch').each(function() {
                            let attribute = $(this).data('attribute');
                            if (response.data[attribute]) {
                                $(this).val(response.data[attribute]);
                            }
                        });
                    }
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
