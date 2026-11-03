<?php

/**
 * New KOMOJU payments are matched by internal order ID or komoju_session_id.
 * Display order numbers, including those embedded in external_order_num, are not.
 *
 * No WordPress/WooCommerce/DB needed. Run: php tests/php/order-lookup-test.php
 */
define('ABSPATH', dirname(__DIR__, 2) . '/');

$GLOBALS['orders'] = [];
$GLOBALS['logs']   = [];

function add_filter()
{
}
function add_action()
{
}
function __($t, $d = null)
{
    return $t;
}
function wc_clean($v)
{
    return $v;
}
function wp_json_encode($v)
{
    return json_encode($v);
}
function wp_die($msg = '', $title = '', $args = [])
{
    throw new RuntimeException('wp_die: ' . $msg);
}
function wc_get_order($id)
{
    return $GLOBALS['orders'][(string) $id] ?? false;
}
function wc_get_orders($args)
{
    $key   = $args['meta_key']   ?? '';
    $value = $args['meta_value'] ?? null;
    $found = [];
    foreach ($GLOBALS['orders'] as $order) {
        if ($order->get_meta($key) === $value) {
            $found[] = $order;
        }
    }

    $limit = $args['limit'] ?? -1;
    if ($limit > 0) {
        $found = array_slice($found, 0, $limit);
    }

    return $found;
}

class Fake_WC_Order
{
    private $id;
    private $meta;

    public function __construct($id, $meta = [])
    {
        $this->id   = $id;
        $this->meta = $meta;
    }

    public function get_id()
    {
        return $this->id;
    }

    public function get_meta($key)
    {
        return $this->meta[$key] ?? '';
    }
}

class WC_Gateway_Komoju
{
    public static function log($message)
    {
        $GLOBALS['logs'][] = $message;
    }
}

require_once ABSPATH . 'includes/class-wc-gateway-komoju-webhook-event.php';
require_once ABSPATH . 'includes/class-wc-gateway-komoju-response.php';

class Testable_IPN_Handler
{
    private $handler;
    private $ref;

    public function __construct()
    {
        require_once ABSPATH . 'includes/class-wc-gateway-komoju-ipn-handler.php';
        $this->ref     = new ReflectionClass('WC_Gateway_Komoju_IPN_Handler');
        $this->handler = $this->ref->newInstanceWithoutConstructor();
    }

    public function call($method, ...$args)
    {
        $m = $this->ref->getMethod($method);
        if (PHP_VERSION_ID < 80100) {
            $m->setAccessible(true);
        }

        return $m->invoke($this->handler, ...$args);
    }
}

function remember(Fake_WC_Order $order)
{
    $GLOBALS['orders'][(string) $order->get_id()] = $order;

    return $order;
}

function komoju_session($id, $metadata_order_id, $external_order_num = null)
{
    $session                                 = new stdClass();
    $session->id                             = $id;
    $session->metadata                       = new stdClass();
    $session->metadata->woocommerce_order_id = $metadata_order_id;
    $session->payment                        = new stdClass();
    $session->payment->external_order_num    = $external_order_num;

    return $session;
}

function webhook_event(array $data)
{
    return new WC_Gateway_Komoju_Webhook_Event(wp_json_encode([
        'type' => 'payment.captured',
        'data' => array_merge([
            'id'                 => 'payment_abc123',
            'status'             => 'captured',
            'external_order_num' => 'WC-254781-ABCDEF',
        ], $data),
    ]));
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

$ipn = new Testable_IPN_Handler();

$paid  = remember(new Fake_WC_Order(254781, ['komoju_session_id' => 'session_paid']));
$order = $ipn->call('get_order_from_komoju_session', komoju_session('session_paid', '254781', 'WC-K7M2-9Q4P-X3B8-ABCDEF'));
check('return loads the internal id from metadata', $order, $paid);

$GLOBALS['orders'] = [];
$paid              = remember(new Fake_WC_Order(254781, ['komoju_session_id' => 'session_paid']));
$order             = $ipn->call('get_order_from_komoju_session', komoju_session('session_paid', 'K7M2-9Q4P-X3B8', 'WC-K7M2-9Q4P-X3B8-ABCDEF'));
check('return falls back to komoju_session_id', $order, $paid);

$GLOBALS['orders'] = [];
remember(new Fake_WC_Order(42, ['komoju_session_id' => 'session_real']));
$order = $ipn->call('get_order_from_komoju_session', komoju_session('session_unknown', 'missing', 'WC-42-ABCDEF'));
check('return does not parse external_order_num', $order, null);

$GLOBALS['orders'] = [];
$paid              = remember(new Fake_WC_Order(254781, ['komoju_session_id' => 'session_paid']));
$order             = $ipn->call('get_komoju_order', webhook_event([
    'session'            => 'session_paid',
    'external_order_num' => 'WC-K7M2-9Q4P-X3B8-ABCDEF',
    'metadata'           => ['woocommerce_order_id' => '254781'],
]));
check('webhook loads the order by session id', $order, $paid);

$GLOBALS['orders'] = [];
$paid              = remember(new Fake_WC_Order(254781, []));
$order             = $ipn->call('get_komoju_order', webhook_event([
    'external_order_num' => 'WC-254781-ABCDEF',
    'metadata'           => ['woocommerce_order_id' => '254781'],
]));
check('webhook loads the internal id from metadata', $order, $paid);

$GLOBALS['orders'] = [];
remember(new Fake_WC_Order(42, []));
$order = $ipn->call('get_komoju_order', webhook_event([
    'external_order_num' => 'WC-42-ABCDEF',
    'metadata'           => ['woocommerce_order_id' => 'K7M2-9Q4P-X3B8'],
]));
check('webhook does not parse external_order_num', $order, false);

printf("\n%s\n", $fails ? "FAILED ($fails)" : 'OK');
exit($fails ? 1 : 0);
