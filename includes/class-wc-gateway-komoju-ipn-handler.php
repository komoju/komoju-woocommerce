<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once 'class-wc-gateway-komoju-response.php';
include_once 'class-wc-gateway-komoju-webhook-event.php';

/**
 * Handles responses from Komoju IPN
 */
class WC_Gateway_Komoju_IPN_Handler extends WC_Gateway_Komoju_Response
{
    protected $gateway;
    protected $webhookSecretToken;
    protected $secret_key;
    protected $invoice_prefix;
    protected $useOnHold;

    /**
     * Constructor
     */
    public function __construct($gateway, $webhookSecretToken = '', $secret_key = '', $invoice_prefix = '', $useOnHold = false)
    {
        add_filter('invoke_komoju_ipn_handler', [$this, 'check_response'], 10, 1);
        add_action('valid_komoju_standard_ipn_request', [$this, 'valid_response']);
        add_action('komoju_capture_payment_async', [$this, 'payment_complete_async'], 10, 3);

        $this->gateway            = $gateway;
        $this->webhookSecretToken = $webhookSecretToken;
        $this->secret_key         = $secret_key;
        $this->invoice_prefix     = $invoice_prefix;
        $this->useOnHold          = $useOnHold;
    }

    /**
     * Check for Komoju IPN or Session Response
     */
    public function check_response($_handled)
    {
        // callback from session page
        if (isset($_GET['session_id'])) {
            $session = $this->get_session($_GET['session_id']);
            $order   = $this->get_order_from_komoju_session($session, $this->invoice_prefix);

            // null payment on a session indicates incomplete payment flow
            if ($session->status === 'completed' && !is_null($order)) {
                $success_url = $this->gateway->get_return_url($order);
                wp_redirect($success_url);
            } elseif (is_null($session)) {
                $checkout_url = wc_get_checkout_url();
                wp_redirect($checkout_url);
                wc_add_notice(
                    __('Encountered an issue communicating with KOMOJU. Please wait a moment and try again.'),
                    'error'
                );
            } elseif (is_null($order)) {
                $checkout_url = wc_get_checkout_url();
                wp_redirect($checkout_url);
            } else {
                $payment_url = $order->get_checkout_payment_url(false);
                wp_redirect($payment_url);
            }

            return true;
        }

        // Quick setup POST from KOMOJU
        if (isset($_POST['secret_key'])) {
            $this->quick_setup($_POST);

            return true;
        }

        // Webhook (IPN)
        $entityBody = file_get_contents('php://input');
        if (!empty($entityBody) && $this->validate_hmac($entityBody)) {
            $webhookEvent = new WC_Gateway_Komoju_Webhook_Event($entityBody);

            // NOTE: direct function call doesn't work
            do_action('valid_komoju_standard_ipn_request', $webhookEvent);

            return true;
        }

        wp_die('Failed to verify KOMOJU authenticity', 'Komoju IPN', ['response' => 401]);
    }

    public function quick_setup($post)
    {
        $saved_nonce       = get_option('komoju_woocommerce_nonce');
        $nonce_from_komoju = $post['nonce'];

        if ($saved_nonce === false || $saved_nonce !== $nonce_from_komoju) {
            wp_die('Invalid nonce. Please try again.', 'KOMOJU quick setup', ['response' => 422]);

            return;
        }

        update_option('komoju_woocommerce_secret_key', $post['secret_key']);
        update_option('komoju_woocommerce_publishable_key', $post['publishable_key']);
        update_option('komoju_woocommerce_webhook_secret', $post['webhook_secret']);
        delete_option('komoju_woocommerce_nonce');

        update_option('komoju_woocommerce_just_connected_merchant_name', $post['merchant_name']);

        wp_redirect(admin_url() . 'admin.php?page=wc-settings&tab=komoju_settings');
    }

