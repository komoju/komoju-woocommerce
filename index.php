<?php

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

/*
* Plugin Name: KOMOJU Payments
* Plugin URI: https://github.com/komoju/komoju-woocommerce
* Description: Extends WooCommerce with KOMOJU gateway.
* Author: KOMOJU
* Author URI: https://komoju.com
* Version: 3.3.2
* Requires at least: 6.0
* Requires PHP: 7.4
* Requires Plugins: woocommerce
* Text Domain: komoju-japanese-payments
* Domain Path: /languages
* License: GPL-2.0-or-later
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
* WC requires at least: 6.0
* WC tested up to: 10.8.1
*/

// Keep in sync with the "Version:" header above; used to cache-bust assets.
if (!defined('KOMOJU_WC_VERSION')) {
    define('KOMOJU_WC_VERSION', '3.3.2');
}

add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'woocommerce_komoju_plugin_action_links');

function woocommerce_komoju_plugin_action_links($links)
{
    $settings_url  = admin_url('admin.php?page=wc-settings&tab=komoju_settings');
    $settings_link = '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'komoju-japanese-payments') . '</a>';
    array_unshift($links, $settings_link);

    return $links;
}

add_action('init', 'woocommerce_komoju_load_textdomain');

function woocommerce_komoju_load_textdomain()
{
    $domain = 'komoju-japanese-payments';
    $locale = determine_locale();

    // Load the plugin's bundled translations directly. This ensures new strings
    // not yet available on translate.wordpress.org are always translated.
    $mofile = dirname(__FILE__) . "/languages/{$domain}-{$locale}.mo";
    if (file_exists($mofile)) {
        load_textdomain($domain, $mofile);
    }
}

// Prefer the bundled translations over the translate.wordpress.org language
// pack, which lacks newly added strings. Also covers just-in-time loading,
// which can run before the init hook above (e.g. during gateway construction).
add_filter('load_textdomain_mofile', 'woocommerce_komoju_override_mofile', 10, 2);

function woocommerce_komoju_override_mofile($mofile, $domain)
{
    if ($domain !== 'komoju-japanese-payments') {
        return $mofile;
    }

    $locale         = determine_locale();
    $bundled_mofile = dirname(__FILE__) . "/languages/{$domain}-{$locale}.mo";
    if (file_exists($bundled_mofile)) {
        return $bundled_mofile;
    }

    return $mofile;
}

add_action('plugins_loaded', 'woocommerce_komoju_init', 0);

