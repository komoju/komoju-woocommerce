<?php

/**
 * WooCommerce Komoju Payment Gateway
 * Uninstall - removes all options from DB when user deletes the plugin via WordPress backend.
 *
 * @since 1.0
 *
 **/
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('woocommerce_komoju_settings');
delete_option('komoju_woocommerce_secret_key');
delete_option('komoju_woocommerce_webhook_secret');
delete_option('komoju_woocommerce_invoice_prefix');
delete_option('komoju_woocommerce_debug_log');

$komoju_payment_types = get_option('komoju_woocommerce_payment_types');
if (gettype($komoju_payment_types) == 'array') {
    foreach ($komoju_payment_types as $komoju_slug) {
        delete_option('woocommerce_komoju_' . $komoju_slug . '_settings');
    }
}

delete_option('komoju_woocommerce_payment_types');
delete_option('komoju_woocommerce_payment_methods');
