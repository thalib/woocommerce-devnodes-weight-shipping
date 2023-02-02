<?php
/**
 * Plugin Name:       Devnodes Weight Based Shipping Method for Woocommerce
 * Plugin URI:        https://github.com/thalib/woocommerce-devnodes-weight-shipping
 * Description:       Open Source Weight Based Shipping Method for Woocommerce
 * Version:           1.1.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Mohamed Thalib H
 * Author URI:        https://devnodes.in
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://github.com/thalib/woocommerce-devnodes-weight-shipping
 * Text Domain:       devnodes
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'includes/Weight_Shipping.php';

/*****************************************
 * Shipping method api
 * Reference: https://woocommerce.com/document/shipping-method-api/
 *****************************************/

add_action('woocommerce_shipping_init', 'devnodes_shipping_method_weight');
function devnodes_shipping_method_weight()
{
    //add your shipping method to woocommerce list of Shipping methods
    add_filter('woocommerce_shipping_methods', 'add_shipping_method_devnodes');
    function add_shipping_method_devnodes($methods)
    {
        $methods['devnodes_weight'] = 'WC_Shipping_Method_Devnodes_Weight';
        return $methods;
    }
}
