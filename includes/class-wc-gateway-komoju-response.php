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
        self::do_payment_complete(wc_get_order($order_id), $note, $txn_id);
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
