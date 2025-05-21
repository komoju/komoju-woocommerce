<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 *KOMOJU settings.
 *
 * @class       WC_Settings_Page_Komoju
 *
 * @extends     WC_Settings_Page
 *
 * @version     3.2.0
 *
 * @author      Komoju
 */
require_once dirname(__FILE__) . '/komoju-php/komoju-php/lib/komoju.php';

class WC_Settings_Page_Komoju extends WC_Settings_Page
{
    public function __construct()
    {
        $this->id    = 'komoju_settings';
        $this->label = __('Komoju', 'komoju-woocommerce');

        add_action(
            'woocommerce_admin_field_komoju_payment_types',
            [$this, 'output_payment_methods']
        );

        add_action(
            'woocommerce_admin_field_komoju_setup_button',
            [$this, 'output_setup_button']
        );

        add_action(
            'woocommerce_admin_field_komoju_endpoint',
            [$this, 'output_endpoint_field']
        );

        parent::__construct();
    }

    // Override from WC_Settings_Page
    public function get_sections()
    {
        $sections = [
            ''             => __('Payment methods', 'komoju-woocommerce'),
            'api_settings' => __('API settings', 'komoju-woocommerce'),
        ];

        return apply_filters('woocommerce_get_sections_' . $this->id, $sections);
    }

    // Override from WC_Settings_Page
    public function get_settings()
    {
        global $current_section;
        $settings = [];

        if ('' === $current_section) {
            $settings = apply_filters(
                'woocommerce_komoju_settings',
                include 'includes/account-settings-komoju.php'
            );
        } elseif ('api_settings' === $current_section) {
            $settings = apply_filters(
                'woocommerce_komoju_settings',
                include 'includes/api-settings-komoju.php'
            );
        }

        return apply_filters('woocommerce_get_settings_' . $this->id, $settings, $current_section);
    }

    // Override from WC_Settings_Page
    public function save()
    {
        $old_payment_types = get_option('komoju_woocommerce_payment_types');
        parent::save();
        $new_payment_types = get_option('komoju_woocommerce_payment_types');

        if ($old_payment_types != $new_payment_types) {
            $this->cache_payment_methods_from_komoju($old_payment_types, $new_payment_types);
        }
    }

    // Override from WC_Settings_Page
    public function output()
    {
        $just_connected = get_option('komoju_woocommerce_just_connected_merchant_name');
        if ($just_connected) {
            delete_option('komoju_woocommerce_just_connected_merchant_name');
            $this->output_connected_notice($just_connected);
        }
        parent::output();
    }

    // This shows a flash message (meant for the top of the KOMOJU settings page) for when
    // the user returns from the quick setup feature.
    public function output_connected_notice($merchant_name)
    {
        ?>
        <div id="message" class="updated inline">
            <p><strong><?php echo sprintf(__('Successfully connected to KOMOJU account %s.'), $merchant_name); ?></strong></p>
        </div>
        <?php
    }

    // Action handler for rendering settings with type = 'komoju_endpoint'
    public function output_endpoint_field($setting)
    {
        $value     = isset($setting['value']) ? $setting['value'] : $setting['default'];
        $untainted = $value === $setting['default']; ?>
<tr valign="top">
<th class="titledesc" scope="row">
    <label for="<?php echo esc_attr($setting['id']); ?>"><?php echo $setting['title']; ?></label>
</th>
<td class="forminp forminp-text komoju-endpoint-field komoju-endpoint-<?php echo esc_attr($setting['id']); ?>">
    <input id="<?php echo esc_attr($setting['id']); ?>"
           name="<?php echo esc_attr($setting['id']); ?>"
           value="<?php echo esc_attr($value); ?>"
           data-default="<?php echo esc_attr($setting['default']); ?>"
           type="text"
           <?php if ($untainted) {
               echo 'disabled';
           } ?>>
    <p class="description">
        <?php echo __("Only modify this if you know what you're doing.", 'komoju-woocommerce'); ?>
    </p>
    <div>
        <?php if ($untainted) { ?>
            <button
                type="button"
                class="komoju-endpoint-edit"
                data-target="<?php echo esc_attr($setting['id']); ?>"
                onclick="komoju_woocommerce_enable_endpoint_field(event)">
                <?php echo __('Edit', 'komoju-woocommerce'); ?>
            </button>
        <?php } ?>

        <button
            type="button"
            class="komoju-endpoint-reset"
            data-target="<?php echo esc_attr($setting['id']); ?>"
            onclick="komoju_woocommerce_reset_endpoint_field(event)">
            <?php echo __('Reset', 'komoju-woocommerce'); ?>
        </button>
    </div>

    <script>
        function komoju_woocommerce_enable_endpoint_field(event) {
            const button = event.target;
            const input = document.getElementById(button.dataset.target);
            input.disabled = false;
            event.target.remove();
        }
        function komoju_woocommerce_reset_endpoint_field(event) {
            const button = event.target;
            const input = document.getElementById(button.dataset.target);
            input.value = input.dataset.default;
        }
    </script>

    <style>
        .komoju-endpoint-field {
            display: flex;
            flex-flow: column wrap;
            align-items: flex-start;
            gap: 4px;
        }
        .komoju-endpoint-field button {
            min-width: 80px;
        }
        .komoju-endpoint-field p.description {
            margin: 0;
        }
    </style>
</td>
</tr>
<?php
    }

