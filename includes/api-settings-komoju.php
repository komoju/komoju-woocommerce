<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Settings for the whole Komoju plugin
 */

return [
    // API credentials
    [
        'title'       => __('API credentials', 'komoju-japanese-payments'),
        'type'        => 'title',
        'id'          => 'komoju-api-credentials',
        /* translators: %s: webhook URL */
        /* translators: %s: link to the KOMOJU settings page */
        'desc'        => sprintf(__('Unless you need to enter keys manually, we recommend connecting via the button on the %s.', 'komoju-japanese-payments'), '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=komoju_settings')) . '">' . __('KOMOJU settings page', 'komoju-japanese-payments') . '</a>') . '<br>' . sprintf(__('Default url for the webhook is %s. Use this if you\'re not sure what it should be.', 'komoju-japanese-payments'), $this->url_for_webhooks()),
    ],
    [
        'id'                => 'komoju_woocommerce_secret_key',
        'placeholder'       => 'sk_live_000000000000000000000000',
        'title'             => __('Secret Key from KOMOJU', 'komoju-japanese-payments'),
        'type'              => 'komoju_secret',
        'default'           => WC_Gateway_Komoju::get_legacy_setting('secretKey'),
        'custom_attributes' => [
            'pattern' => '^sk_(live|test)_[A-Za-z0-9]+$',
        ],
    ],
    [
        'id'                => 'komoju_woocommerce_publishable_key',
        'placeholder'       => 'pk_live_000000000000000000000000',
        'title'             => __('Publishable Key from KOMOJU', 'komoju-japanese-payments'),
        'type'              => 'komoju_secret',
        'default'           => WC_Gateway_Komoju::get_legacy_setting('publishableKey'),
        'custom_attributes' => [
            'pattern' => '^pk_(live|test)_[A-Za-z0-9]+$',
        ],
    ],
    [
        'id'                => 'komoju_woocommerce_webhook_secret',
        'placeholder'       => __('Please enter your KOMOJU Webhook Secret Token', 'komoju-japanese-payments'),
        'title'             => __('Webhook Secret Token', 'komoju-japanese-payments'),
        'type'              => 'komoju_secret',
        'default'           => WC_Gateway_Komoju::get_legacy_setting('webhookSecretToken'),
    ],
    [
        'id'       => 'komoju-api-credentials-end',
        'type'     => 'sectionend',
    ],

    // Payment behavior
    [
        'title'       => __('Payment behavior', 'komoju-japanese-payments'),
        'type'        => 'title',
        'id'          => 'komoju-payment-behavior',
        'desc'        => __('Control how orders and payments are processed in your store.', 'komoju-japanese-payments'),
    ],
    [
        'id'          => 'komoju_woocommerce_invoice_prefix',
        'placeholder' => __('Please enter a prefix for your invoice numbers. If you use your KOMOJU account for multiple stores ensure this prefix is unique.', 'komoju-japanese-payments'),
        'title'       => __('Invoice Prefix', 'komoju-japanese-payments'),
        'type'        => 'text',
        'default'     => WC_Gateway_Komoju::get_legacy_setting('invoice_prefix', 'WC-'),
        'desc_tip'    => true,
    ],
    [
        'id'          => 'komoju_woocommerce_use_on_hold',
        'type'        => 'checkbox',
        'title'       => __('Use on-hold status for pending payments', 'komoju-japanese-payments'),
        'desc'        => __("Use 'on-hold' status for payments that are authorized on KOMOJU but awaiting capture. If not selected, 'payment pending' status will be used.", 'komoju-japanese-payments'),
        'default'     => WC_Gateway_Komoju::get_legacy_setting('useOnHold', 'no'),
        'desc_tip'    => true,
    ],
    [
        'id'           => 'komoju_woocommerce_ipn_async',
        'type'         => 'checkbox',
        'title'        => __('Process IPNs Asynchronously', 'komoju-japanese-payments'),
        'desc'         => __('When true, IPNs will return immediately, and order completion will be processed in the background.', 'komoju-japanese-payments'),
        'default'      => 'no',
    ],
    [
        'id'       => 'komoju-payment-behavior-end',
        'type'     => 'sectionend',
    ],

    // Advanced (developer settings)
    [
        'title'       => __('Advanced', 'komoju-japanese-payments'),
        'type'        => 'title',
        'id'          => 'komoju-advanced-settings',
        'desc'        => __('Developer settings. Leave these as their defaults unless KOMOJU support has asked you to change them.', 'komoju-japanese-payments'),
    ],
    [
        'id'          => 'komoju_woocommerce_debug_log',
        'desc'        => __('Log KOMOJU events. View logs under WooCommerce &gt; Status &gt; Logs (source: komoju).', 'komoju-japanese-payments'),
        'desc_tip'    => true,
        'title'       => __('Debug Log', 'komoju-japanese-payments'),
        'type'        => 'checkbox',
        'label'       => __('Enable logging', 'komoju-japanese-payments'),
        'default'     => WC_Gateway_Komoju::get_legacy_setting('debug', 'no'),
    ],
    [
        'id'          => 'komoju_woocommerce_api_endpoint',
        'title'       => __('KOMOJU Endpoint', 'komoju-japanese-payments'),
        'type'        => 'komoju_endpoint',
        'default'     => KomojuApi::defaultEndpoint(),
    ],
    [
        'id'          => 'komoju_woocommerce_fields_url',
        'title'       => __('KOMOJU Fields script URL', 'komoju-japanese-payments'),
        'type'        => 'komoju_endpoint',
        'default'     => 'https://multipay.komoju.com/fields.js',
    ],
    [
        'id'          => 'komoju_woocommerce_waf_staging_token',
        'desc'        => __("Only modify this if you know what you're doing.", 'komoju-japanese-payments'),
        'title'       => __('Staging token', 'komoju-japanese-payments'),
        'type'        => 'text',
        'default'     => '',
    ],
    [
        'id'       => 'komoju-advanced-settings-end',
        'type'     => 'sectionend',
    ],
];
