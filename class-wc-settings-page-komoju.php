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
 * @version     3.3.2
 *
 * @author      Komoju
 */
require_once dirname(__FILE__) . '/komoju-php/komoju-php/lib/komoju.php';

class WC_Settings_Page_Komoju extends WC_Settings_Page
{
    public function __construct()
    {
        $this->id    = 'komoju_settings';
        $this->label = __('KOMOJU', 'komoju-japanese-payments');

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

        add_action(
            'woocommerce_admin_field_komoju_secret',
            [$this, 'output_secret_field']
        );

        add_filter(
            'woocommerce_admin_settings_sanitize_option_komoju_woocommerce_secret_key',
            [$this, 'validate_secret_key'],
            10,
            3
        );

        add_filter(
            'woocommerce_admin_settings_sanitize_option_komoju_woocommerce_publishable_key',
            [$this, 'validate_publishable_key'],
            10,
            3
        );

        add_filter(
            'woocommerce_admin_settings_sanitize_option_komoju_woocommerce_webhook_secret',
            [$this, 'validate_webhook_secret'],
            10,
            3
        );

        parent::__construct();
    }

    // Override from WC_Settings_Page
    public function get_sections()
    {
        $sections = [
            ''             => __('Payment methods', 'komoju-japanese-payments'),
            'api_settings' => __('API settings', 'komoju-japanese-payments'),
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

        if (WC_Gateway_Komoju::komoju_is_test_mode()) {
            $this->output_test_mode_notice();
        }

        parent::output();
    }

    /**
     * Display a prominent notice when KOMOJU is in test mode.
     */
    public function output_test_mode_notice()
    {
        ?>
        <div class="notice notice-warning komoju-test-mode-notice">
            <p>
                <strong><?php echo esc_html__('Test Mode Active', 'komoju-japanese-payments'); ?></strong>
                —
                <?php echo esc_html__('Your store is using KOMOJU test keys. No real charges will be processed.', 'komoju-japanese-payments'); ?>
            </p>
        </div>
        <?php
    }

    // This shows a flash message (meant for the top of the KOMOJU settings page) for when
    // the user returns from the quick setup feature.
    public function output_connected_notice($merchant_name)
    {
        ?>
        <div class="notice notice-success is-dismissible">
            <p><strong><?php
                /* translators: %s: merchant account name */
                echo sprintf(esc_html__('Successfully connected to KOMOJU account %s.', 'komoju-japanese-payments'), esc_html($merchant_name));
        ?></strong></p>
        </div>
        <?php
    }

    // Blank keeps the stored key; otherwise require an sk_live_/sk_test_ prefix.
    public function validate_secret_key($value, $option, $raw_value)
    {
        $value = trim($value);
        if ($value === '') {
            return get_option('komoju_woocommerce_secret_key');
        }
        if (!preg_match('/^sk_(live|test)_[A-Za-z0-9]+$/', $value)) {
            WC_Admin_Settings::add_error(
                __('Invalid KOMOJU secret key. It should start with sk_live_ or sk_test_.', 'komoju-japanese-payments')
            );

            return get_option('komoju_woocommerce_secret_key');
        }

        return $value;
    }

    // Blank keeps the stored key; otherwise require a pk_live_/pk_test_ prefix.
    public function validate_publishable_key($value, $option, $raw_value)
    {
        $value = trim($value);
        if ($value === '') {
            return get_option('komoju_woocommerce_publishable_key');
        }
        if (!preg_match('/^pk_(live|test)_[A-Za-z0-9]+$/', $value)) {
            WC_Admin_Settings::add_error(
                __('Invalid KOMOJU publishable key. It should start with pk_live_ or pk_test_.', 'komoju-japanese-payments')
            );

            return get_option('komoju_woocommerce_publishable_key');
        }

        return $value;
    }

    // Blank keeps the stored webhook secret.
    public function validate_webhook_secret($value, $option, $raw_value)
    {
        $value = trim($value);
        if ($value === '') {
            return get_option('komoju_woocommerce_webhook_secret');
        }

        return $value;
    }

    // Write-only password field for type = 'komoju_secret'. The stored value is
    // never echoed back; a saved key is shown only as a masked hint placeholder.
    public function output_secret_field($setting)
    {
        $stored_value      = get_option($setting['id']);
        $has_value         = (bool) $stored_value;
        $custom_attributes = '';
        if (!empty($setting['custom_attributes']) && is_array($setting['custom_attributes'])) {
            foreach ($setting['custom_attributes'] as $attribute => $attribute_value) {
                $custom_attributes .= ' ' . esc_attr($attribute) . '="' . esc_attr($attribute_value) . '"';
            }
        }

        if ($has_value) {
            $placeholder = $this->masked_secret_hint($stored_value);
        } else {
            $placeholder = isset($setting['placeholder']) ? $setting['placeholder'] : '';
        }

        $description = isset($setting['desc']) ? $setting['desc'] : '';
        ?>
<tr valign="top">
<th class="titledesc" scope="row">
    <label for="<?php echo esc_attr($setting['id']); ?>"><?php echo esc_html($setting['title']); ?></label>
</th>
<td class="forminp forminp-password">
    <input id="<?php echo esc_attr($setting['id']); ?>"
           name="<?php echo esc_attr($setting['id']); ?>"
           type="password"
           value=""
           placeholder="<?php echo esc_attr($placeholder); ?>"
           autocomplete="off"
           spellcheck="false"<?php echo $custom_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?>>
    <?php if ($description !== '') { ?>
        <p class="description"><?php echo esc_html($description); ?></p>
    <?php } ?>
</td>
</tr>
<?php
    }

    // Masked hint for a saved secret: the non-sensitive sk_/pk_ prefix + dots,
    // or dots only for other values.
    private function masked_secret_hint($value)
    {
        $dots = str_repeat('•', 16);

        if (preg_match('/^((?:sk|pk)_(?:live|test)_)/', $value, $matches)) {
            return $matches[1] . $dots;
        }

        return $dots;
    }

    // Action handler for rendering settings with type = 'komoju_endpoint'
    public function output_endpoint_field($setting)
    {
        $value     = isset($setting['value']) ? $setting['value'] : $setting['default'];
        $untainted = $value === $setting['default']; ?>
<tr valign="top">
<th class="titledesc" scope="row">
    <label for="<?php echo esc_attr($setting['id']); ?>"><?php echo esc_html($setting['title']); ?></label>
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
        <?php echo esc_html__("Only modify this if you know what you're doing.", 'komoju-japanese-payments'); ?>
    </p>
    <div class="komoju-endpoint-buttons">
        <?php if ($untainted) { ?>
            <button
                type="button"
                class="button button-secondary komoju-endpoint-edit"
                data-target="<?php echo esc_attr($setting['id']); ?>">
                <?php echo esc_html__('Edit', 'komoju-japanese-payments'); ?>
            </button>
        <?php } ?>

        <button
            type="button"
            class="button button-link-delete komoju-endpoint-reset"
            data-target="<?php echo esc_attr($setting['id']); ?>">
            <?php echo esc_html__('Reset', 'komoju-japanese-payments'); ?>
        </button>
    </div>
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
                <label><?php echo esc_html($setting['title']); ?></label>
            </th>
            <td class="forminp forminp-text komoju-setup-button">
                <a href="<?php echo esc_url($setup_url); ?>"
                   class="button button-hero komoju-setup <?php echo $already_connected ? 'button-secondary' : 'button-primary'; ?>">
                    <?php
                        if ($already_connected) {
                            echo esc_html__('Reconnect with KOMOJU', 'komoju-japanese-payments');
                        } else {
                            echo esc_html__('Sign into KOMOJU', 'komoju-japanese-payments');
                        } ?>
                </a>
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
                echo esc_html__('Failed to connect to KOMOJU. Please ensure the correct secret key is set by reconnecting via the "Reconnect with KOMOJU" button above.', 'komoju-japanese-payments');
            } else {
                echo esc_html__('Once signed into KOMOJU, you can select payment methods to use as WooCommerce gateways.', 'komoju-japanese-payments');
            } ?>
                </td></tr>
            <?php
            return;
        }

        $help_tip = __('Selecting payment methods here makes them available as WooCommerce gateways. To turn them on at checkout, enable each one individually under WooCommerce > Settings > Payments.', 'komoju-japanese-payments');

        // Show each payment method as a checkbox with an icon?>
        <tr>
        <th class="titledesc" scope="row">
            <label class="komoju-payment-methods-label"><?php echo esc_html($setting['title']); ?> <?php echo wc_help_tip($help_tip); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped?></label>
        </th>
        <td class="forminp forminp-text komoju-payment-methods">
            <?php
            foreach ($all_payment_methods as $slug => $payment_method) {
                ?>
                <label class="komoju-payment-method">
                <input
                  type="checkbox"
                  name="<?php echo esc_attr($setting['id']); ?>[]"
                  value="<?php echo esc_attr($slug); ?>"
                  <?php checked(in_array($slug, $value, true)); ?>
                >
                <img
                  width="38"
                  height="24"
                  alt=""
                  src="https://komoju.com/payment_methods/<?php echo esc_attr($slug); ?>.svg">
                <?php echo esc_html($payment_method['name_' . $locale]); ?>
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
