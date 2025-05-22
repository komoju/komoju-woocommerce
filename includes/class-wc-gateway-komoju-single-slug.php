<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once 'class-wc-gateway-komoju-response.php';
include_once 'class-wc-gateway-komoju-webhook-event.php';

/**
 * Specialized version of WC_Gateway_Komoju that provides only one payment method at a time.
 */
class WC_Gateway_Komoju_Single_Slug extends WC_Gateway_Komoju
{
    /* Fix for Deprecated: Creation of dynamic property */
    /** @var string */
    public $publishableKey;

    /** @var array */
    public $payment_method;

    public function __construct($payment_method)
    {
        $slug = $payment_method['type_slug'];

        $this->publishableKey = $this->get_option_compat('publishable_key', 'publishable_key');
        $this->payment_method = $payment_method;
        $this->id             = 'komoju_' . $slug;
        $this->has_fields     = $this->should_use_inline_fields($slug);
        $this->method_title   = __('Komoju', 'komoju-woocommerce') . ' - ' . $this->default_title();

        if ($this->get_option('showIcon') == 'yes') {
            $this->icon = "https://komoju.com/payment_methods/$slug.svg";

            if ($slug == 'credit_card') {
                // Show dynamic icon with supported brands.
                $brands = $payment_method['subtypes'];

                $sort_order = [
                    'visa'             => 0,
                    'master'           => 1,
                    'jcb'              => 2,
                    'american_express' => 3,
                    'diners_club'      => 4,
                    'discover'         => 5,
                ];

                // Sort by the order defined above.
                usort($brands, function ($a, $b) use ($sort_order) {
                    return $sort_order[$a] - $sort_order[$b];
                });

                $brands = implode(',', $brands);
                $this->icon .= "?brands=$brands";
            }
        }

        // TODO: It would be nice if KOMOJU told us in the payment method object whether or
        // not it supports refunds. For now, we'll just wing it.
        if (!in_array($slug, ['konbini', 'pay_easy', 'bank_transfer'])) {
            $this->supports[] = 'refunds';
        }

        parent::__construct();

        // Gimmick -
        // If fields should not be rendered honour user-defined descriptions only
        // This prevents fields to be rendered if 1) the description of the corresponding payment method is empty, and either 2a) publishable key is unavailable or 2b) inline fields are opted out in payment method settings
        // This counters a non-intuitive behaviour in WC (https://github.com/woocommerce/woocommerce/blob/5dd7713346786c5874615b34a1d56f81a29b673f/plugins/woocommerce/templates/checkout/payment-method.php#L28)
        if (!$this->has_fields) {
            $this->description = $this->get_option('description');
        }

        $this->method_description = sprintf(
            __('%s payments powered by KOMOJU', 'komoju-woocommerce'),
            $this->default_title()
        );
    }

    /**
     * Process refund.
     *
     * Attempts to refund the passed-in amount with KOMOJU.
     *
     * @param int $order_id order ID
     * @param float|null $amount refund amount
     * @param string $reason refund reason
     *
     * @return bool true or false based on success, or a WP_Error object
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order      = wc_get_order($order_id);
        $payment_id = $order->get_meta('komoju_payment_id');
        $currency   = $order->get_currency();

        if ($payment_id == '') {
            return false;
        }

        $payload = [];
        if (!is_null($amount)) {
            $payload['amount'] = self::to_cents($amount, $currency);
        }
        if ($reason != '') {
            $payload['description'] = $reason;
        }

        try {
            $payment = $this->komoju_api->refund($payment_id, $payload);
        } catch (KomojuExceptionBadServer|KomojuExceptionBadJson $e) {
            $error_message = $e->getMessage();
            $this->log($error_message);

            return new WP_Error('komoju_refund_failed', $error_message);
        }

        $refund = $payment->refunds[count($payment->refunds) - 1];

        if ($refund && $refund->amount == self::to_cents($amount, $currency)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Create incomplete session for rendering <komoju-fields>
     */
    public function create_session_for_fields()
    {
        $komoju_api     = $this->komoju_api;
        $currency       = get_woocommerce_currency();
        $order_total    = 0;

        if (WC()->cart) {
            $order_total = $this->get_order_total();
        }

        $session_params = [
            'amount'         => self::to_cents($order_total, $currency),
            'currency'       => $currency,
            'default_locale' => self::get_locale_or_fallback(),
            'metadata'       => [
                'woocommerce_note' => 'This session is only for rendering inline fields, and will not be completed.',
            ],
        ];

        if ($order_total == 0) {
            return null;
        }

        return $komoju_api->createSession($session_params);
    }

