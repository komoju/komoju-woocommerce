<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 *Komoju Payment Gateway
 *
 * Provides access to Japanese local payment methods.
 *
 * @class       WC_Gateway_Komoju
 *
 * @extends     WC_Payment_Gateway
 *
 * @version     3.2.2
 *
 * @author      Komoju
 */
require_once dirname(__FILE__) . '/komoju-php/komoju-php/lib/komoju.php';

class WC_Gateway_Komoju extends WC_Payment_Gateway
{
    /** @var array Array of locales */
    public $locale;

    /** @var bool Whether or not logging is enabled */
    public static $log_enabled;

    /** @var WC_Logger Logger instance */
    public static $log;

    /* Fix for Deprecated: Creation of dynamic property */
    /** @var bool Debugging enabled */
    public $debug;

    /** @var string Invoice prefix */
    public $invoice_prefix;

    /** @var string Secret key */
    public $secretKey;

    /** @var string Webhook secret token */
    public $webhookSecretToken;

    /** @var KomojuApi */
    public $komoju_api;

    /** @var string Instructions */
    public $instructions;

    /** @var bool Use on hold */
    public $useOnHold;

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->id                   = $this->id ? $this->id : 'komoju';
        $this->method_title         = $this->method_title ? $this->method_title : __('Komoju', 'komoju-woocommerce');
        $this->method_description   = __("Includes all Komoju payment methods. Not recommended, since the name 'KOMOJU' is not recognized by most customers and refunds through WooCommerce are not supported.", 'komoju-woocommerce');
        $this->debug                = 'yes' === $this->get_option_compat('debug_log', 'debug');
        $this->invoice_prefix       = $this->get_option_compat('invoice_prefix', 'invoice_prefix');
        $this->secretKey            = $this->get_option_compat('secret_key', 'secretKey');
        $this->webhookSecretToken   = $this->get_option_compat('webhook_secret', 'webhookSecretToken');
        $this->komoju_api           = new KomojuApi($this->secretKey);
        self::$log_enabled          = $this->debug;

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title                = $this->get_option('title');
        $this->description          = $this->get_option('description');
        $this->instructions         = $this->get_option('instructions', $this->description);
        $this->useOnHold            = $this->get_option_compat('use_on_hold', 'useOnHold');

