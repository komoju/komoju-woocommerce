<?php

/**
 * Expired / cancelled KOMOJU payments must cancel the WooCommerce order,
 * while stale webhooks from an earlier payment attempt must be ignored.
 *
 * Regression guard for 3.2.6 (PR #166), where these handlers became no-ops.
 *
 * No WordPress/WooCommerce/DB needed. Run: php tests/php/ipn-cancellation-test.php
 */
define('ABSPATH', dirname(__DIR__, 2) . '/');

// --- WordPress / WooCommerce stubs -----------------------------------------

$logs = [];

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
    return is_string($v) ? trim(strip_tags($v)) : $v;
}
function wp_json_encode($v)
{
    return json_encode($v);
}
function get_option($n, $d = false)
{
    return $d;
}
function wp_die($msg = '', $title = '', $args = [])
{
    throw new RuntimeException('wp_die: ' . $msg);
}
function wc_get_order($id)
{
    return $GLOBALS['orders'][$id] ?? false;
}

/**
 * Minimal WC_Order stand-in that records status transitions.
 */
class Fake_WC_Order
{
    private $id;
    private $status;
    private $meta;
    private $transaction_id;

    public $status_history = [];

    public function __construct($id, $status = 'pending', $meta = [], $transaction_id = '')
    {
        $this->id             = $id;
        $this->status         = $status;
        $this->meta           = $meta;
        $this->transaction_id = $transaction_id;
    }

    public function get_id()
    {
        return $this->id;
    }

    public function get_status()
    {
        return $this->status;
    }

    public function has_status($status)
    {
        return is_array($status)
            ? in_array($this->status, $status, true)
            : $this->status === $status;
    }

    // Mirrors WooCommerce: the "paid" statuses are processing and completed.
    public function is_paid()
    {
        return in_array($this->status, ['processing', 'completed'], true);
    }

    public function update_status($status, $note = '')
    {
        $this->status           = $status;
        $this->status_history[] = ['status' => $status, 'note' => $note];
    }

    public function get_meta($key)
    {
        return $this->meta[$key] ?? '';
    }

    public function update_meta_data($key, $value, $unique = false)
    {
        $this->meta[$key] = $value;
    }

    public function get_transaction_id()
    {
        return $this->transaction_id;
    }

    public function add_order_note($note)
    {
    }

    public function save()
    {
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

/**
 * Exposes the protected handlers without booting the real constructor.
 */
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

        // Required on PHP < 8.1 (CI runs 7.4); deprecated no-op from 8.5.
        if (PHP_VERSION_ID < 80100) {
            $m->setAccessible(true);
        }

        return $m->invoke($this->handler, ...$args);
    }
}

/** Build a webhook event payload the way KOMOJU sends it. */
function webhook_event(array $overrides = [])
{
    $data = array_merge([
        'id'                 => 'payment_abc123',
        'status'             => 'expired',
        'external_order_num' => 'wc-42-abcdefg',
        'session'            => 'session_current',
        'currency'           => 'JPY',
        'total'              => 1000,
    ], $overrides);

    return new WC_Gateway_Komoju_Webhook_Event(wp_json_encode([
        'type' => 'payment.expired',
        'data' => $data,
    ]));
}

// --- Assertions ------------------------------------------------------------

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

// --- Expiry must cancel ----------------------------------------------------

$order = new Fake_WC_Order(42, 'pending', ['komoju_session_id' => 'session_current']);
$ipn->call('payment_status_expired', $order, webhook_event());
check('expired webhook cancels pending order', $order->get_status(), 'cancelled');
check('cancellation records an order note', $order->status_history[0]['note'], 'Payment expired via IPN.');

// on-hold is used when the "use on-hold" setting is enabled.
$order = new Fake_WC_Order(42, 'on-hold', ['komoju_session_id' => 'session_current']);
$ipn->call('payment_status_expired', $order, webhook_event());
check('expired webhook cancels on-hold order', $order->get_status(), 'cancelled');

$order = new Fake_WC_Order(42, 'pending', ['komoju_session_id' => 'session_current']);
$ipn->call('payment_status_cancelled', $order, webhook_event(['status' => 'cancelled']));
check('cancelled webhook cancels pending order', $order->get_status(), 'cancelled');

// --- Stale webhooks must NOT cancel (the PR #166 retry scenario) -----------

// Customer cancelled attempt #1, then retried and got a new session.
$order = new Fake_WC_Order(42, 'pending', ['komoju_session_id' => 'session_retry']);
$ipn->call('payment_status_cancelled', $order, webhook_event([
    'status'  => 'cancelled',
    'session' => 'session_old',
]));
check('stale session cancellation is ignored', $order->get_status(), 'pending');
check('stale session causes no status change', $order->status_history, []);

$order = new Fake_WC_Order(42, 'pending', ['komoju_session_id' => 'session_retry']);
$ipn->call('payment_status_expired', $order, webhook_event(['session' => 'session_old']));
check('stale session expiry is ignored', $order->get_status(), 'pending');

// --- Paid orders must never be downgraded ---------------------------------

foreach (['processing', 'completed', 'refunded'] as $status) {
    $order = new Fake_WC_Order(42, $status, ['komoju_session_id' => 'session_current']);
    $ipn->call('payment_status_cancelled', $order, webhook_event(['status' => 'cancelled']));
    check("$status order is not downgraded to cancelled", $order->get_status(), $status);
}

// --- Legacy orders without session tracking -------------------------------

$order = new Fake_WC_Order(42, 'pending', [], 'payment_abc123');
$ipn->call('payment_status_expired', $order, webhook_event());
check('legacy order matched by uuid cancels', $order->get_status(), 'cancelled');

$order = new Fake_WC_Order(42, 'pending', [], 'wc-42-abcdefg');
$ipn->call('payment_status_expired', $order, webhook_event());
check('legacy order matched by external_order_num cancels', $order->get_status(), 'cancelled');

$order = new Fake_WC_Order(42, 'pending', [], 'payment_someone_else');
$ipn->call('payment_status_expired', $order, webhook_event());
check('legacy order with mismatched transaction_id is ignored', $order->get_status(), 'pending');

// Nothing identifies the payment, so we must not cancel. Orders created
// between 3.2.6 and this fix are in this state and need manual cleanup.
$order = new Fake_WC_Order(42, 'pending', [], '');
$ipn->call('payment_status_expired', $order, webhook_event());
check('unidentifiable order is left alone', $order->get_status(), 'pending');

// --- Malformed payloads ---------------------------------------------------

// session_id() must tolerate a payload with no "session" key.
$event = new WC_Gateway_Komoju_Webhook_Event(wp_json_encode([
    'type' => 'payment.expired',
    'data' => ['id' => 'payment_abc123', 'status' => 'expired', 'external_order_num' => 'wc-42-abcdefg'],
]));
check('session_id() returns null when absent', $event->session_id(), null);

$order = new Fake_WC_Order(42, 'pending', ['komoju_session_id' => 'session_current']);
$ipn->call('payment_status_expired', $order, $event);
check('sessionless webhook does not cancel a session-tracked order', $order->get_status(), 'pending');

printf("\n%s\n", $fails ? "FAILED ($fails)" : 'OK');
exit($fails ? 1 : 0);
