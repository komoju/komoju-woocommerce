<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

abstract class WC_Gateway_Komoju_Response
{
    /**
     * Get the order for a KOMOJU webhook.
     *
     * Session id first, then the internal order ID in metadata.
     * external_order_num is a display reference and is not parsed.
     *
     * @param WC_Gateway_Komoju_Webhook_Event $webhookEvent Webhook event data
     *
     * @return bool|WC_Order object
     */
    protected function get_komoju_order($webhookEvent)
    {
        $order = $this->order_from_session_id($webhookEvent->session_id());
        if ($order) {
            return $order;
        }

        $order_id = $webhookEvent->woocommerce_order_id();
        $order    = $order_id ? wc_get_order($order_id) : false;
        if ($order) {
            return $order;
        }

        WC_Gateway_Komoju::log('Error: Cannot locate order for KOMOJU webhook. session_id: ' . (string) $webhookEvent->session_id() . ' woocommerce_order_id: ' . (string) $order_id);

        return false;
    }

    /**
     * Get the order for a KOMOJU session return.
     *
     * Internal order ID first, then komoju_session_id.
     *
     * @param object $session KOMOJU session
     *
     * @return WC_Order|null
     */
    protected function get_order_from_komoju_session($session)
    {
        $order_id = $session->metadata->woocommerce_order_id ?? null;
        $order    = $order_id ? wc_get_order($order_id) : false;
        if ($order) {
            return $order;
        }

        $session_id = isset($session->id) ? $session->id : null;
        $order      = $this->order_from_session_id($session_id);
        if ($order) {
            return $order;
        }

        WC_Gateway_Komoju::log('Error: Cannot locate order for KOMOJU session: ' . (string) $session_id);

        return null;
    }

    /**
     * @param string|null $session_id
     *
     * @return WC_Order|false
     */
    private function order_from_session_id($session_id)
    {
        if (!is_string($session_id) || $session_id === '') {
            return false;
        }

        $orders = wc_get_orders([
            'limit'      => 1,
            'meta_key'   => 'komoju_session_id',
            'meta_value' => $session_id,
            'return'     => 'objects',
        ]);

        if (!is_array($orders) || empty($orders)) {
            return false;
        }

        return $orders[0];
    }

    /**
     * Adapters may emulate MySQL versions/flags without supporting named locks.
     * Only the core driver is known; custom database drivers keep legacy behavior.
     */
    protected static function supports_order_lock()
    {
        global $wpdb;

        $supported = $wpdb instanceof wpdb && get_class($wpdb) === 'wpdb' && $wpdb->is_mysql === true;
        if (!$supported) {
            self::log_order_lock('Order locking unavailable for database driver ' . (is_object($wpdb) ? get_class($wpdb) : gettype($wpdb)) . '; processing without serialization.');
        }

        return $supported;
    }

    /**
     * Gateway logging is configured by its constructor, but background
     * workers can run before any gateway is constructed.
     */
    protected static function log_order_lock($message)
    {
        $debug = get_option('komoju_woocommerce_debug_log');
        if (!$debug) {
            $legacy = get_option('woocommerce_komoju_settings');
            $debug  = is_array($legacy) && isset($legacy['debug']) ? $legacy['debug'] : 'no';
        }
        if ($debug === 'yes') {
            wc_get_logger()->debug($message, ['source' => 'komoju']);
        }
    }

    /**
     * Serialize webhook and worker writes, then reload outside all order caches.
     * MySQL/MariaDB named locks are connection-owned and need no schema changes.
     *
     * @return bool false on contention; callers must leave the work retryable
     */
    protected static function with_order_lock($order_id, $callback)
    {
        global $wpdb;

        $lock     = 'komoju_order_' . md5(DB_NAME . ':' . $wpdb->prefix . ':' . (int) $order_id);
        $acquired = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock));
        if ((string) $acquired === '0') {
            return false;
        }
        if ((string) $acquired !== '1') {
            throw new RuntimeException('Could not acquire KOMOJU order lock for order ' . $order_id);
        }

        $released = false;
        $release  = static function () use ($wpdb, $lock, &$released) {
            if (!$released) {
                $released = true;
                $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
            }
        };
        // Amount validation and WordPress hooks can exit without running finally.
        register_shutdown_function($release);

        try {
            clean_post_cache($order_id);
            $data_store = WC_Data_Store::load('order');
            if ($data_store->has_callable('clear_cached_data')) {
                $data_store->clear_cached_data([$order_id]);
            }
            if (class_exists('\Automattic\WooCommerce\Caches\OrderCache')) {
                wc_get_container()->get(Automattic\WooCommerce\Caches\OrderCache::class)->remove($order_id);
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                throw new RuntimeException('Cannot reload KOMOJU order ' . $order_id);
            }
            $order->read_meta_data(true);
            $callback($order);

            return true;
        } finally {
            $release();
        }
    }

    /**
     * Complete order, add transaction ID and note
     *
     * @param WC_Order $order
     * @param string $txn_id
     * @param string $note
     */
    protected function payment_complete($order, $txn_id = '', $note = '')
    {
        if (get_option('komoju_woocommerce_ipn_async') == 'yes') {
            $order_id = $order->get_id();
            as_enqueue_async_action('komoju_capture_payment_async', [$order_id, $note, $txn_id], 'komoju-capture');
        } else {
            self::do_payment_complete($order, $note, $txn_id);
        }
    }

    public static function payment_complete_async($order_id, $note, $txn_id)
    {
        if (!self::supports_order_lock()) {
            self::do_payment_complete(wc_get_order($order_id), $note, $txn_id);

            return;
        }

        $processed = self::with_order_lock($order_id, static function ($order) use ($note, $txn_id) {
            if ($order->is_paid() || $order->has_status('refunded')) {
                return;
            }
            self::do_payment_complete($order, $note, $txn_id);
        });

        if (!$processed) {
            // A failed Action Scheduler action is not automatically retried.
            $retry = as_schedule_single_action(time() + 60, 'komoju_capture_payment_async', [$order_id, $note, $txn_id], 'komoju-capture');
            if (!$retry) {
                throw new RuntimeException('Could not reschedule KOMOJU completion for order ' . $order_id);
            }
            self::log_order_lock('Order #' . $order_id . ' is locked; rescheduled background completion as action #' . $retry . ' in 60 seconds.');
        }
    }

    protected static function do_payment_complete($order, $note, $txn_id)
    {
        $order->add_order_note($note);
        $order->payment_complete($txn_id);
    }

    /**
     * Hold order and add note
     *
     * @param WC_Order $order
     * @param string $reason
     */
    protected function payment_on_hold($order, $reason = '')
    {
        $order->update_status('on-hold', $reason);
        $order->reduce_order_stock();
        WC()->cart->empty_cart();
    }
}
