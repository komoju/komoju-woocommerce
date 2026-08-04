<?php

/**
 * Block checkout must send translated gateway titles/descriptions.
 *
 * WCML translates gateway strings two ways: by mutating $gateway->title from the
 * woocommerce_payment_gateways filter, and via the woocommerce_gateway_title /
 * _description filters inside get_title()/get_description(). We no longer fire
 * the former during block registration, so reading raw properties loses both.
 *
 * No WPML/WooCommerce/DB needed. Run: php tests/php/blocks-translation-test.php
 */
define('ABSPATH', dirname(__DIR__, 2) . '/');

$filters = [];

function add_filter($hook, $callback)
{
    $GLOBALS['filters'][$hook][] = $callback;
}

function apply_filters($hook, $value)
{
    foreach ($GLOBALS['filters'][$hook] ?? [] as $cb) {
        $value = $cb($value, ...array_slice(func_get_args(), 2));
    }

    return $value;
}

function __($t, $d = null)
{
    return $t;
}
function esc_html__($t, $d = null)
{
    return $t;
}
function esc_url($u)
{
    return $u;
}
function esc_attr($s)
{
    return $s;
}
function get_option($n, $d = false)
{
    return $d;
}
function wp_kses_post($s)
{
    return $s;
}
function wp_json_encode($v)
{
    return json_encode($v);
}
function get_locale()
{
    return 'en_US';
}
function get_woocommerce_currency()
{
    return 'JPY';
}
function absint($n)
{
    return (int) abs($n);
}
function is_admin()
{
    return false;
}
function wc_get_logger()
{
    return null;
}
function plugin_dir_url($f)
{
    return '/';
}
function add_action()
{
}
function wp_register_script()
{
}
function WC()
{
    return new class {
        public $cart;

        public function __construct()
        {
            $this->cart = new class {
                public function is_empty()
                {
                    return false;
                }
            };
        }
    };
}

class WC_Payment_Gateway
{
    public $id;
    public $icon;
    public $has_fields;
    public $method_title;
    public $method_description;
    public $title;
    public $description;
    public $enabled  = 'yes';
    public $supports = ['products'];
    public $settings = [];

    public function get_option($k, $e = null)
    {
        return '';
    }

    public function init_settings()
    {
    }

    public function get_order_total()
    {
        return 0;
    }

    public function supports($f)
    {
        return in_array($f, $this->supports, true);
    }

    // Mirrors WooCommerce core.
    public function get_title()
    {
        return apply_filters('woocommerce_gateway_title', (string) $this->title, $this->id);
    }

    public function get_description()
    {
        return apply_filters('woocommerce_gateway_description', wp_kses_post($this->description), $this->id);
    }
}

abstract class AbstractPaymentMethodType
{
    protected $name;
    protected $settings = [];
}
class_alias('AbstractPaymentMethodType', 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType');

require_once ABSPATH . 'class-wc-gateway-komoju.php';
require_once ABSPATH . 'includes/class-wc-gateway-komoju-single-slug.php';
require_once ABSPATH . 'includes/class-wc-gateway-komoju-block.php';

/** Build a gateway the way block registration does: constructed directly, never filtered. */
function gateway()
{
    $ref = new ReflectionClass('WC_Gateway_Komoju_Single_Slug');
    $gw  = $ref->newInstanceWithoutConstructor();
    $ref->getProperty('payment_method')->setValue($gw, ['type_slug' => 'konbini', 'settings' => []]);
    $gw->id          = 'komoju_konbini';
    $gw->title       = 'Konbini';
    $gw->description = 'Pay at a convenience store.';

    return $gw;
}

/** Registers only the filter-based translation path. */
function fake_wcml()
{
    $GLOBALS['filters'] = [];
    add_filter('woocommerce_gateway_title', fn ($t, $id) => $id === 'komoju_konbini' ? 'コンビニ払い' : $t);
    add_filter('woocommerce_gateway_description', fn ($d, $id) => $id === 'komoju_konbini' ? 'コンビニでお支払いいただけます。' : $d);
}

$fails = 0;
function check($label, $actual, $expected)
{
    global $fails;
    $pass = $actual === $expected;
    printf("%s %s\n", $pass ? '[PASS]' : '[FAIL]', $label);
    if (!$pass) {
        printf("       expected %s, got %s\n", var_export($expected, true), var_export($actual, true));
        ++$fails;
    }
}

// Translated on a multilingual site.
fake_wcml();
$data = (new WC_Gateway_Komoju_Blocks(gateway()))->get_payment_method_data();
check('title translated', $data['title'], 'コンビニ払い');
check('description translated', $data['description'], 'コンビニでお支払いいただけます。');

// Session-failure path returns a separate array; it must translate too.
$gw = gateway();
(new ReflectionClass('WC_Gateway_Komoju'))->getProperty('komoju_api')->setValue($gw, new class {
    public function createSession($p)
    {
        throw new KomojuExceptionBadServer('boom');
    }
});
$blocks = new WC_Gateway_Komoju_Blocks($gw);
$blocks->get_payment_method_data();
$fallback = $blocks->get_payment_method_data();
check('fallback path translated', $fallback['title'], 'コンビニ払い');

// Untouched without such filters.
$GLOBALS['filters'] = [];
$plain              = (new WC_Gateway_Komoju_Blocks(gateway()))->get_payment_method_data();
check('no filters: title verbatim', $plain['title'], 'Konbini');
check('no filters: description verbatim', $plain['description'], 'Pay at a convenience store.');

printf("\n%s\n", $fails ? "FAILED ($fails)" : 'OK');
exit($fails ? 1 : 0);
