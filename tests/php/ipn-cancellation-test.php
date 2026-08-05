<?php

/**
 * Expired / cancelled KOMOJU payments must cancel the WooCommerce order.
 *
 * Regression guard for the behaviour removed in 3.2.6 (commit 6efbe93, PR #166),
 * where payment_status_expired() and payment_status_cancelled() became log-only
 * no-ops and orders were left stuck in pending forever.
 *
 * The tricky part is that we must cancel on a genuine expiry while still ignoring
 * *stale* webhooks from an earlier payment attempt -- the retry bug PR #166 was
 * actually trying to fix. Both directions are asserted here.
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
 * Minimal WC_Order stand-in. Records status transitions so tests can assert on
 * them, and mirrors the handful of WooCommerce semantics the handler relies on:
 * is_paid() covering processing/completed, plus meta and transaction id.
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
 * Exposes the protected handlers under test without booting the real
 * constructor (which registers hooks and needs API credentials).
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
        // No setAccessible() call: it is a no-op since PHP 8.1 and deprecated in 8.5.
        return $this->ref->getMethod($method)->invoke($this->handler, ...$args);
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

// --- The merchant-reported bug: expiry must cancel -------------------------

// A konbini/bank-transfer order whose KOMOJU payment expired after the
// configured window. This is the case that silently stopped working in 3.2.6.
$order = new Fake_WC_Order(42, 'pending', ['komoju_session_id' => 'session_current']);
$ipn->call('payment_status_expired', $order, webhook_event());
check('expired webhook cancels pending order', $order->get_status(), 'cancelled');
check('cancellation records an order note', $order->status_history[0]['note'], 'Payment expired via IPN.');

// on-hold is the status used when "use on-hold" is enabled; it must also cancel.
$order = new Fake_WC_Order(42, 'on-hold', ['komoju_session_id' => 'session_current']);
$ipn->call('payment_status_expired', $order, webhook_event());
check('expired webhook cancels on-hold order', $order->get_status(), 'cancelled');

// An explicit cancellation from the KOMOJU side.
$order = new Fake_WC_Order(42, 'pending', ['komoju_session_id' => 'session_current']);
$ipn->call('payment_status_cancelled', $order, webhook_event(['status' => 'cancelled']));
check('cancelled webhook cancels pending order', $order->get_status(), 'cancelled');

// --- Stale webhooks must NOT cancel (the PR #166 retry scenario) -----------

// Customer cancelled attempt #1, then retried and got a new session. The late
// webhook for the old session must not touch the order.
$order = new Fake_WC_Order(42, 'pending', ['komoju_session_id' => 'session_retry']);
$ipn->call('payment_status_cancelled', $order, webhook_event([
    'status'  => 'cancelled',
    'session' => 'session_old',
]));
check('stale session cancellation is ignored', $order->get_status(), 'pending');
check('stale session causes no status change', $order->status_history, []);

// Same, but the stale event is an expiry rather than a cancellation.
$order = new Fake_WC_Order(42, 'pending', ['komoju_session_id' => 'session_retry']);
$ipn->call('payment_status_expired', $order, webhook_event(['session' => 'session_old']));
check('stale session expiry is ignored', $order->get_status(), 'pending');

// --- Paid orders must never be downgraded ---------------------------------

// Out-of-order delivery: capture landed first, then a stale cancel arrives.
foreach (['processing', 'completed', 'refunded'] as $status) {
    $order = new Fake_WC_Order(42, $status, ['komoju_session_id' => 'session_current']);
    $ipn->call('payment_status_cancelled', $order, webhook_event(['status' => 'cancelled']));
    check("$status order is not downgraded to cancelled", $order->get_status(), $status);
}

// --- Legacy orders without session tracking -------------------------------

// Pre-3.2.6 orders identified by transaction_id matching the payment uuid.
$order = new Fake_WC_Order(42, 'pending', [], 'payment_abc123');
$ipn->call('payment_status_expired', $order, webhook_event());
check('legacy order matched by uuid cancels', $order->get_status(), 'cancelled');

// ...or by transaction_id matching external_order_num.
$order = new Fake_WC_Order(42, 'pending', [], 'wc-42-abcdefg');
$ipn->call('payment_status_expired', $order, webhook_event());
check('legacy order matched by external_order_num cancels', $order->get_status(), 'cancelled');

// A legacy transaction_id belonging to a different payment must be ignored.
$order = new Fake_WC_Order(42, 'pending', [], 'payment_someone_else');
$ipn->call('payment_status_expired', $order, webhook_event());
check('legacy order with mismatched transaction_id is ignored', $order->get_status(), 'pending');

// Neither session meta nor transaction_id: nothing identifies the payment, so
// we must not cancel. NOTE: this is the state of orders created between 3.2.6
// and the fix -- they cannot be auto-cancelled and need manual cleanup.
$order = new Fake_WC_Order(42, 'pending', [], '');
$ipn->call('payment_status_expired', $order, webhook_event());
check('unidentifiable order is left alone', $order->get_status(), 'pending');

// --- Malformed payloads ---------------------------------------------------

// session_id() must tolerate a payload with no "session" key rather than
// emitting a PHP notice mid-webhook.
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
