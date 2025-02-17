<?php
/*
Plugin Name: YourPropFirm Switch Variant
Description: Allow users to switch variations on the checkout page for all variable products in cart and update cart automatically.
Version: 1.0
Author: Your Name
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'YourPropFirm_Switch_Variant' ) ) {

    class YourPropFirm_Switch_Variant {

        public function __construct() {
            // Tampilkan dropdown variasi di halaman checkout (sebelum billing form).
            add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'display_variation_switchers' ) );

            // Enqueue script untuk memicu AJAX dan update cart.
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

            // AJAX untuk update cart berdasarkan variant terpilih.
            add_action( 'wp_ajax_yourpropfirm_update_cart_variation', array( $this, 'update_cart_variation' ) );
            add_action( 'wp_ajax_nopriv_yourpropfirm_update_cart_variation', array( $this, 'update_cart_variation' ) );
        }

        /**
         * Display variation switchers for all variable products in the cart.
         */
        public function display_variation_switchers() {
            // Ambil semua item di cart
            $cart_items = WC()->cart->get_cart();
            if ( empty( $cart_items ) ) {
                return; // Tidak ada produk di cart
            }

            echo '<div class="yourpropfirm-switch-variant-wrapper">';
            echo '<h3>' . __( 'Switch Variations', 'yourpropfirm-switch-variant' ) . '</h3>';

            // Loop setiap item di cart
            foreach ( $cart_items as $cart_item_key => $cart_item ) {
                $product = wc_get_product( $cart_item['product_id'] );

                // Hanya tampilkan form untuk product yang bertipe "variable"
                if ( ! $product || 'variable' !== $product->get_type() ) {
                    continue;
                }

                // Ambil semua variation IDs
                $variation_ids = $product->get_children();
                if ( empty( $variation_ids ) ) {
                    continue;
                }

                // Kumpulkan data variation: attributes & price
                $available_variations = array();
                foreach ( $variation_ids as $variation_id ) {
                    $variation_obj = wc_get_product( $variation_id );
                    if ( ! $variation_obj ) {
                        continue;
                    }
                    $available_variations[] = array(
                        'variation_id' => $variation_id,
                        'attributes'   => $variation_obj->get_attributes(), // ex: ['pa_challenge-type' => '1-phase', ...]
                        'price_html'   => $variation_obj->get_price_html(),
                    );
                }

                // Cari semua possible values untuk attribute 'challenge-type' dan 'challenge-amount'
                // Ganti slug jika Anda menggunakan nama lain
                $attr_challenge_type   = 'pa_challenge-type';
                $attr_challenge_amount = 'pa_challenge-amount';

                $challenge_type_options   = array();
                $challenge_amount_options = array();

                foreach ( $available_variations as $variation ) {
                    $atts = $variation['attributes'];
                    if ( isset( $atts[ $attr_challenge_type ] ) ) {
                        $challenge_type_options[] = $atts[ $attr_challenge_type ];
                    }
                    if ( isset( $atts[ $attr_challenge_amount ] ) ) {
                        $challenge_amount_options[] = $atts[ $attr_challenge_amount ];
                    }
                }

                // Hilangkan duplikat
                $challenge_type_options   = array_unique( $challenge_type_options );
                $challenge_amount_options = array_unique( $challenge_amount_options );

                // Buat form
                echo '<div class="yourpropfirm-switch-variant-item" style="border:1px solid #ddd; margin-bottom:15px; padding:10px;">';
                echo '<strong>' . $product->get_name() . '</strong><br>';

                // Tampilkan info item lama (misal Variation Title) jika ada
                if ( isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] ) {
                    $current_variation_obj = wc_get_product( $cart_item['variation_id'] );
                    if ( $current_variation_obj ) {
                        echo '<p style="margin:5px 0;">' . sprintf( __( 'Current Variation: %s', 'yourpropfirm-switch-variant' ), $current_variation_obj->get_name() ) . '</p>';
                    }
                }

                // Dropdown Challenge Type
                echo '<p class="form-row form-row-wide">';
                echo '<label>' . __( 'Challenge Type', 'yourpropfirm-switch-variant' ) . '</label>';
                echo '<select class="yourpropfirm-challenge-type" data-cart_item_key="' . esc_attr( $cart_item_key ) . '">';
                echo '<option value="">' . __( 'Select Challenge Type', 'yourpropfirm-switch-variant' ) . '</option>';
                foreach ( $challenge_type_options as $option ) {
                    echo '<option value="' . esc_attr( $option ) . '">' . esc_html( ucfirst( $option ) ) . '</option>';
                }
                echo '</select>';
                echo '</p>';

                // Dropdown Challenge Amount
                echo '<p class="form-row form-row-wide">';
                echo '<label>' . __( 'Challenge Amount', 'yourpropfirm-switch-variant' ) . '</label>';
                echo '<select class="yourpropfirm-challenge-amount" data-cart_item_key="' . esc_attr( $cart_item_key ) . '">';
                echo '<option value="">' . __( 'Select Challenge Amount', 'yourpropfirm-switch-variant' ) . '</option>';
                foreach ( $challenge_amount_options as $option ) {
                    echo '<option value="' . esc_attr( $option ) . '">' . esc_html( ucfirst( $option ) ) . '</option>';
                }
                echo '</select>';
                echo '</p>';

                // Simpan product_id dan cart_item_key di hidden input agar bisa digunakan di AJAX
                echo '<input type="hidden" class="yourpropfirm-product-id" value="' . esc_attr( $cart_item['product_id'] ) . '">';
                echo '<input type="hidden" class="yourpropfirm-cart-item-key" value="' . esc_attr( $cart_item_key ) . '">';

                echo '</div>'; // end .yourpropfirm-switch-variant-item
            }

            echo '</div>'; // end .yourpropfirm-switch-variant-wrapper
        }

        /**
         * Enqueue scripts needed for AJAX to update cart when user switches variation.
         */
        public function enqueue_scripts() {
            if ( is_checkout() ) {
                // Pastikan jQuery sudah di-load
                wp_enqueue_script( 'jquery' );

                // Localize script
                $ajax_url = admin_url( 'admin-ajax.php' );

                // Script JS
                $script = "
                jQuery(function($){
                    // Saat user mengubah dropdown
                    $('.yourpropfirm-challenge-type, .yourpropfirm-challenge-amount').on('change', function(){
                        var parentWrap      = $(this).closest('.yourpropfirm-switch-variant-item');
                        var productID       = parentWrap.find('.yourpropfirm-product-id').val();
                        var cartItemKey     = parentWrap.find('.yourpropfirm-cart-item-key').val();
                        var challengeType   = parentWrap.find('.yourpropfirm-challenge-type').val();
                        var challengeAmount = parentWrap.find('.yourpropfirm-challenge-amount').val();

                        // Pastikan user memilih keduanya
                        if( !challengeType || !challengeAmount ){
                            return;
                        }

                        // AJAX call
                        $.ajax({
                            url: '{$ajax_url}',
                            type: 'POST',
                            data: {
                                action: 'yourpropfirm_update_cart_variation',
                                product_id: productID,
                                cart_item_key: cartItemKey,
                                challenge_type: challengeType,
                                challenge_amount: challengeAmount
                            },
                            beforeSend: function(){
                                // Bisa tampilkan loader
                            },
                            success: function(response){
                                if(response.success){
                                    // Trigger update_checkout agar order review di-refresh
                                    $('body').trigger('update_checkout');
                                } else {
                                    console.log(response.data);
                                }
                            },
                            error: function(err){
                                console.log(err);
                            }
                        });
                    });
                });
                ";

                // Masukkan script inline
                wp_add_inline_script( 'jquery', $script );
            }
        }

        /**
         * AJAX callback to remove old cart item and add new variation item.
         */
        public function update_cart_variation() {
            // Dapatkan data dari AJAX
            $product_id       = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
            $cart_item_key    = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
            $challenge_type   = isset($_POST['challenge_type']) ? sanitize_text_field($_POST['challenge_type']) : '';
            $challenge_amount = isset($_POST['challenge_amount']) ? sanitize_text_field($_POST['challenge_amount']) : '';

            if( ! $product_id || ! $cart_item_key ) {
                wp_send_json_error( array( 'message' => 'Missing product_id or cart_item_key.' ) );
            }
            if( ! $challenge_type || ! $challenge_amount ) {
                wp_send_json_error( array( 'message' => 'Missing variation attributes.' ) );
            }

            // Cari variation ID yang cocok
            $variation_id = $this->find_matching_variation( $product_id, array(
                'attribute_pa_challenge-type'   => $challenge_type,
                'attribute_pa_challenge-amount' => $challenge_amount
            ) );

            if ( ! $variation_id ) {
                wp_send_json_error( array( 'message' => 'No matching variation found.' ) );
            }

            // Hapus item lama dari cart
            WC()->cart->remove_cart_item( $cart_item_key );

            // Tambahkan item baru
            $added = WC()->cart->add_to_cart( $product_id, 1, $variation_id, array(
                'attribute_pa_challenge-type'   => $challenge_type,
                'attribute_pa_challenge-amount' => $challenge_amount
            ) );

            if ( $added ) {
                wp_send_json_success( array( 'message' => 'Cart updated successfully.' ) );
            } else {
                wp_send_json_error( array( 'message' => 'Failed to add new variation to cart.' ) );
            }
        }

        /**
         * Find a variation ID matching the given attributes.
         */
        private function find_matching_variation( $product_id, $attributes ) {
            $product = wc_get_product( $product_id );
            if ( ! $product || 'variable' !== $product->get_type() ) {
                return 0;
            }

            foreach ( $product->get_children() as $child_id ) {
                $variation = wc_get_product( $child_id );
                if ( ! $variation ) {
                    continue;
                }
                $matched = true;
                foreach ( $attributes as $attr_name => $attr_value ) {
                    // Pastikan bandingkan dengan lowercase atau format yang sama
                    $variation_attr = $variation->get_attribute( $attr_name );
                    if ( strtolower( $variation_attr ) !== strtolower( $attr_value ) ) {
                        $matched = false;
                        break;
                    }
                }
                if ( $matched ) {
                    return $child_id;
                }
            }
            return 0;
        }
    }

    new YourPropFirm_Switch_Variant();
}
