<?php
/*
Plugin Name: YourPropFirm Switch Variant
Description: Custom plugin to add a variant switcher on the WooCommerce checkout page so that users can switch variant.
Version: 1.0
Author: Your Name
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'YourPropFirm_Switch_Variant' ) ) {

    class YourPropFirm_Switch_Variant {

        /**
         * Constructor to initialize hooks.
         */
        public function __construct() {
            // Display variant switcher before the billing form on the checkout page.
            add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'display_variant_switcher' ) );

            // Validate the custom checkout fields.
            add_action( 'woocommerce_checkout_process', array( $this, 'validate_variant_fields' ) );

            // Save custom checkout field data to the order meta.
            add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_variant_fields' ) );
        }

        /**
         * Display the variant switcher fields on the checkout page.
         */
        public function display_variant_switcher() {
            // Get current cart items.
            $cart = WC()->cart->get_cart();
            $challenge_type_options   = array();
            $challenge_amount_options = array();

            // Loop through cart items to extract product attributes.
            foreach ( $cart as $cart_item ) {
                $product = wc_get_product( $cart_item['product_id'] );
                if ( $product ) {
                    // Retrieve the 'challenge-type' attribute.
                    $challenge_type = $product->get_attribute( 'challenge-type' );
                    if ( ! empty( $challenge_type ) ) {
                        // Assume that multiple values are comma-separated.
                        $types = array_map( 'trim', explode( ',', $challenge_type ) );
                        $challenge_type_options = array_unique( array_merge( $challenge_type_options, $types ) );
                    }
                    // Retrieve the 'challenge-amount' attribute.
                    $challenge_amount = $product->get_attribute( 'challenge-amount' );
                    if ( ! empty( $challenge_amount ) ) {
                        $amounts = array_map( 'trim', explode( ',', $challenge_amount ) );
                        $challenge_amount_options = array_unique( array_merge( $challenge_amount_options, $amounts ) );
                    }
                }
            }

            // Fallback: If no options were found, use default sample options.
            if ( empty( $challenge_type_options ) ) {
                $challenge_type_options = array( '1 Phase', '2 Phase' );
            }
            if ( empty( $challenge_amount_options ) ) {
                $challenge_amount_options = array( '$5,000', '$10,000', '$25,000', '$50,000' );
            }

            echo '<div class="yourpropfirm-switch-variant" style="margin-bottom:20px;">';
            echo '<h3>' . __( 'Switch Variant', 'yourpropfirm-switch-variant' ) . '</h3>';

            // Display the Challenge Type select field.
            echo '<p class="form-row form-row-wide">';
            echo '<label for="challenge_type">' . __( 'Challenge Type', 'yourpropfirm-switch-variant' ) . '</label>';
            echo '<select name="challenge_type" id="challenge_type" class="select">';
            echo '<option value="">' . __( 'Select Challenge Type', 'yourpropfirm-switch-variant' ) . '</option>';
            foreach ( $challenge_type_options as $option ) {
                echo '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>';
            }
            echo '</select>';
            echo '</p>';

            // Display the Challenge Amount select field.
            echo '<p class="form-row form-row-wide">';
            echo '<label for="challenge_amount">' . __( 'Challenge Amount', 'yourpropfirm-switch-variant' ) . '</label>';
            echo '<select name="challenge_amount" id="challenge_amount" class="select">';
            echo '<option value="">' . __( 'Select Challenge Amount', 'yourpropfirm-switch-variant' ) . '</option>';
            foreach ( $challenge_amount_options as $option ) {
                echo '<option value="' . esc_attr( $option ) . '">' . esc_html( $option ) . '</option>';
            }
            echo '</select>';
            echo '</p>';

            echo '</div>';
        }

        /**
         * Validate the custom checkout fields.
         */
        public function validate_variant_fields() {
            if ( empty( $_POST['challenge_type'] ) ) {
                wc_add_notice( __( 'Please select a Challenge Type.', 'yourpropfirm-switch-variant' ), 'error' );
            }
            if ( empty( $_POST['challenge_amount'] ) ) {
                wc_add_notice( __( 'Please select a Challenge Amount.', 'yourpropfirm-switch-variant' ), 'error' );
            }
        }

        /**
         * Save the custom checkout field values to the order meta.
         *
         * @param int $order_id The order ID.
         */
        public function save_variant_fields( $order_id ) {
            if ( ! empty( $_POST['challenge_type'] ) ) {
                update_post_meta( $order_id, '_challenge_type', sanitize_text_field( $_POST['challenge_type'] ) );
            }
            if ( ! empty( $_POST['challenge_amount'] ) ) {
                update_post_meta( $order_id, '_challenge_amount', sanitize_text_field( $_POST['challenge_amount'] ) );
            }
        }
    }

    // Initialize the plugin.
    new YourPropFirm_Switch_Variant();
}