<?php

/**
 * Asynchronous IPN completion must be registered during plugin bootstrap.
 *
 * The Action Scheduler runner is a separate WP-Cron request and must not rely
 * on a WC_Gateway_Komoju or WC_Gateway_Komoju_IPN_Handler instance existing.
 *
 * No WordPress/WooCommerce/DB needed. Run: php tests/php/ipn-async-completion-test.php
 */
define('ABSPATH', dirname(__DIR__, 2) . '/');

$actions          = [];
$filters          = [];
$orders           = [];
$looked_up_orders = [];

function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
{
    $GLOBALS['actions'][$hook][$priority][] = [
        'callback'      => $callback,
        'accepted_args' => $accepted_args,
    ];
}

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
{
    $GLOBALS['filters'][$hook][$priority][] = [
        'callback'      => $callback,
        'accepted_args' => $accepted_args,
    ];
}

function has_action($hook, $callback = false)
{
    foreach ($GLOBALS['actions'][$hook] ?? [] as $priority => $callbacks) {
        foreach ($callbacks as $registered) {
            if ($callback === false || $registered['callback'] === $callback) {
                return $priority;
            }
        }
    }

    return false;
}

function action_accepted_args($hook, $callback)
{
    foreach ($GLOBALS['actions'][$hook] ?? [] as $callbacks) {
        foreach ($callbacks as $registered) {
            if ($registered['callback'] === $callback) {
                return $registered['accepted_args'];
            }
        }
    }

    return false;
}

function do_action($hook, ...$args)
{
    $callbacks = $GLOBALS['actions'][$hook] ?? [];
    ksort($callbacks);

    foreach ($callbacks as $registered_callbacks) {
        foreach ($registered_callbacks as $registered) {
            call_user_func_array(
                $registered['callback'],
                array_slice($args, 0, $registered['accepted_args'])
            );
        }
    }
}

function plugin_basename($file)
{
    return $file;
}

function wc_get_order($order_id)
{
    $GLOBALS['looked_up_orders'][] = $order_id;

    return $GLOBALS['orders'][$order_id] ?? false;
}

class Fake_Async_Order
{
    public $notes                  = [];
    public $payment_complete_calls = [];
    public $paid                   = false;

    public function add_order_note($note)
    {
        $this->notes[] = $note;
    }

    public function payment_complete($transaction_id)
    {
        $this->payment_complete_calls[] = $transaction_id;
        $this->paid                     = true;
    }
}

require_once ABSPATH . 'index.php';

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

// Bootstrap the plugin without constructing a gateway or IPN handler.
do_action('plugins_loaded');

$callback = ['WC_Gateway_Komoju_Response', 'payment_complete_async'];
check('async callback is registered during plugin bootstrap', has_action('komoju_capture_payment_async', $callback), 10);
check('async callback accepts all action arguments', action_accepted_args('komoju_capture_payment_async', $callback), 3);
check('legacy gateway is not constructed during bootstrap', class_exists('WC_Gateway_Komoju', false), false);
check('IPN handler is not constructed during bootstrap', class_exists('WC_Gateway_Komoju_IPN_Handler', false), false);

$order_id                     = 8950;
$GLOBALS['orders'][$order_id] = new Fake_Async_Order();
do_action('komoju_capture_payment_async', $order_id, 'IPN payment captured', 'payment_abc123');

$order = $GLOBALS['orders'][$order_id];
check('async callback loads the queued order', $GLOBALS['looked_up_orders'], [$order_id]);
check('async callback records the IPN note', $order->notes, ['IPN payment captured']);
check('async callback records the KOMOJU payment ID', $order->payment_complete_calls, ['payment_abc123']);
check('async callback completes the order', $order->paid, true);

printf("\n%s\n", $fails ? "FAILED ($fails)" : 'OK');
exit($fails ? 1 : 0);
