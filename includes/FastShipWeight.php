<?php

/**
 * class: Weight based method
 */

if (!defined('ABSPATH')) {
    exit;
}

/*****************
 *  class: Weight based method
 */

if (!class_exists('WC_Shipping_Method')) {
    require_once WP_PLUGIN_DIR . '/woocommerce/includes/abstracts/abstract-wc-settings-api.php';
    require_once WP_PLUGIN_DIR . '/woocommerce/includes/abstracts/abstract-wc-shipping-method.php';
}

class WC_Shipping_Method_Fastship_Weight extends WC_Shipping_Method
{
    public function __construct($instance_id = 0)
    {
        $this->id = 'fastship_weight'; //this is the id of our shipping method
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Fastship Weight Shipping', 'fastship');
        $this->method_description = __('Lets you charge a weight based charge for shipping.', 'fastship');
        //add to shipping zones list
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );

        //make it always enabled and run init
        $this->enabled = 'yes';
        $this->init();

        // Save settings in admin if you have any defined
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));

    }

    public function init()
    {
        // Load the settings API
        $this->init_form_fields();

        $this->min_weight = $this->get_option('min_weight');
        $this->max_weight = $this->get_option('max_weight');
        $this->tax_status = $this->get_option('tax_status');
        $this->cost = $this->get_option('cost');
        $this->round_weight = $this->get_option('round_weight');

        $name = $this->get_option('title');
        $this->title = sprintf("%s: ₹%d/Kg [%01.2f-%01.2f Kg]", $name, $this->cost, $this->min_weight, $this->max_weight);
    }

    //Fields for the settings page
    public function init_form_fields()
    {

        $settings = array(
            'title' => array(
                'title' => __('Method Title', 'fastship'),
                'type' => 'text',
                'description' => __('User sees this title during checkout.', 'fastship'),
                'desc_tip' => true,
                'default' => __('Standard Surface', 'fastship'),
            ),
            'min_weight' => array(
                'title' => __('Weight Min (Kg)', 'fastship'),
                'type' => 'number',
                'default' => 0,
            ),
            'max_weight' => array(
                'title' => __('Weight Max (Kg)', 'fastship'),
                'type' => 'number',
                'default' => 5,
            ),
            'round_weight' => array(
                'title' => __('Round weight (in grams)', 'fastship'),
                'type' => 'number',
                'description' => __('Round UP weight to nearest (in gram)<br> In India the logistics service charge in 500g or 200g slab', 'fastship'),
                'desc_tip' => true,
                'default' => 500,
            ),
            'cost' => array(
                'title' => __('Shipping Rate (per Kg)', 'fastship'),
                'type' => 'number',
                'default' => 130,
            ),
            'tax_status' => array(
                'title' => __('Tax status', 'fastship'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'default' => 'none',
                'options' => array(
                    'taxable' => __('Taxable', 'fastship'),
                    'none' => __('None', 'fastship'),
                ),
                'description' => __('Apply tax on shipping fee', 'fastship'),
                'desc_tip' => true,
            ),
        );

        $shipping_classes = WC()->shipping()->get_shipping_classes();
        if (!empty($shipping_classes)) {
            
            $settings['class_costs'] = array(
                'title' => __('Shipping class costs', 'fastship'),
                'type' => 'title',
                'default' => '',
                'description' => __('Set special rate for each shipping class', 'fastship'),
            );

            foreach ($shipping_classes as $shipping_class) {
                if (!isset($shipping_class->term_id)) {
                    continue;
                }

                $settings['class_cost_' . $shipping_class->term_id] = array(
                    /* translators: %s: shipping class name */
                    'title' => sprintf(__('"%s" (rate per Kg)', 'fastship'), esc_html($shipping_class->name)),
                    'type' => 'number',
                    'description' => __('0 to disable this class', 'fastship'),
                    'desc_tip' => true,
                    'placeholder' => __('N/A', 'fastship'),
                    'default' => 0,
                );
            }
        }

        //fields for the modal form from the Zones window
        $this->instance_form_fields = $settings;
    }

    public function fastship_get_class_weight($package)
    {
        $class_weight = array();
        $class_weight['no_class'] = 0;

        foreach ($package['contents'] as $item_id => $item) {
            $product = $item['data'];
            $class_id = $product->get_shipping_class_id();
            if ($product->needs_shipping()) {
                if ($class_id) {
                    if (array_key_exists($class_id, $class_weight)) {
                        $class_weight[$class_id] += $product->get_weight() * $item['quantity'];
                    } else {
                        $class_weight[$class_id] = $product->get_weight() * $item['quantity'];
                    }
                } else {
                    $class_weight['no_class'] += $product->get_weight() * $item['quantity'];
                }
            }
        }
        return $class_weight;
    }

    private function get_round_weight($weight, $precision = 0)
    {
        $weight_kg = round($weight, 2, PHP_ROUND_HALF_UP) * 1000; // convert to gram

        if ($precision) {
            $rem = ($weight_kg % $precision);
            if ($rem) {
                $weight_kg = $weight_kg + $precision - $rem;
            }
        }
        return $weight_kg / 1000; //convert back to kg
    }

    public function fastship_get_shipping_cost($package, $default_rate, $cart_weight)
    {
        $shipping_cost = 0;

        $shipping_classes = WC()->shipping()->get_shipping_classes();

        if (empty($shipping_classes)) {
            $shipping_cost = $default_rate * $cart_weight;
        } else {

            $class_weight = $this->fastship_get_class_weight($package);

            //Add no_class products shipping cost, round weight to 100 grams
            $shipping_cost = $default_rate * $this->get_round_weight($class_weight['no_class'], 100);

            //Add class based shipping cost
            foreach ($shipping_classes as $shipping_class) {
                $class_id = $shipping_class->term_id;
                if (array_key_exists($class_id, $class_weight)) {
                    $class_rate = $this->get_option('class_cost_' . $class_id);

                    //round weight to 100 grams
                    $weight = $this->get_round_weight($class_weight[$class_id], 100);
                    if ($class_rate > 0) {
                        $shipping_cost += $weight * $class_rate;
                    } else {
                        $shipping_cost += $weight * $default_rate;
                    }
                }
            } //for
        } //if

        //round to 10, -1 for fancy
        $shipping_cost = ( intval($shipping_cost / 10) * 10 ) - 1;
        return $shipping_cost;
    }

    public function calculate_shipping($package = array())
    {

        $cart_weight = WC()->cart->get_cart_contents_weight();
        $weight_kg = $this->get_round_weight($cart_weight, $this->round_weight);

        if ($cart_weight >= $this->min_weight && $cart_weight <= $this->max_weight) {

            $method_title = $this->get_option('title');
            $label = $method_title . ' (Discounted_Rate_' . $weight_kg . 'Kg)';

            $cost = $this->fastship_get_shipping_cost($package, $this->cost, $weight_kg);

            $tax_status = ($this->tax_status == 'none') ? false : '';

            $rate = array(
                'id' => $this->get_rate_id(),
                'label' => $label,
                'cost' => $cost,
                'package' => $package,
                'calc_tax' => 'per_order',
                'taxes' => $tax_status,
            );

            //error_log('thalib: rate ' . print_r($rate, true));

            if ($cost) {
                $this->add_rate($rate);
            }
        } //if min/max weight
    } // function

} //class