function woocommerce_komoju_init()
{
    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_komoju_gateway($methods)
    {
        require_once 'class-wc-gateway-komoju.php';
        require_once 'includes/class-wc-gateway-komoju-single-slug.php';
        $methods[] = new WC_Gateway_Komoju();

        $komoju_payment_methods = get_option('komoju_woocommerce_payment_methods');
        if (gettype($komoju_payment_methods) == 'array') {
            foreach ($komoju_payment_methods as $payment_method) {
                // CPF-264 Remove linepay from the list of payment methods
                if (isset($payment_method['type_slug']) && $payment_method['type_slug'] === 'linepay') {
                    continue;
                }
                $methods[] = new WC_Gateway_Komoju_Single_Slug($payment_method);
            }
        }

        return $methods;
    }

    /**
     * Add the KOMOJU settings page to WooCommerce
     **/
    function woocommerce_add_komoju_settings_page($settings)
    {
        require_once 'class-wc-gateway-komoju.php';
        require_once 'class-wc-settings-page-komoju.php';
        $settings[] = new WC_Settings_Page_Komoju();

        return $settings;
    }

    /**
     * Add the KOMOJU Fields JS
     **/
    function woocommerce_komoju_load_scripts()
    {
        if (!is_checkout()) {
            return;
        }

        $komoju_fields_js = get_option('komoju_woocommerce_fields_url');
        if (!$komoju_fields_js) {
            $komoju_fields_js = 'https://multipay.komoju.com/fields.js';
        }

        wp_enqueue_script('komoju-fields', $komoju_fields_js, [], null, true); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
        wp_script_add_data('komoju-fields', 'type', 'module');

        wp_enqueue_style(
            'komoju-checkout',
            plugins_url('includes/css/checkout-komoju.css', __FILE__),
            [],
            KOMOJU_WC_VERSION
        );
    }

    /**
     * Enqueue admin styles and scripts for the KOMOJU settings page.
     **/
    function woocommerce_komoju_load_admin_assets($hook)
    {
        if ($hook !== 'woocommerce_page_wc-settings') {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET['tab']) || $_GET['tab'] !== 'komoju_settings') {
            return;
        }

        wp_enqueue_style(
            'komoju-admin',
            plugins_url('includes/css/admin-komoju.css', __FILE__),
            [],
            KOMOJU_WC_VERSION
        );

        wp_enqueue_script(
            'komoju-admin',
            plugins_url('includes/js/admin-komoju.js', __FILE__),
            [],
            KOMOJU_WC_VERSION,
            true
        );
    }

    function woocommerce_komoju_load_script_as_module($tag, $handle, $src)
    {
        if ($handle !== 'komoju-fields') {
            return $tag;
        }

        return '<script type="module" src="' . esc_url($src) . '"></script>' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
    }

    function woocommerce_komoju_handle_http_request()
    {
        // Force WC to load our gateway, causing WC_Gateway_Komoju_IPN_Handler to get instantiated.
        WC()->payment_gateways()->payment_gateways();

        // When WC_Gateway_Komoju_IPN_Handler is instantiated, this filter should be registered.
        $handled = apply_filters('komoju_japanese_payments_invoke_ipn_handler', false);

        // Catch unexpected case where the filter is NOT registered
        if (!$handled) {
            header('X-Komoju-Error: komoju gateway not loaded');
            wp_die(
                'gateway (and thus IPN handler) not loaded',
                'KOMOJU WooCommerce plugin',
                ['status' => 500]
            );
        }
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_komoju_gateway');
    add_filter('woocommerce_get_settings_pages', 'woocommerce_add_komoju_settings_page');
    add_action('woocommerce_api_wc_gateway_komoju', 'woocommerce_komoju_handle_http_request');

    add_action('wp_enqueue_scripts', 'woocommerce_komoju_load_scripts');
    add_action('admin_enqueue_scripts', 'woocommerce_komoju_load_admin_assets');
    add_filter('script_loader_tag', 'woocommerce_komoju_load_script_as_module', 10, 3);

    add_action('plugins_loaded', 'woocommerce_komoju_blocks');

    function woocommerce_komoju_blocks()
    {
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        require_once 'includes/class-wc-gateway-komoju-block.php';
    }

    add_action('woocommerce_blocks_loaded', 'komoju_japanese_payments_register_block_types');

    function komoju_japanese_payments_register_block_types()
    {
        if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            return;
        }

        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (PaymentMethodRegistry $payment_method_registry) {
                require_once 'class-wc-gateway-komoju.php';
                require_once 'includes/class-wc-gateway-komoju-single-slug.php';

                $komoju_payment_methods = get_option('komoju_woocommerce_payment_methods');
                if (gettype($komoju_payment_methods) != 'array') {
                    return;
                }

                foreach ($komoju_payment_methods as $payment_method) {
                    // CPF-264 Remove linepay from the list of payment methods
                    if (isset($payment_method['type_slug']) && $payment_method['type_slug'] === 'linepay') {
                        continue;
                    }

                    $gateway = new WC_Gateway_Komoju_Single_Slug($payment_method);

                    if ($gateway->enabled == 'yes') {
                        $payment_method_registry->register(new WC_Gateway_Komoju_Blocks($gateway));
                    }
                }
            }
        );
    }
}
