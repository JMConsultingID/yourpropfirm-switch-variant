<?php
/*
Plugin Name: YourPropFirm Switch Variant
Description: Allow users to switch variations on the checkout page and update cart automatically.
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
            add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'display_variant_switcher' ) );

            // Enqueue script untuk memicu AJAX dan update cart.
            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

            // AJAX untuk update cart berdasarkan variant terpilih.
            add_action( 'wp_ajax_yourpropfirm_update_cart_variation', array( $this, 'update_cart_variation' ) );
            add_action( 'wp_ajax_nopriv_yourpropfirm_update_cart_variation', array( $this, 'update_cart_variation' ) );
        }

        /**
         * Menampilkan form switch variant di halaman checkout.
         */
        public function display_variant_switcher() {
            // Ganti dengan ID produk induk (variable product) Anda
            $product_id = 65079;

            $product = wc_get_product( $product_id );
            if ( ! $product || 'variable' !== $product->get_type() ) {
                echo '<p style="color:red;">Variable product not found or invalid product ID.</p>';
                return;
            }

            // Ambil semua variation IDs
            $variation_ids = $product->get_children();

            // Dapatkan semua atribut yang digunakan product ini
            $available_variations = array();
            foreach ( $variation_ids as $variation_id ) {
                $variation_obj = wc_get_product( $variation_id );
                if ( $variation_obj ) {
                    $available_variations[] = array(
                        'variation_id' => $variation_id,
                        'attributes'   => $variation_obj->get_attributes(),
                        'price_html'   => $variation_obj->get_price_html(),
                    );
                }
            }

            // Kumpulkan possible values dari setiap attribute
            // Di WooCommerce, nama atribut di database umumnya berformat 'pa_challenge-type' dsb.
            $attribute_taxonomies = wc_get_attribute_taxonomies();
            $attributes_map = array();
            foreach ( $attribute_taxonomies as $tax ) {
                // Contoh slug: 'pa_challenge-type'
                $attributes_map[ 'pa_' . $tax->attribute_name ] = $tax->attribute_label;
            }

            // Misal kita fokus pada attribute 'pa_challenge-type' dan 'pa_challenge-amount'
            // Boleh di-extend sesuai kebutuhan
            $attr_challenge_type   = 'pa_challenge-type';
            $attr_challenge_amount = 'pa_challenge-amount';

            // Dapatkan daftar unique values
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

            $challenge_type_options   = array_unique( $challenge_type_options );
            $challenge_amount_options = array_unique( $challenge_amount_options );

            echo '<div class="yourpropfirm-switch-variant" style="margin-bottom:20px;">';
            echo '<h3>' . __( 'Switch Variant', 'yourpropfirm-switch-variant' ) . '</h3>';

            // Challenge Type
            echo '<p class="form-row form-row-wide">';
            echo '<label for="challenge_type">' . __( 'Challenge Type', 'yourpropfirm-switch-variant' ) . '</label>';
            echo '<select name="challenge_type" id="challenge_type" class="select">';
            echo '<option value="">' . __( 'Select Challenge Type', 'yourpropfirm-switch-variant' ) . '</option>';
            foreach ( $challenge_type_options as $option ) {
                echo '<option value="' . esc_attr( $option ) . '">' . esc_html( ucfirst( $option ) ) . '</option>';
            }
            echo '</select>';
            echo '</p>';

            // Challenge Amount
            echo '<p class="form-row form-row-wide">';
            echo '<label for="challenge_amount">' . __( 'Challenge Amount', 'yourpropfirm-switch-variant' ) . '</label>';
            echo '<select name="challenge_amount" id="challenge_amount" class="select">';
            echo '<option value="">' . __( 'Select Challenge Amount', 'yourpropfirm-switch-variant' ) . '</option>';
            foreach ( $challenge_amount_options as $option ) {
                echo '<option value="' . esc_attr( $option ) . '">' . esc_html( ucfirst( $option ) ) . '</option>';
            }
            echo '</select>';
            echo '</p>';

            // Hidden input agar JS tahu ID product induk
            echo '<input type="hidden" id="yourpropfirm_product_id" value="' . esc_attr( $product_id ) . '">';

            echo '</div>';
        }

        /**
         * Enqueue scripts yang dibutuhkan untuk AJAX update cart.
         */
        public function enqueue_scripts() {
            if ( is_checkout() ) {
                // Pastikan jQuery sudah di-load
                wp_enqueue_script( 'jquery' );

                // Localize script
                $ajax_url = admin_url( 'admin-ajax.php' );

                // Buat script
                $script = "
                jQuery(function($){
                    var timeout = null;
                    // Trigger event saat user mengubah Challenge Type / Amount
                    $('#challenge_type, #challenge_amount').on('change', function(){
                        // Dapatkan value terpilih
                        var challengeType   = $('#challenge_type').val();
                        var challengeAmount = $('#challenge_amount').val();
                        var productID       = $('#yourpropfirm_product_id').val();

                        if( !challengeType || !challengeAmount ){
                            return; // Pastikan user memilih keduanya
                        }

                        // Panggil AJAX untuk update cart
                        $.ajax({
                            url: '{$ajax_url}',
                            type: 'POST',
                            data: {
                                action: 'yourpropfirm_update_cart_variation',
                                product_id: productID,
                                challenge_type: challengeType,
                                challenge_amount: challengeAmount
                            },
                            beforeSend: function(){
                                // Bisa tampilkan loader di sini
                            },
                            success: function(response){
                                // Setelah berhasil, trigger update_checkout agar order review di-refresh
                                $('body').trigger('update_checkout');
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
         * AJAX callback untuk menghapus item lama dan menambahkan item baru dengan variation ID yang sesuai.
         */
        public function update_cart_variation() {
            // Pastikan data dikirim via POST
            $product_id       = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
            $challenge_type   = isset($_POST['challenge_type']) ? sanitize_text_field($_POST['challenge_type']) : '';
            $challenge_amount = isset($_POST['challenge_amount']) ? sanitize_text_field($_POST['challenge_amount']) : '';

            if( ! $product_id || ! $challenge_type || ! $challenge_amount ) {
                wp_send_json_error( array( 'message' => 'Missing data.' ) );
            }

            // Cari variation ID yang cocok dengan kombinasi atribut
            $variation_id = $this->find_matching_variation( $product_id, array(
                'attribute_pa_challenge-type'   => $challenge_type,
                'attribute_pa_challenge-amount' => $challenge_amount
            ) );

            if ( ! $variation_id ) {
                wp_send_json_error( array( 'message' => 'No matching variation found.' ) );
            }

            // Hapus item lama (yang punya parent $product_id) dari cart
            $this->remove_variations_from_cart( $product_id );

            // Tambahkan item baru (variation ID) ke cart
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
         * Fungsi untuk menemukan variation ID berdasarkan atribut yang dipilih user.
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

        /**
         * Hapus item lama di cart yang parent-nya adalah $product_id (jika ada).
         */
        private function remove_variations_from_cart( $product_id ) {
            foreach ( WC()->cart->get_cart() as $cart_key => $cart_item ) {
                if ( $cart_item['product_id'] == $product_id ) {
                    // Hapus item ini
                    WC()->cart->remove_cart_item( $cart_key );
                }
            }
        }

    }

    new YourPropFirm_Switch_Variant();
}