    /**
     * There was a valid response
     *
     * @param WC_Gateway_Komoju_Webhook_Event $webhookEvent Webhook event data
     */
    public function valid_response($webhookEvent)
    {
        WC_Gateway_Komoju::log('External order num: ' . $webhookEvent->external_order_num());
        WC_Gateway_Komoju::log('Uuid: ' . $webhookEvent->uuid());
        WC_Gateway_Komoju::log('Payment status: ' . $webhookEvent->status());

        $order = $this->get_komoju_order($webhookEvent, $this->invoice_prefix);
        if ($order) {
            $this->save_komoju_meta_data($order, $webhookEvent);
            switch ($webhookEvent->status()) {
                case 'captured':
                    $this->payment_status_captured($order, $webhookEvent);
                    break;
                case 'authorized':
                    $this->payment_status_authorized($order, $webhookEvent);
                    break;
                case 'expired':
                    $this->payment_status_expired($order, $webhookEvent);
                    break;
                case 'cancelled':
                    $this->payment_status_cancelled($order, $webhookEvent);
                    break;
                case 'refunded':
                    $this->payment_status_refunded($order, $webhookEvent);
                    break;
                default:
                    WC_Gateway_Komoju::log('Unknown webhook sent. Webhook type: ' . $webhookEvent->event_type());
            }
        }
    }

    /**
     * Check Komoju IPN validity (hmac control)
     *
     * @param string $requestBody the body of the request. Needed to correctly
     *                            calculate the HMAC for comparison.
     *
     * @return bool true/false to indicate whether the hmac is valid
     */
    public function validate_hmac($requestBody)
    {
        WC_Gateway_Komoju::log('Checking if IPN response is valid');

        $isDevEnv = getenv('WORDPRESS_DEV_ENV');

        $hmacHeader = $_SERVER['HTTP_X_KOMOJU_SIGNATURE'];

        $calcHmac = hash_hmac('sha256', $requestBody, $this->webhookSecretToken);

        if ($hmacHeader != $calcHmac) {
            if ($isDevEnv) {
                WC_Gateway_Komoju::log('hmac codes (sent by Komoju / recalculated) don\'t match. Continuing the process because it\'s running in dev mode....');

                return true;
            } else {
                WC_Gateway_Komoju::log('hmac codes (sent by Komoju / recalculated) don\'t match. Exiting the process...');

                return false;
            }
        }

        return true;
    }

    /**
     * Check payment amount from IPN matches the order
     *
     * @param WC_Order $order
     * @param int $amount the order amount
     */
    protected function validate_amount($order, $amount)
    {
        $order_amount = WC_Gateway_Komoju::to_cents($order->get_total(), $order->get_currency());
        if (number_format($order_amount != $amount)) {
            WC_Gateway_Komoju::log('Payment error: Amounts do not match (total: ' . $amount . ') for order #' . $order->get_id() . '(' . $order->get_total() . ')');

            // Put this order on-hold for manual checking
            $order->update_status('on-hold', sprintf(__('Validation error: Komoju amounts do not match (total %s).', 'komoju-woocommerce'), $amount));
            exit;
        }
    }

    /**
     * Handle a captured payment
     *
     * @param WC_Order $order
     * @param WC_Gateway_Komoju_Webhook_Event $webhookEvent Webhook event data
     */
    protected function payment_status_captured($order, $webhookEvent)
    {
        if ($order->has_status('captured')) {
            WC_Gateway_Komoju::log('Aborting, Order #' . $order->get_id() . ' is already complete.');
            exit;
        }

        if ($webhookEvent->currency() != $order->get_currency()) {
            $session = $this->get_session($webhookEvent->session_id());
            $this->validate_amount($order, $session->amount);
        } else {
            $this->validate_amount($order, $webhookEvent->grand_total() - $webhookEvent->payment_method_fee());
        }

        if ('captured' === $webhookEvent->status()) {
            $this->payment_complete($order, !empty($webhookEvent->uuid()) ? wc_clean($webhookEvent->uuid()) : '', __('IPN payment captured', 'komoju-woocommerce'));

            if (!empty($webhookEvent->payment_method_fee())) {
                // log komoju transaction fee
                $order->update_meta_data('Payment Gateway Transaction Fee', wc_clean($webhookEvent->payment_method_fee()));
                $order->save();
            }
        } else {
            $this->payment_on_hold($order, sprintf(__('Payment pending: %s', 'komoju-woocommerce'), $webhookEvent->additional_information()));
        }
    }