        // Filters
        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        if ($this->id === 'komoju') {
            include_once 'includes/class-wc-gateway-komoju-ipn-handler.php';
            new WC_Gateway_Komoju_IPN_Handler(
                $this,
                $this->webhookSecretToken,
                $this->secretKey,
                $this->invoice_prefix,
                $this->useOnHold
            );
            add_filter('woocommerce_admin_order_data_after_billing_address', [$this, 'show_komoju_link_on_order_page'], 10, 1);
        }
    }

    /*
     * This shows a link to komoju on order pages that were paid with this gateway.
     */
    public function show_komoju_link_on_order_page($order)
    {
        $payment_id = $order->get_meta('komoju_payment_id');
        if (!$payment_id) {
            return;
        }

        $url = $this->komoju_api->endpoint . '/admin/payments/' . $payment_id; ?>
        <p>
            <a href="<?php echo esc_attr($url); ?>">
                <?php echo __('View payment on KOMOJU', 'komoju-woocommerce'); ?>
            </a>
        </p>
<?php
    }

    /**
     * Logging method
     *
     * @param string $message
     */
    public static function log($message)
    {
        if (self::$log_enabled) {
            if (empty(self::$log)) {
                self::$log = new WC_Logger();
            }
            self::$log->add('komoju', $message);
        }
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = include 'includes/gateway-settings-komoju.php';
    }

    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     * @param string $payment_type
     *
     * @return array
     */
    public function process_payment($order_id, $payment_type = null)
    {
        $session = $this->create_session_for_order($order_id, $payment_type);

        return [
            'result'   => 'success',
            'redirect' => $session->session_url,
        ];
    }

    public function create_session_for_order($order_id, $payment_type = null)
    {
        include_once 'includes/class-wc-gateway-komoju-request.php';
        $order      = wc_get_order($order_id);
        $return_url = apply_filters(
            'komoju_session_return_url',
            WC()->api_request_url('WC_Gateway_Komoju'),
            $order_id,
            $payment_type
        );

        // construct line items
        $line_items = [];
        foreach ($order->get_items() as $item) {
            $image_parser = new DOMDocument();
            $image_parser->loadHTML($item->get_product()->get_image());
            $img = $image_parser->getElementsByTagName('img')->item(0);

            $line_items[] = [
                'description' => $item->get_name(),
                'quantity'    => $item->get_quantity(),
                'image'       => $img->attributes->getNamedItem('src')->nodeValue,
            ];
        }

        $name            = null;
        $email           = null;
        $billing_address = null;
        if ($order->has_billing_address()) {
            $billing_address = [
                'zipcode'         => $order->get_billing_postcode(),
                'street_address1' => $order->get_billing_address_1(),
                'street_address2' => trim(join(' ', [$order->get_billing_address_2(), $order->get_billing_company()])),
                'country'         => $order->get_billing_country(),
                'state'           => $order->get_billing_state(),
                'city'            => $order->get_billing_city(),
            ];

            $name  = trim(join(' ', [$order->get_billing_last_name(), $order->get_billing_first_name()]));
            $email = $order->get_billing_email();
        }

        $shipping_address = null;
        $ship_to_billing  = get_option('woocommerce_ship_to_destination') === 'billing';

        if ($order->has_shipping_address()) {
            $shipping_address = [
                'zipcode'         => $order->get_shipping_postcode(),
                'street_address1' => $order->get_shipping_address_1(),
                'street_address2' => trim(join(' ', [$order->get_shipping_address_2(), $order->get_shipping_company()])),
                'country'         => $order->get_shipping_country(),
                'state'           => $order->get_shipping_state(),
                'city'            => $order->get_shipping_city(),
            ];
        } elseif ($ship_to_billing) {
            $shipping_address = $billing_address;
        }

        // new session
        $currency       = $order->get_currency();
        $komoju_api     = $this->komoju_api;
        $session_params = [
            'amount'         => self::to_cents($order->get_total(), $currency),
            'currency'       => $currency,
            'return_url'     => $return_url,
            'default_locale' => self::get_locale_or_fallback(),
            'email'          => $email,
            'payment_data'   => [
                'external_order_num' => $this->external_order_num($order),
                'billing_address'    => $billing_address,
                'name'               => $name,
                'shipping_address'   => $shipping_address,
            ],
            'line_items' => $line_items,
            'metadata'   => [
                'woocommerce_order_id' => $order->get_order_number(),
            ],
        ];
        if ($payment_type) {
            $session_params['payment_types'] = [$payment_type];
        }
        $remove_nulls                   = function ($v) { return !is_null($v); };
        $session_params['payment_data'] = array_filter(
            $session_params['payment_data'],
            $remove_nulls
        );
        $session_params = array_filter($session_params, $remove_nulls);

        return $komoju_api->createSession($session_params);
    }

    /**
     * set KOMOJU side reference for order
     *
     * @param WC_Order $order
     */
    private function external_order_num($order)
    {
        $suffix = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 6);

        return $this->invoice_prefix . $order->get_order_number() . '-' . $suffix;
    }

    public static function get_locale_or_fallback()
    {
        $fallback_lang   = 'en';
        $supported_langs = ['ja', 'en', 'ko'];
        $page_locale     = get_locale();
        $lang            = is_string($page_locale) ? substr($page_locale, 0, 2) : $fallback_lang;

        if (in_array($lang, $supported_langs)) {
            return $lang;
        } else {
            return $fallback_lang;
        }
    }

    /**
     * We moved a lot of gateway settings into global options.
     *
     * We don't want to require people to go update their settings, so we use this function to
     * first check for the new setting, and then use the old one if not present.
     */
    public function get_option_compat($new_global_key, $old_local_key)
    {
        $new_option = get_option('komoju_woocommerce_' . $new_global_key);
        if ($new_option) {
            return $new_option;
        }

        return self::get_legacy_setting($old_local_key);
    }

    /**
     * Quick helper for grabbing legacy settings.
     *
     * We used to have everything saved on the gateway, but now global stuff like
     * API keys are stored separately in order to support multiple gateways.
     */
    public static function get_legacy_setting($name, $default_value = null)
    {
        $legacy_settings = get_option('woocommerce_komoju_settings');
        if ($legacy_settings === false) {
            return $default_value;
        }

        if (isset($legacy_settings[$name])) {
            return $legacy_settings[$name];
        }

        return $default_value;
    }

    /**
     * Default customer-facing title of this payment gateway.
     */
    protected function default_title()
    {
        return __('Komoju', 'komoju-woocommerce');
    }

    protected function default_description()
    {
        return sprintf(
            __('%s payments powered by KOMOJU', 'komoju-woocommerce'),
            $this->default_title()
        );
    }

    public static function to_cents($total, $currency = '')
    {
        if (!$currency) {
            $currency = get_woocommerce_currency();
        }

        if (in_array(strtolower($currency), self::no_decimal_currencies())) {
            return absint($total);
        } else {
            return absint(wc_format_decimal((float) $total * 100, wc_get_price_decimals())); // In cents.
        }
    }

    public static function no_decimal_currencies()
    {
        return [
            'bif', // Burundian Franc
            'clp', // Chilean Peso
            'djf', // Djiboutian Franc
            'gnf', // Guinean Franc
            'jpy', // Japanese Yen
            'kmf', // Comorian Franc
            'krw', // South Korean Won
            'mga', // Malagasy Ariary
            'pyg', // Paraguayan Guaraní
            'rwf', // Rwandan Franc
            'ugx', // Ugandan Shilling
            'vnd', // Vietnamese Đồng
            'vuv', // Vanuatu Vatu
            'xaf', // Central African Cfa Franc
            'xof', // West African Cfa Franc
            'xpf', // Cfp Franc
        ];
    }
}