    // Action handler for rendering settings with type = 'komoju_setup_button'
    public function output_setup_button($setting)
    {
        $nonce = wp_generate_uuid4();
        update_option('komoju_woocommerce_nonce', $nonce);

        $already_connected = get_option('komoju_woocommerce_secret_key') ? true : false;

        $setup_url = KomojuApi::endpoint() . '/plugin/auth?' .
            'post_url=' . rawurlencode($this->contracted_url_for_webhooks()) . '&' .
            'webhook_url=' . rawurlencode($this->contracted_url_for_webhooks()) . '&' .
            'nonce=' . rawurlencode($nonce); ?>
        <tr>
            <th class="titledesc" scope="row">
                <label><?php echo $setting['title']; ?></label>
            </th>
            <td class="forminp forminp-text komoju-setup-button" style="height: 60px">
                <a href="<?php echo esc_attr($setup_url); ?>"
                   class='komoju-setup <?php echo $already_connected ? 'connected' : ''; ?>'>
                    <?php
                        if ($already_connected) {
                            echo __('Reconnect with KOMOJU', 'komoju-woocommerce');
                        } else {
                            echo __('Sign into KOMOJU', 'komoju-woocommerce');
                        } ?>
                </a>

                <style>
                a.komoju-setup {
                    text-decoration: none;
                    background-color: #1880DE;
                    font-size: 18px;
                    color: white;
                    border: none;
                    border-radius: 8px;
                    padding: 26px;
                    margin-bottom: 12px;
                }
                a.komoju-setup:hover {
                    background-color: #3590E1;
                }

                a.komoju-setup.connected {
                    background-color: white;
                    color: #172E44;
                    border: 2px solid #C1CDD8;
                }
                a.komoju-setup.connected:hover {
                    background-color: #F0F8FF;
                }
                </style>
            </td>
        </tr>
        <?php
    }

    // Action handler for rendering settings with type = 'komoju_payment_types'
    public function output_payment_methods($setting)
    {
        $value               = is_array($setting['value']) ? $setting['value'] : [];
        $locale              = WC_Gateway_Komoju::get_locale_or_fallback();
        $all_payment_methods = $this->fetch_all_payment_methods();
        if ($all_payment_methods === null) {
            ?>
                <tr style="color: darkred"><td></td><td>
                    <?php
                        $secret_key = $this->secret_key();
            if ($secret_key && $secret_key !== '') {
                echo __('Unable to reach KOMOJU. Is your secret key correct?', 'komoju-woocommerce');
            } else {
                echo __('Once signed into KOMOJU, you can select payment methods to use as WooCommerce gateways.', 'komoju-woocommerce');
            } ?>
                </td></tr>
            <?php
            return;
        }

        // Show each payment method as a checkbox with an icon?>
        <tr>
        <th class="titledesc" scope="row">
            <label><?php echo $setting['title']; ?></label>
        </th>
        <td class="forminp forminp-text komoju-payment-methods"
            style="display: flex; flex-flow: row wrap; max-width: 800px; margin-bottom: 12px">
            <?php
            foreach ($all_payment_methods as $slug => $payment_method) {
                ?>
                <label style="display: flex; align-items: center; gap: 5px; margin-bottom: 5px; width: 200px">
                <input
                  type="checkbox"
                  name="<?php echo esc_attr($setting['id']); ?>[]"
                  value="<?php echo esc_attr($slug); ?>"
                  <?php if (in_array($slug, $value)) {
                      echo 'checked';
                  } ?>
                >
                <img
                  width="38"
                  height="24"
                  src="https://komoju.com/payment_methods/<?php echo esc_attr($slug); ?>.svg">
                <?php echo $payment_method['name_' . $locale]; ?>
                </label>
                <?php
            } ?>
        </td>
        </tr>
        <?php
    }

