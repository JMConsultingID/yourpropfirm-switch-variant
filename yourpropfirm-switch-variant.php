<?php
/**
 * Plugin Name: YourPropFirm Switch Variant
 * Plugin URI: https://example.com
 * Description: Allows switching product variations on the checkout page before filling the billing form.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class YourPropFirm_Switch_Variant {

    public function __construct() {
        add_action('woocommerce_before_checkout_form', [$this, 'display_variant_selector'], 5);
        add_action('wp_ajax_yourpropfirm_update_cart', [$this, 'update_cart']);
        add_action('wp_ajax_nopriv_yourpropfirm_update_cart', [$this, 'update_cart']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts() {
        if (is_checkout()) {
            wp_enqueue_script('yourpropfirm-switch-variant', plugin_dir_url(__FILE__) . 'assets/js/switch-variant.js', ['jquery'], '1.0', true);
            wp_localize_script('yourpropfirm-switch-variant', 'yourpropfirmAjax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('yourpropfirm_nonce'),
            ]);
        }
    }

    public function display_variant_selector() {
        if (WC()->cart->is_empty()) return;
        
        $cart_items = WC()->cart->get_cart();
        $product_id = 0;
        
        foreach ($cart_items as $cart_item) {
            $product_id = $cart_item['product_id'];
            break;
        }
        
        if (!$product_id) return;

        $product = wc_get_product($product_id);
        if (!$product->is_type('variable')) return;

        $variations = $product->get_available_variations();
        $attributes = $product->get_variation_attributes();

        // Get default variation attributes
        $default_attributes = $product->get_default_attributes();
        
        echo '<div id="yourpropfirm-variant-switcher">';
        echo '<h3>Choose Variant</h3>';
        
        foreach ($attributes as $attribute_name => $options) {
            echo '<label>' . wc_attribute_label($attribute_name) . '</label>';
            echo '<select class="yourpropfirm-switch" data-attribute="' . esc_attr($attribute_name) . '">';
            foreach ($options as $option) {
                $selected = isset($default_attributes[$attribute_name]) && $default_attributes[$attribute_name] == $option ? 'selected' : '';
                echo '<option value="' . esc_attr($option) . '" ' . $selected . '>' . esc_html($option) . '</option>';
            }
            echo '</select>';
        }
        
        echo '</div>';
    }

    public function update_cart() {
        check_ajax_referer('yourpropfirm_nonce', 'security');
        
        $variation_attributes = isset($_POST['variation_attributes']) ? $_POST['variation_attributes'] : [];
        if (empty($variation_attributes)) {
            wp_send_json_error(['message' => 'No variation attributes provided.']);
        }

        $product_id = 0;
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            break;
        }

        if (!$product_id) {
            wp_send_json_error(['message' => 'No product found in cart.']);
        }

        $product = wc_get_product($product_id);
        if (!$product->is_type('variable')) {
            wp_send_json_error(['message' => 'Selected product is not a variable product.']);
        }

        $variation_id = 0;
        foreach ($product->get_available_variations() as $variation) {
            $match = true;
            foreach ($variation_attributes as $attribute => $value) {
                $attribute_key = 'attribute_' . sanitize_title($attribute);
                if (!isset($variation['attributes'][$attribute_key]) || $variation['attributes'][$attribute_key] !== $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $variation_id = $variation['variation_id'];
                break;
            }
        }

        if (!$variation_id) {
            // If no valid variation found, get the default variation
            $default_variation_id = $product->get_default_variation_id();
            if ($default_variation_id) {
                $variation_id = $default_variation_id;
            } else {
                wp_send_json_error(['message' => 'Invalid variation selected.']);
            }
        }

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($product_id, 1, $variation_id);
        wp_send_json_success(['message' => 'Cart updated successfully.']);
    }
}

new YourPropFirm_Switch_Variant();