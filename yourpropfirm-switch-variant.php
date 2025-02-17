<?php
/**
 * Plugin Name: YourPropFirm Product Variation Manager
 * Plugin URI: https://example.com
 * Description: Sets default product variation and allows switching variations on checkout.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class YourPropFirm_Variation_Manager {
    private $default_product_id;

    public function __construct() {
        // Set your default product ID here
        $this->default_product_id = 1202; // Replace with your product ID

        add_action('template_redirect', [$this, 'handle_empty_cart_redirect'], 5);
        add_filter('woocommerce_checkout_redirect_empty_cart', '__return_false');


        // Initialize default product hooks
        //add_action('init', [$this, 'add_default_variation_to_cart']);
        
        // Initialize variation switcher hooks
        add_action('woocommerce_before_checkout_billing_form', [$this, 'display_variant_selector'], 5);
        add_action('wp_ajax_yourpropfirm_update_cart', [$this, 'update_cart']);
        add_action('wp_ajax_nopriv_yourpropfirm_update_cart', [$this, 'update_cart']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }


    public function handle_empty_cart_redirect() {
    if (!is_checkout()) {
        return;
    }
    
    if (WC()->cart->is_empty()) {
        $product = wc_get_product($this->default_product_id);
        
        if ($product && $product->is_type('variable')) {
            $default_attributes = $product->get_default_attributes();
            
            // Format attributes correctly for WooCommerce
            $formatted_attributes = [];
            foreach ($default_attributes as $key => $value) {
                $formatted_attributes['attribute_' . $key] = $value;
            }
            
            // Debug output
            echo "Default Attributes:<br>";
            var_dump($default_attributes);
            echo "<br>Formatted Attributes:<br>";
            var_dump($formatted_attributes);
            
            if (!empty($formatted_attributes)) {
                $variation_id = $product->get_matching_variation($formatted_attributes);
                
                echo "<br>Variation ID:<br>";
                var_dump($variation_id);
                
                if ($variation_id) {
                    WC()->cart->add_to_cart($this->default_product_id, 1, $variation_id, $formatted_attributes);
                    wp_safe_redirect(wc_get_checkout_url());
                    exit;
                }
            }
            
            // If no matching variation found, let's see available variations
            echo "<br>Available Variations:<br>";
            var_dump($product->get_available_variations());
        }
    }
}

    public function add_default_variation_to_cart() {
        // Only run on checkout page
        if (!is_checkout()) {
            return;
        }
        
        // Check if cart is empty
        if (WC()->cart->is_empty()) {
            $product = wc_get_product($this->default_product_id);
            
            if ($product && $product->is_type('variable')) {
                // Get default attributes
                $default_attributes = $product->get_default_attributes();
                
                if (!empty($default_attributes)) {
                    // Get variation ID based on default attributes
                    $variation_id = $product->get_matching_variation($default_attributes);
                    
                    if ($variation_id) {
                        // Add the variation to cart
                        WC()->cart->add_to_cart($this->default_product_id, 1, $variation_id, $default_attributes);
                    }
                }
            }
        }
    }

    public function enqueue_scripts() {
        if (is_checkout()) {
            wp_enqueue_script('yourpropfirm-switch-variant', plugin_dir_url(__FILE__) . 'assets/js/switch-variant.js', ['jquery'], '1.5', true);
            wp_enqueue_style('yourpropfirm-switch-variant-style', plugin_dir_url(__FILE__) . 'assets/css/switch-variant.css', [], '1.5');
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
        $selected_variation_id = 0;
        
        foreach ($cart_items as $cart_item) {
            $product_id = $cart_item['product_id'];
            if (isset($cart_item['variation_id']) && $cart_item['variation_id'] > 0) {
                $selected_variation_id = $cart_item['variation_id'];
            }
            break;
        }
        
        if (!$product_id) return;

        $product = wc_get_product($product_id);
        if (!$product->is_type('variable')) return;

        $variations = $product->get_available_variations();
        $attributes = $product->get_variation_attributes();
        $selected_attributes = [];

        if ($selected_variation_id) {
            foreach ($variations as $variation) {
                if ($variation['variation_id'] == $selected_variation_id) {
                    $selected_attributes = $variation['attributes'];
                    break;
                }
            }
        }

        echo '<div id="yourpropfirm-variant-switcher">';
        echo '<h3>Choose Variant</h3>';

        foreach ($attributes as $attribute_name => $options) {
            echo '<label>' . wc_attribute_label($attribute_name) . '</label>';
            echo '<div class="yourpropfirm-radio-group" data-attribute="' . esc_attr($attribute_name) . '">';
            foreach ($options as $option) {
                $selected = (isset($selected_attributes['attribute_' . sanitize_title($attribute_name)]) && $selected_attributes['attribute_' . sanitize_title($attribute_name)] == $option) ? ' checked' : '';
                echo '<div class="yourpropfirm-radio-option">';
                echo '<input type="radio" name="' . esc_attr($attribute_name) . '" value="' . esc_attr($option) . '" class="yourpropfirm-switch"' . $selected . '>';
                echo '<label class="yourpropfirm-radio-label">' . esc_html($option) . '</label>';
                echo '</div>';
            }
            echo '</div>';
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
            wp_send_json_error(['message' => 'Invalid variation selected.']);
        }

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart($product_id, 1, $variation_id);
        wp_send_json_success(['message' => 'Cart updated successfully.']);
    }
}

new YourPropFirm_Variation_Manager();