    public function validate_fields()
    {
        return true;
    }

    public function should_use_inline_fields($slug)
    {
        // Merchants can disable inline payment fields via gateway settings.
        if ($this->get_option('inlineFields') !== 'yes') {
            return false;
        }
        // We can't use the komoju-fields library without a publishable key.
        if (!$this->publishableKey) {
            return false;
        }

        return true;
    }

    public function payment_fields()
    {
        if (!$this->has_fields) {
            return;
        }

        // We lazily fetch one session to be shared by all payment methods with dynamic fields.
        static $checkout_session;
        if (is_null($checkout_session)) {
            $checkout_session = $this->create_session_for_fields();
        }
        $payment_type = $this->payment_method['type_slug']; ?>
        <komoju-fields
            token name="komoju_payment_token"
            komoju-api="<?php echo KomojuApi::endpoint(); ?>"
            publishable-key="<?php echo esc_attr($this->publishableKey); ?>"
            session="<?php echo esc_attr(json_encode($checkout_session)); ?>"
            payment-type="<?php echo esc_attr($payment_type); ?>"
            style="display: block"
        >
        </komoju-fields>
        <script>
            (() => {
                const fields = document.querySelector('komoju-fields[payment-type="<?php echo esc_attr($payment_type); ?>"]');
                fields.addEventListener('komoju-error', event => {
                    // Missing parameter errors likely mean we cannot use tokens for this payment method, so we will just
                    // submit the form (thus navigating to session page) in such cases.
                    if (event.detail.error?.code !== 'missing_parameter') return;
                    event.preventDefault();
                    fields.submitParentForm();
                });
            })();
        </script>
        <?php
    }

    public function process_payment($order_id, $payment_type = null)
    {
        $order = wc_get_order($order_id);
        // If we have a token from <komoju-fields>, we can process payment immediately.
        // Otherwise we will redirect to the KOMOJU hosted page.
        $token = sanitize_text_field($_POST['komoju_payment_token']);

        // Gimmick: If there is a KOMOJU payment UUID already set for the order, try to cancel the preceding payment.
        // Some considerations:
        // - It will be helpful to prevent duplicated Konbini payments, which can be caused through the "order-pay" page.
        // - If the metadata is set, the payment already exists, and it is safe to cancel the payment.
        //   - It is also worth to note that the paths to this code guarantee that this order is payable, i.e., no payment is captured or has expired yet, and therefore it is cancellable.
        // - If the metadata is not set, there is nothing we can do anyway.
        $komoju_payment_id = $order->get_meta('komoju_payment_id');
        if (!empty($komoju_payment_id)) {
            $this->komoju_api->cancel($komoju_payment_id, []);
        }

        if (!$token || $token === '') {
            return parent::process_payment($order_id, $this->payment_method['type_slug']);
        }

        $session = $this->create_session_for_order($order_id, $payment_type);
        $result  = $this->komoju_api->paySession($session->id, [
            'customer_email'  => $order->get_billing_email(),
            'payment_details' => $token,
        ]);

        $order->set_transaction_id($session->payment_data->external_order_num);
        $order->save();

        if ($result->redirect_url) {
            return [
                'result'   => 'success',
                'redirect' => $result->redirect_url,
            ];
        } else {
            wc_add_notice(__('Payment error:', 'komoju-woocommerce') . $result->error, 'error');
        }
    }

    public function default_title()
    {
        return $this->payment_method['name_' . WC_Gateway_Komoju::get_locale_or_fallback()];
    }
}
