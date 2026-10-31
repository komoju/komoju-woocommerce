<?php

/**
 * Exercise webhook and background completion without WordPress or a database.
 * Real database concurrency is additionally checked in the local smoke run.
 * Run: php tests/php/ipn-async-completion-test.php
 */
define('ABSPATH', dirname(__DIR__, 2) . '/');
define('DB_NAME', 'komoju_test');

$actions        = [];
$orders         = [];
$retries        = [];
$async          = false;
$retry_succeeds = true;
$after_lock     = null;

function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
{
    $GLOBALS['actions'][$hook][$priority][] = [$callback, $accepted_args];
}
function add_filter()
{
}
function plugin_basename($file)
{
    return $file;
}
function __($text, $domain = null)
{
    return $text;
}
function wc_clean($value)
{
    return $value;
}
function wp_json_encode($value)
{
    return json_encode($value);
}
function get_option($key)
{
    if ($key !== 'komoju_woocommerce_ipn_async') {
        return false;
    }

    return $GLOBALS['async'] ? 'yes' : 'no';
}
function wc_get_order($id)
{
    return isset($GLOBALS['orders'][$id]) ? clone $GLOBALS['orders'][$id] : false;
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
function clean_post_cache($id)
{
}
function wp_die($message, $title, $args)
{
    throw new RuntimeException($message, $args['response']);
}
function as_enqueue_async_action($hook, $args, $group)
{
    $GLOBALS['retries'][] = [$hook, $args, $group];

    return count($GLOBALS['retries']);
}
function as_schedule_single_action($when, $hook, $args, $group)
{
    if (!$GLOBALS['retry_succeeds']) {
        return 0;
    }

    return as_enqueue_async_action($hook, $args, $group);
}
function do_action($hook, ...$args)
{
    $callbacks = $GLOBALS['actions'][$hook] ?? [];
    ksort($callbacks);
    foreach ($callbacks as $registered) {
        foreach ($registered as [$callback, $accepted_args]) {
            call_user_func_array($callback, array_slice($args, 0, $accepted_args));
        }
    }
}

class wpdb
{
    public $prefix   = 'wp_';
    public $is_mysql = true;
    public $result   = '1';
    public $held     = false;

    public function prepare($sql, $name)
    {
        return sprintf($sql, "'" . $name . "'");
    }

    public function get_var($sql)
    {
        if (strpos($sql, 'GET_LOCK') !== false) {
            if ($this->held) {
                return '0';
            }
            if ($this->result === '1') {
                $this->held = true;
                if ($GLOBALS['after_lock']) {
                    $callback              = $GLOBALS['after_lock'];
                    $GLOBALS['after_lock'] = null;
                    $callback();
                }
            }

            return $this->result;
        }
        $this->held = false;

        return '1';
    }
}
$wpdb = new wpdb();

class Unknown_DB_Adapter extends wpdb
{
    public function get_var($sql)
    {
        throw new RuntimeException('Database adapter must not receive locking SQL');
    }

    public function db_server_info()
    {
        return '8.0.0';
    }
}

class WC_Data_Store
{
    public static function load($name)
    {
        return new self();
    }

    public function has_callable($method)
    {
        return false;
    }
}
class WC_Gateway_Komoju
{
    public static function log($message)
    {
    }

    public static function to_cents($amount, $currency)
    {
        return $amount;
    }
}
class Fake_Async_Order
{
    public $id;
    public $status       = 'pending';
    public $notes        = [];
    public $transactions = [];
    public $meta         = [];
    public $fail         = false;

    public function __construct($id)
    {
        $this->id   = $id;
        $this->meta = ['komoju_session_id' => 'session_' . $id];
    }

    public function get_id()
    {
        return $this->id;
    }

    public function is_paid()
    {
        return in_array($this->status, ['processing', 'completed'], true);
    }

    public function has_status($status)
    {
        return $this->status === $status;
    }

    public function get_currency()
    {
        return 'JPY';
    }

    public function get_total()
    {
        return 100;
    }

    public function get_meta($key)
    {
        return $this->meta[$key] ?? '';
    }

    public function read_meta_data($force)
    {
        $this->meta = $GLOBALS['orders'][$this->id]->meta;
    }

    public function update_meta_data($key, $value, $meta_id = 0)
    {
        $this->meta[$key] = $value;
    }

    public function save()
    {
        $GLOBALS['orders'][$this->id] = clone $this;
    }

    public function add_order_note($note)
    {
        $this->notes[] = $note;
        $this->save();
    }

    public function payment_complete($transaction_id)
    {
        if ($this->fail) {
            throw new RuntimeException('Injected completion failure');
        }
        if ($this->is_paid() || $this->has_status('refunded')) {
            return;
        }
        $this->transactions[] = $transaction_id;
        $this->status         = 'processing';
        $this->save();
    }
}

require_once ABSPATH . 'index.php';
do_action('plugins_loaded');

function event_for($id, $type = 'payment.captured')
{
    return new WC_Gateway_Komoju_Webhook_Event(json_encode([
        'type' => $type,
        'data' => [
            'id'                 => 'payment_' . $id, 'status' => 'captured',
            'external_order_num' => $id . '-ABCDEF', 'session' => 'session_' . $id,
            'metadata'           => ['woocommerce_order_id' => (string) $id],
            'amount'             => 100, 'total' => 100, 'currency' => 'JPY',
            'tax'                => 0, 'payment_method_fee' => 0, 'payment_details' => ['type' => 'rakutenpay'],
        ],
    ]));
}
function run_worker($id)
{
    do_action('komoju_capture_payment_async', $id, 'IPN payment captured', 'payment_' . $id);
}
$fails = 0;
function check($label, $actual, $expected)
{
    $pass = $actual === $expected;
    printf("%s %s\n", $pass ? '[PASS]' : '[FAIL]', $label);
    if (!$pass) {
        printf("expected %s, got %s\n", var_export($expected, true), var_export($actual, true));
        ++$GLOBALS['fails'];
    }
}

// Background jobs must work after plugin bootstrap, before any IPN handler exists.
$orders[42] = new Fake_Async_Order(42);
run_worker(42);
run_worker(42);
check('duplicate jobs produce one IPN note', $orders[42]->notes, ['IPN payment captured']);
check('bootstrap worker completes payment only once', $orders[42]->transactions, ['payment_42']);
check('worker preserves successful paid status', $orders[42]->status, 'processing');

$orders[43]         = new Fake_Async_Order(43);
$orders[43]->status = 'refunded';
run_worker(43);
check('late worker leaves refunded order untouched', [$orders[43]->notes, $orders[43]->transactions, $orders[43]->status], [[], [], 'refunded']);

require_once ABSPATH . 'includes/class-wc-gateway-komoju-ipn-handler.php';
$handler = new WC_Gateway_Komoju_IPN_Handler(null);

$orders[44] = new Fake_Async_Order(44);
$after_lock = function () use (&$orders) { $orders[44]->status = 'processing'; };
$handler->valid_response(event_for(44));
check('webhook rechecks state changed while waiting for lock', $orders[44]->transactions, []);

$orders[45] = new Fake_Async_Order(45);
$handler->valid_response(event_for(45, 'payment.authorized'));
$handler->valid_response(event_for(45));
check('different event types reporting capture complete once', $orders[45]->transactions, ['payment_45']);
check('different event types reporting capture add one IPN note', $orders[45]->notes, ['IPN payment captured']);

$orders[46]   = new Fake_Async_Order(46);
$wpdb->result = '0';
$http_status  = null;
try {
    $handler->valid_response(event_for(46));
} catch (RuntimeException $e) {
    $http_status = $e->getCode();
}
check('busy webhook returns a retryable status', $http_status, 503);
check('busy webhook does not consume event or mutate order', [$orders[46]->meta, $orders[46]->notes, $orders[46]->transactions], [['komoju_session_id' => 'session_46'], [], []]);
run_worker(46);
check('busy worker defers completion', $orders[46]->transactions, []);
$wpdb->result = '1';
foreach ($retries as [$hook, $args, $group]) {
    do_action($hook, ...$args);
}
check('deferred worker can complete when contention clears', $orders[46]->transactions, ['payment_46']);

$orders[47]     = new Fake_Async_Order(47);
$wpdb->result   = '0';
$retry_succeeds = false;
$failed         = false;
try {
    run_worker(47);
} catch (RuntimeException $e) {
    $failed = true;
}
check('failed deferral is surfaced rather than silently dropping payment', $failed, true);
$retry_succeeds = true;
$wpdb->result   = null;
$failed         = false;
try {
    run_worker(47);
} catch (RuntimeException $e) {
    $failed = true;
}
check('database lock error fails closed', [$failed, $orders[47]->transactions], [true, []]);

$wpdb->result     = '1';
$orders[48]       = new Fake_Async_Order(48);
$orders[48]->fail = true;
try {
    run_worker(48);
} catch (RuntimeException $e) {
}
$orders[49] = new Fake_Async_Order(49);
run_worker(49);
check('completion exception releases lock for subsequent work', $orders[49]->transactions, ['payment_49']);

$orders[50] = new Fake_Async_Order(50);
$async      = true;
$retries    = [];
$handler->valid_response(event_for(50, 'payment.authorized'));
$handler->valid_response(event_for(50));
foreach ($retries as [$hook, $args, $group]) {
    do_action($hook, ...$args);
}
check('two notifications queued before completion produce one completion', [$orders[50]->notes, $orders[50]->transactions], [['IPN payment captured'], ['payment_50']]);

// Even adapters advertising MySQL compatibility must use the original paths.
$wpdb       = new Unknown_DB_Adapter();
$async      = false;
$orders[51] = new Fake_Async_Order(51);
$handler->valid_response(event_for(51));
check('unknown adapter completes a webhook without locking SQL', $orders[51]->transactions, ['payment_51']);

$orders[52] = new Fake_Async_Order(52);
run_worker(52);
run_worker(52);
check('unknown adapter retains legacy duplicate worker notes', $orders[52]->notes, ['IPN payment captured', 'IPN payment captured']);
check('unknown adapter retains WooCommerce completion behavior', $orders[52]->transactions, ['payment_52']);

$orders[53]         = new Fake_Async_Order(53);
$orders[53]->status = 'refunded';
run_worker(53);
check('unknown adapter retains legacy refunded worker behavior', [$orders[53]->status, $orders[53]->notes], ['refunded', ['IPN payment captured']]);

$orders[54] = new Fake_Async_Order(54);
$async      = true;
$retries    = [];
$handler->valid_response(event_for(54));
foreach ($retries as [$hook, $args, $group]) {
    do_action($hook, ...$args);
}
check('unknown adapter preserves queued payment completion', $orders[54]->transactions, ['payment_54']);

$wpdb           = new wpdb();
$wpdb->is_mysql = false;
$wpdb->result   = null;
$orders[55]     = new Fake_Async_Order(55);
run_worker(55);
check('a connection not identified as MySQL uses original completion', $orders[55]->transactions, ['payment_55']);

printf("\n%s\n", $fails ? "FAILED ($fails)" : 'OK');
exit($fails ? 1 : 0);