    /**
     * Handle a cancelled payment
     *
     * @param WC_Order $order
     * @param WC_Gateway_Komoju_Webhook_Event $webhookEvent Webhook event data
     */
    protected function payment_status_cancelled($order, $webhookEvent)
    {
        if (!$this->is_order_cancellable($order, $webhookEvent)) {
            $transaction_id     = $order->get_transaction_id();
            $external_order_num = $webhookEvent->external_order_num();
            WC_Gateway_Komoju::log('Aborting, transaction_id: ' . $transaction_id . ' and external_order_num: ' . $external_order_num . ' do not match.');
            exit;
        }

        $order->update_status('cancelled', sprintf(__('Payment %s via IPN.', 'komoju-woocommerce'), wc_clean($webhookEvent->status())));
    }

    /**
     * @param WC_Order $order
     *
     * @return bool return true if the order is cancellable
     */
    protected function is_order_cancellable($order, $webhookEvent)
    {
        $transaction_id = $order->get_transaction_id();
        if (empty($transaction_id)) {
            return false;
        }
        if ($transaction_id == $webhookEvent->uuid()) {
            return true;
        }

        // for backward compatibility
        // Order Statuses
        //   - processing: Payment received (paid) and stock has been reduced;
        //   - Completed — Order fulfilled and complete – requires no further action.
        //   - Refunded — Refunded by an admin – no further action required.
        // @see https://woocommerce.com/document/managing-orders/
        $skippable_statuses = ['completed', 'processing', 'refunded'];

        return $transaction_id == $webhookEvent->external_order_num() && !$order->has_status($skippable_statuses);
    }

    /**
     * Handle an expired payment
     *
     * @param WC_Order $order
     * @param WC_Gateway_Komoju_Webhook_Event $webhookEvent Webhook event data
     */
    protected function payment_status_expired($order, $webhookEvent)
    {
        $this->payment_status_cancelled($order, $webhookEvent);
    }

    /**
     * Handle an authorized payment
     *
     * @param WC_Order $order
     * @param WC_Gateway_Komoju_Webhook_Event $webhookEvent Webhook event data
     */
    protected function payment_status_authorized($order, $webhookEvent)
    {
        if ($this->useOnHold === 'yes') {
            $order->update_status('on-hold');
        } else {
            $order->update_status('pending-payment');
        }
        $order->add_order_note(sprintf(__('Payment %s via IPN.', 'komoju-woocommerce'), wc_clean($webhookEvent->status())));
    }

    /**
     * Handle a refunded order
     *
     * @param WC_Order $order
     * @param WC_Gateway_Komoju_Webhook_Event $webhookEvent Webhook event data
     */
    protected function payment_status_refunded($order, $webhookEvent)
    {
        $amount_in_cents = WC_Gateway_Komoju::to_cents($order->get_total(), $order->get_currency());
        // Only handle full refunds, not partial
        WC_Gateway_Komoju::log('Only handling full refund. Controlling that order total equals amount refunded. Does ' . $amount_in_cents . ' equals ' . $webhookEvent->grand_total() . ' ?');
        if ($amount_in_cents == $webhookEvent->amount_refunded()) {
            WC_Gateway_Komoju::log('Refunding order: ' . $order->get_id());
            $order->update_status('refunded', sprintf(__('Payment %s via IPN.', 'komoju-woocommerce'), strtolower($webhookEvent->status())));
        }
    }

    /**
     * Retrieve session from KOMOJU
     *
     * @param string $session_id
     */
    private function get_session($session_id)
    {
        $client = new KomojuApi($this->secret_key);

        try {
            $session = $client->session($session_id);

            return $session;
        } catch (KomojuExceptionBadServer|KomojuExceptionBadJson $e) {
            return null;
        }
    }

    /**
     * Save important data from the IPN to the order
     *
     * @param WC_Order $order
     * @param WC_Gateway_Komoju_Webhook_Event $webhookEvent Webhook event data
     */
    protected function save_komoju_meta_data($order, $webhookEvent)
    {
        if (!empty($webhookEvent->tax())) {
            $order->update_meta_data('Tax', wc_clean($webhookEvent->tax()));
        }
        if (!empty($webhookEvent->amount())) {
            $order->update_meta_data('Amount', wc_clean($webhookEvent->amount()));
        }
        if (!empty($webhookEvent->additional_information())) {
            $order->update_meta_data('Additional info', wc_clean(print_r($webhookEvent->additional_information(), true)));
        }
        if (!empty($webhookEvent->uuid())) {
            $order->update_meta_data('komoju_payment_id', $webhookEvent->uuid(), true);
        }
        $order->save();
    }
}
