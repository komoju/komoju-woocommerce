<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Settings for the whole Komoju plugin
 */

return [
    // ---------------------------------------------------------------------
    // API credentials
    // ---------------------------------------------------------------------
    [
        'title'       => __('API credentials', 'komoju-japanese-payments'),
        'type'        => 'title',
        'id'          => 'komoju-api-credentials',
        /* translators: %s: webhook URL */
        'desc'        => sprintf(__('Default url for the webhook is %s. Use this if you\'re not sure what it should be.', 'komoju-japanese-payments'), $this->url_for_webhooks()),
    ],
    [
        'id'                => 'komoju_woocommerce_secret_key',
        'placeholder'       => 'sk_live_000000000000000000000000',
        'title'             => __('Secret Key from Komoju', 'komoju-japanese-payments'),
        'type'              => 'password',
        'default'           => WC_Gateway_Komoju::get_legacy_setting('secretKey'),
        'desc_tip'          => true,
        'custom_attributes' => [
            'autocomplete' => 'off',
            'spellcheck'   => 'false',
            'pattern'      => '^sk_(live|test)_[A-Za-z0-9]+$',
        ],
    ],
    [
        'id'                => 'komoju_woocommerce_publishable_key',
        'placeholder'       => 'pk_live_000000000000000000000000',
        'title'             => __('Publishable Key from Komoju', 'komoju-japanese-payments'),
        'type'              => 'password',
        'default'           => WC_Gateway_Komoju::get_legacy_setting('publishableKey'),
        'desc_tip'          => true,
        'custom_attributes' => [
            'autocomplete' => 'off',
            'spellcheck'   => 'false',
            'pattern'      => '^pk_(live|test)_[A-Za-z0-9]+$',
        ],
    ],
    [
        'id'                => 'komoju_woocommerce_webhook_secret',
        'placeholder'       => __('Please enter your Komoju Webhook Secret Token', 'komoju-japanese-payments'),
        'title'             => __('Webhook Secret Token', 'komoju-japanese-payments'),
        'type'              => 'password',
        'default'           => WC_Gateway_Komoju::get_legacy_setting('webhookSecretToken'),
        'desc_tip'          => true,
        'custom_attributes' => [
            'autocomplete' => 'off',
            'spellcheck'   => 'false',
        ],
    ],
    [
        'id'       => 'komoju-api-credentials-end',
        'type'     => 'sectionend',
    ],

    // ---------------------------------------------------------------------
    // Payment behavior
    // ---------------------------------------------------------------------
    [
        'title'       => __('Payment behavior', 'komoju-japanese-payments'),
        'type'        => 'title',
        'id'          => 'komoju-payment-behavior',
        'desc'        => __('Control how orders and payments are processed in your store.', 'komoju-japanese-payments'),
    ],
    [
        'id'          => 'komoju_woocommerce_invoice_prefix',
        'placeholder' => __('Please enter a prefix for your invoice numbers. If you use your Komoju account for multiple stores ensure this prefix is unique.', 'komoju-japanese-payments'),
        'title'       => __('Invoice Prefix', 'komoju-japanese-payments'),
        'type'        => 'text',
        'default'     => WC_Gateway_Komoju::get_legacy_setting('invoice_prefix', 'WC-'),
        'desc_tip'    => true,
    ],
    [
        'id'          => 'komoju_woocommerce_use_on_hold',
        'type'        => 'checkbox',
        'title'       => __('Use on-hold status for pending payments', 'komoju-japanese-payments'),
        'desc'        => __("Use 'on-hold' status for payments that are authorized on komoju but awaiting capture. If not selected, 'payment pending' status will be used.", 'komoju-japanese-payments'),
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

    // ---------------------------------------------------------------------
    // Advanced (developer settings - infrequently changed)
    // ---------------------------------------------------------------------
    [
        'title'       => __('Advanced', 'komoju-japanese-payments'),
        'type'        => 'title',
        'id'          => 'komoju-advanced-settings',
        'desc'        => __('Developer settings. Leave these as their defaults unless KOMOJU support has asked you to change them.', 'komoju-japanese-payments'),
    ],
    [
        'id'          => 'komoju_woocommerce_debug_log',
        /* translators: %s: log file path */
        'desc'        => sprintf(__('Log Komoju events inside <code>%s</code>', 'komoju-japanese-payments'), wc_get_log_file_path('komoju')),
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
        'desc'        => __('Usually you want this to be empty.', 'komoju-japanese-payments'),
        'title'       => __('Staging token', 'komoju-japanese-payments'),
        'type'        => 'text',
        'default'     => '',
    ],
    [
        'id'       => 'komoju-advanced-settings-end',
        'type'     => 'sectionend',
    ],
];
