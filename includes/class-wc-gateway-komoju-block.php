<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Gateway_Komoju_Blocks extends AbstractPaymentMethodType
{
    private $gateway;
    protected $name;
    protected $settings;

    public function __construct(WC_Gateway_Komoju_Single_Slug $gateway)
    {
        $this->gateway = $gateway;
        $this->name    = $gateway->id;
    }

    public function initialize()
    {
        $this->settings = $this->gateway->payment_method['settings'] ?? [];
        $this->settings = get_option('woocommerce_test_komoju_settings', []);
    }

    public function is_active()
    {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'komoju-payment-blocks-integration',
            plugin_dir_url(__FILE__) . './js/komoju-checkout-blocks.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
            ],
            null,
            true
        );

        return ['komoju-payment-blocks-integration'];
    }

    public function get_payment_method_data()
    {
        if (!is_admin() && (is_null(WC()->cart) || WC()->cart->is_empty())) {
            return;
        }

        // We lazily fetch one session to be shared by all payment methods with dynamic fields.
        static $checkout_session;
        if (is_null($checkout_session)) {
            $checkout_session = $this->gateway->create_session_for_fields();
        }

        return [
            'id'          => $this->name,
            'title'       => $this->gateway->title,
            'description' => $this->gateway->description,
            'supports'    => array_filter($this->gateway->supports, [$this->gateway, 'supports']),
            // 'paymentFields' => $this->gateway->payment_fields(),
            'icon'           => $this->gateway->icon,
            'tokenName'      => 'komoju_payment_token',
            'komojuApi'      => KomojuApi::endpoint(),
            'publishableKey' => $this->gateway->publishableKey,
            'session'        => wp_json_encode($checkout_session),
            'paymentType'    => $this->gateway->payment_method['type_slug'],
            'locale'         => $this->gateway->locale,
            'inlineFields'   => $this->gateway->has_fields,
        ];
    }
}
