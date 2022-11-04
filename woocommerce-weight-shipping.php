<?php
/**
 * Plugin Name:       Fast Weight Based Shipping Method
 * Plugin URI:        https://github.com/thalib/woocommerce-weight-shipping
 * Description:       Open Source Weight Based Shipping Method for Woocommerce
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Mohamed Thalib H
 * Author URI:        https://github.com/thalib
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://github.com/thalib/woocommerce-weight-shipping
 * Text Domain:       fastship
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/FastShipWeight.php';

/*****************************************
 * Shipping method api
 * Reference: https://woocommerce.com/document/shipping-method-api/
 *****************************************/

add_action('woocommerce_shipping_init', 'fastship_shipping_method_weight');
function fastship_shipping_method_weight()
{

    //add your shipping method to WooCommers list of Shipping methods
    add_filter('woocommerce_shipping_methods', 'add_shipping_method_fastship');
    function add_shipping_method_fastship($methods)
    {
        $methods['fastship_weight'] = 'WC_Shipping_Method_Fastship_Weight';
        return $methods;
    }
}
