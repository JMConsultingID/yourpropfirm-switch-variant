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
        add_action('template_redirect', [$this, 'set_default_variation']);
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

        echo '<div id="yourpropfirm-variant-switcher">';
        echo '<h3>Choose Variant</h3>';
        
        foreach ($attributes as $attribute_name => $options) {
            echo '<label>' . wc_attribute_label($attribute_name) . '</label>';
            echo '<select class="yourpropfirm-switch" data-attribute="' . esc_attr($attribute_name) . '">';
            foreach ($options as $option) {
                echo '<option value="' . esc_attr($option) . '">' . esc_html($option) . '</option>';
            }
            echo '</select>';
        }
        
        echo '</div>';
    }

    public function set_default_variation() {
        if (is_checkout() && isset($_GET['add-to-cart'])) {
            $product_id = absint($_GET['add-to-cart']);
            $product = wc_get_product($product_id);
            
            if ($product && $product->is_type('variable')) {
                $default_attributes = $product->get_default_attributes();
                
                foreach ($product->get_available_variations() as $variation) {
                    $match = true;
                    foreach ($default_attributes as $key => $value) {
                        $attribute_key = 'attribute_' . sanitize_title($key);
                        if (!isset($variation['attributes'][$attribute_key]) || $variation['attributes'][$attribute_key] !== $value) {
                            $match = false;
                            break;
                        }
                    }
                    if ($match) {
                        WC()->cart->empty_cart();
                        WC()->cart->add_to_cart($product_id, 1, $variation['variation_id']);
                        return;
                    }
                }

                // Jika tidak ada yang cocok dengan default attributes, pilih variation pertama
                $first_variation = reset($product->get_available_variations());
                if ($first_variation) {
                    WC()->cart->empty_cart();
                    WC()->cart->add_to_cart($product_id, 1, $first_variation['variation_id']);
                }
            }
        }
    }
}

new YourPropFirm_Switch_Variant();