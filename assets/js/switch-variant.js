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
            $('.yourpropfirm-radio-group label').removeClass('selected');
            $(this).next('label').addClass('selected');
            updateCart();
        });

        $('#yourpropfirm-update-cart').on('click', function(e) {
            e.preventDefault();
            updateCart();
        });
    });

    // Apply styling for radio button layout
    const style = `
        .yourpropfirm-radio-group {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            padding: 10px 0px 0px 0px;
        }
        .yourpropfirm-radio-group label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border: 1px solid #ccc;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .yourpropfirm-radio-group input[type="radio"] {
            display: none;
        }
        .yourpropfirm-radio-group input[type="radio"]:checked + label,
        .yourpropfirm-radio-group label.selected {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
    `;

    let styleSheet = document.createElement("style");
    styleSheet.type = "text/css";
    styleSheet.innerText = style;
    document.head.appendChild(styleSheet);

})( jQuery );