    // Basically, the 'komoju_woocommerce_payment_types' option is just an array of slugs,
    // and 'komoju_woocommerce_payment_methods' holds the actual payment method objects
    // we get from the KOMOJU API.
    //
    // This action handler updates the 'komoju_woocommerce_payment_types' option
    // to match the 'komoju_woocommerce_payment_types' option.
    public function cache_payment_methods_from_komoju($old_payment_types, $payment_types)
    {
        $all_payment_methods = $this->fetch_all_payment_methods();
        if ($all_payment_methods === null) {
            return;
        }

        // CPF-264 Force remove linepay settings
        delete_option('woocommerce_komoju_linepay_settings');

        // Clear gateway settings from removed entries
        if ($old_payment_types) {
            $to_remove = array_diff($old_payment_types, $payment_types);
            foreach ($to_remove as $slug) {
                delete_option('woocommerce_komoju_' . $slug . '_settings');
            }
        }

        // Populate komoju_woocommerce_payment_methods option with fresh values from KOMOJU
        $payment_methods = [];
        foreach ($payment_types as $slug) {
            $payment_methods[$slug] = $all_payment_methods[$slug];
        }

        update_option('komoju_woocommerce_payment_methods', $payment_methods, true);
    }

    private function url_for_webhooks()
    {
        // In dev the relative plugin URL will remove the host name, but it
        // will appear in production instances
        return WC()->api_request_url('WC_Gateway_Komoju');
    }

    // There is a quirk in our production setup where all HTTP requests containing the
    // word "localhost" are rejected. We still want local setup to work, so we use
    // "local" as an alias for localhost.
    private function contracted_url_for_webhooks()
    {
        $url = $this->url_for_webhooks();
        $url = str_ireplace('http://localhost:', 'http://local:', $url);
        $url = str_ireplace('http://127.0.0.1:', 'http://local:', $url);

        return $url;
    }

    // Returns a list of payment methods from KOMOJU.
    //
    // @return array that looks something like this:
    // [
    //     "konbini" => [
    //         "currency"           => "JPY",
    //         "name_en"            => "Konbini",
    //         "name_ja"            => "コンビニ",
    //         "name_ko"            => "편의점",
    //         "payment_method_fee" => 190,
    //         "type_slug"          => "konbini"
    //     ],
    //     ...
    // ]
    private function fetch_all_payment_methods()
    {
        $api = new KomojuApi($this->secret_key());

        if (!$api->secretKey || strlen($api->secretKey) === 0) {
            return null;
        }

        try {
            $all_payment_methods = $api->paymentMethods();
            $methods_by_slug     = [];
            $wc_currency         = get_woocommerce_currency();

            foreach ($all_payment_methods as $payment_method) {
                $slug        = $payment_method['type_slug'];
                $pm_currency = $payment_method['currency'];

                // CPF-264 Remove linepay from the list of payment methods
                if ($slug === 'linepay') {
                    continue;
                }

                // If $slug is not set, register
                if (!isset($methods_by_slug[$slug])) {
                    $methods_by_slug[$slug] = $payment_method;
                } else {
                    // If $slug is already registered and
                    // the payment currency matches the WooCommerce currency then override it
                    if ($pm_currency === $wc_currency) {
                        $methods_by_slug[$slug] = $payment_method;
                    }
                }
            }

            return $methods_by_slug;
        } catch (KomojuExceptionBadServer $ex) {
            return null;
        }
    }

    private function secret_key()
    {
        $global_option = get_option('komoju_woocommerce_secret_key');
        if (!$global_option) {
            // This is for backwards compatibility. We used to have all settings saved under
            // a single payment gateway called "Komoju". We've sinced moved to having this
            // global settings page, but want to continue supporting old setups.
            return WC_Gateway_Komoju::get_legacy_setting('secretKey');
        }

        return $global_option;
    }
}
