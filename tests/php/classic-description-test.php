<?php

/**
 * Classic checkout must render the configured individual-gateway description.
 *
 * No WordPress/WooCommerce/DB needed. Run: php tests/php/classic-description-test.php
 */
define('ABSPATH', dirname(__DIR__, 2) . '/');

function __($text, $domain = null)
{
    return $text;
}

function esc_html__($text, $domain = null)
{
    return $text;
}

function get_option($name, $default = false)
{
    return $default;
}

function wp_kses_post($text)
{
    return $text;
}

function wpautop($text)
{
    return '<p>' . $text . '</p>';
}

function wptexturize($text)
{
    return $text;
}

function get_locale()
{
    return 'en_US';
}

function get_woocommerce_currency()
{
    return 'JPY';
}

function absint($number)
{
    return (int) abs($number);
}

function wc_get_logger()
{
    return null;
}

function add_action()
{
}

class WC_Payment_Gateway
{
    public $id;
    public $has_fields;
    public $method_title;
    public $method_description;
    public $title;
    public $description;
    public $enabled  = 'yes';
    public $supports = ['products'];

    public function init_form_fields()
    {
    }

    public function init_settings()
    {
    }

    public function get_option($key, $default = null)
    {
        return $default;
    }

    public function get_description()
    {
        return wp_kses_post($this->description);
    }

    public function payment_fields()
    {
        $description = $this->get_description();

        if ($description) {
            echo wpautop(wptexturize($description));
        }
    }
}

require_once ABSPATH . 'class-wc-gateway-komoju.php';
require_once ABSPATH . 'includes/class-wc-gateway-komoju-single-slug.php';

$reflection           = new ReflectionClass('WC_Gateway_Komoju_Single_Slug');
$gateway              = $reflection->newInstanceWithoutConstructor();
$gateway->has_fields  = false;
$gateway->description = 'Customers complete payment on the KOMOJU hosted page.';

ob_start();
$gateway->payment_fields();
$output = ob_get_clean();

if (strpos($output, $gateway->description) === false) {
    fwrite(STDERR, "FAILED: classic redirect checkout omitted the configured description\n");
    exit(1);
}

printf("OK\n");
