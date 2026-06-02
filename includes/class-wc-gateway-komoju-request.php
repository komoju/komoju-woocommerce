<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Generates requests to send to Komoju
 */
class WC_Gateway_Komoju_Request
{
    /**
     * Stores line items to send to Komoju
     *
     * @var array
     */
    protected $line_items = [];

    /**
     * Pointer to gateway making the request
     *
     * @var WC_Gateway_Komoju
     */
    protected $gateway;

    /**
     * Constructor
     *
     * @param WC_Gateway_Komoju $gateway
     */
    public function __construct($gateway)
    {
        $this->gateway         = $gateway;
        $this->request_id      = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 6);
        $this->Komoju_endpoint = '/ja/api/' . $this->gateway->accountID . '/transactions/';
    }

    /**
     * Get the Komoju request URL for an order
     *
     * @param WC_Order $order
     *
     * @return string
     */
    public function get_request_url($order, $method = 'credit_card')
    {
        $komoju_args = $this->get_komoju_args($order, $method);

        return 'https://komoju.com' . $this->Komoju_endpoint . $method . '/new?' . $komoju_args;
    }

    /**
     * Get Komoju Args for passing to Komoju hosted page
     *
     * @param WC_Order $order
     * @param string $method the method of payment the user has selected, ie credit-card
     *
     * @return array
     */
    protected function get_komoju_args($order, $method)
    {
        WC_Gateway_Komoju::log('Generating payment form for order ' . $order->get_order_number());

        $params = [
            'transaction[amount]'                => $order->get_total(),
            'transaction[currency]'              => get_woocommerce_currency(),
            'transaction[customer][email]'       => $order->get_billing_email(),
            'transaction[customer][phone]'       => $order->get_billing_phone(),
            'transaction[customer][given_name]'  => $order->get_billing_first_name(),
            'transaction[customer][family_name]' => $order->get_billing_last_name(),
            'transaction[external_order_num]'    => $this->gateway->get_option('invoice_prefix') . $order->get_order_number() . '-' . $this->request_id,
            'transaction[return_url]'            => $this->gateway->get_return_url($order),
            'transaction[cancel_url]'            => $order->get_cancel_order_url_raw(),
            'transaction[tax]'                   => 0,
            'timestamp'                          => time(),
            'via'                                => 'woocommerce',
        ];
        WC_Gateway_Komoju::log('Raw parameters: ' . wp_json_encode($params));

        $qs_params = [];
        foreach ($params as $key => $val) {
            $qs_params[] = urlencode($key) . '=' . urlencode($val);
        }
        sort($qs_params);
        $query_string = implode('&', $qs_params);

        $url  = $this->Komoju_endpoint . $method . '/new?' . $query_string;
        $hmac = hash_hmac('sha256', $url, $this->gateway->secretKey);
        $query_string .= '&hmac=' . $hmac;

        return $query_string;
    }

    /**
     * Check if currency has decimals
     *
     * @param string $currency
     *
     * @return bool
     */
    protected function currency_has_decimals($currency)
    {
        if (in_array($currency, ['HUF', 'JPY', 'TWD'])) {
            return false;
        }

        return true;
    }

    /**
     * Round prices
     *
     * @param float|int $price
     * @param WC_Order $order
     *
     * @return float|int
     */
    protected function round($price, $order)
    {
        $precision = 2;

        if (!$this->currency_has_decimals($order->get_currency())) {
            $precision = 0;
        }

        return round($price, $precision);
    }

    /**
     * Format prices
     *
     * @param float|int $price
     * @param WC_Order $order
     *
     * @return float|int
     */
    protected function number_format($price, $order)
    {
        $decimals = 2;

        if (!$this->currency_has_decimals($order->get_currency())) {
            $decimals = 0;
        }

        return number_format($price, $decimals, '.', '');
    }
}
