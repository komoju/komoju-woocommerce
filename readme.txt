=== KOMOJU Payments ===
Contributors: degica
Tags: woocommerce, payment-gateway, japanese-payments, konbini, paypay
Requires at least: 6.0
Tested up to: 7.0.2
Stable tag: 3.3.1
Requires PHP: 7.4
Requires Plugins: woocommerce
WC requires at least: 6.0.0
WC tested up to: 10.8.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept prevalent payment methods in Japan, South Korea, and beyond with KOMOJU. Boost conversions with seamless WooCommerce checkout.

== Description ==

**Give your customers the local payment experience they expect**

KOMOJU Payments connects your online store to major international credit cards and regional alternative payment method options across Japan, South Korea, Southeast Asia, Europe, and Latin America through a single integration. By offering customers their preferred local payment methods in familiar currencies, KOMOJU eliminates checkout friction, reduces cart abandonment, and drives higher store conversions—allowing you to accept payments wherever you're located and ship worldwide.

### Key Features & Benefits

* **Single Integration Setup:** Accept major credit cards, digital wallets, and convenience store payments through a single, easy-to-install plugin.
* **Frictionless Native Checkout:** Customers complete purchases smoothly on your checkout page without unnecessary redirects.
* **Pay-As-You-Grow Pricing:** Only pay a flat payment processing fee on completed transactions with no setup costs, monthly fees or hidden charges.
* **Automated Order Synchronization:** Syncs payment statuses directly to your WooCommerce orders, updating them automatically when payments are completed.
* **Enterprise-Grade Security:** Fully PCI-DSS compliant with dynamic 3D Secure (3DS 2.0) authentication and AI-powered fraud prevention to protect every transaction.
* **Flexible Payouts:** Fast funding with flexible weekly or monthly payout schedules to keep your cash flow moving.

### Supported Payment Categories

KOMOJU connects you to the payment methods your customers prefer.

* **Credit Cards:** Major international card brands alongside local and regional credit cards.
* **Digital Wallets:** Digital wallets in various markets, including widely used local options such as PayPay and Alipay.
* **Convenience Store Payments (Konbini):** Over-the-counter cash payments at major convenience store chains.
* **Bank Transfers & Carrier Billing:** Local bank transfers, Pay-easy, and mobile carrier billing options.
* **Buy Now, Pay Later (BNPL):** Flexible pay-after-delivery options such as Paidy.

*Note: To view the complete, up-to-date list of supported payment methods, please visit KOMOJU's [Payment Methods Page](https://en.komoju.com/payment-methods/).*

== Installation ==

1. **Install & Activate:** In your WordPress dashboard, go to **Plugins > Add New**, search for **KOMOJU Payments**, and click **Install Now**, then **Activate**.
2. **Connect Your Account:** Navigate to **WooCommerce > Settings > Payments**, select **KOMOJU**, and click **Sign into KOMOJU** to link your merchant account.
3. **Enable Payment Methods:** Select your preferred payment options—such as credit cards, digital wallets, or konbini payments. No coding required.

For detailed setup guides, please visit our documentation:

* [KOMOJU WooCommerce Documentation (English)](https://doc.komoju.com/docs/woocommerce)
* [KOMOJU WooCommerce Documentation (日本語)](https://ja.doc.komoju.com/docs/woocommerce)

== Frequently Asked Questions ==

= What versions of WordPress and WooCommerce are supported? =

This plugin requires WordPress 6.0 or higher (tested up to 7.0.2) and PHP 7.4 or higher. We actively maintain and test the plugin against current versions of WordPress and WooCommerce to ensure optimal security and performance.

= What should I do if I experience compatibility issues? =

If you encounter any unexpected errors or compatibility issues on your store setup, please utilise the [KOMOJU Help Center](https://help.komoju.com/hc/en-us) or inquire from the [KOMOJU Dashboard](https://app.komoju.com/merchant).

= Where can I view the full list of supported payment methods? =

To view the full, up-to-date list of supported payment methods, visit our [Payment Methods Page](https://en.komoju.com/payment-methods/).

= Do my customers need a KOMOJU account to make a payment? =

No. KOMOJU provides a frictionless guest checkout experience. Customers select their preferred payment method directly at checkout without needing to create or log into a KOMOJU account.

= Where can I get more information or support? =

You can visit our [Help Center Page](https://help.komoju.com/hc/en-us) or inquire from the [KOMOJU Dashboard](https://app.komoju.com/merchant) for assistance with account setup or technical questions.

== Screenshots ==

1. **Localized Customer Checkout:** Seamless checkout with popular local payment methods including PayPay, Konbini payments, BNPL, and credit cards.
2. **Simple Payment Management:** Easily enable, disable, and configure individual payment options directly within WooCommerce settings.
3. **Real-Time Analytics & Reporting:** Track completed transactions, payout schedules, and store performance inside the KOMOJU merchant dashboard.

== Changelog ==

= 3.3.1 =

Fixed a regression where expired and cancelled payments did not move the order to "Cancelled"
Block checkout now shows translated gateway names on WCML sites
Fixed an issue where the payment methods shown when switching from test to live mode were stale

= 3.3.0 =

UI/UX improvements using standard WordPress/WooCommerce components
API keys are now masked password fields with input validation
Added Japanese translations across the settings and payment method pages
Debug logs now appear under WooCommerce > Status > Logs
Added a Settings link and declared WooCommerce as a required plugin
Deprecated the legacy combined "KOMOJU" payment method

= 3.2.9 =

Security and code quality improvements
Added banners to indicate when a test merchant is being used

= 3.2.8 =

Security and code quality improvements

= 3.2.7 =

Security and code quality improvements
Updated minimum PHP requirement to 7.4
Updated "Tested up to" WordPress 7.0

= 3.2.6 =

Updated link to KOMOJU payment page on order dashboard to use the "/merchant" path
Fixed bug where a customer who returned to the checkout page could not create another payment for the same order
Added "komoju_session_id" as metadata on the order page
Updated compatibility for WordPress 6.9.4
Upgraded support for WooCommerce 10.7.0

= 3.2.5 =

Add cURL timeouts and graceful error handling to prevent checkout errors

= 3.2.4 =

Supress non-critical error messages

= 3.2.3 =

Supress non-critical error messages

= 3.2.2 =

Update readme.txt

= 3.2.1 =

Prevent raw JSON error messages from showing

= 3.2.0 =

Removing all references to LINE Pay

= 3.1.9 =

Update readme.txt

= 3.1.8 =

Added Quick Start Guide Link
Updated compatibility for WordPress 6.7.2
Upgraded support for WooCommerce 9.7.1
Fix: Hide field-related elements when inline fields are disabled

= 3.1.7 =

Moved "Use on-hold status" option under API settings
- The "Use on-hold status" setting for WooCommerce orders has been relocated to the API settings. Previously, this option was only available under the general KOMOJU gateway, which was not ideal for most merchants. Moving it to API settings ensures better usability and consistency.

= 3.1.6 =

Updated compatibility for WordPress 6.7.1.
Upgraded support for WooCommerce 9.5.2 (previously 9.4.1).
Fix creating checkout session when the cart is empty.

= 3.1.5 =

Updated compatibility for WordPress 6.7.0.
Upgraded support for WooCommerce 9.4.1 (previously 8.8.3).

= 3.1.4 =

Suppressed incompatibility error messages in the page editor
Fix session errors with specific themes

= 3.1.3 =

Adjust credit card icon positions
Prevent rendering hosted fields when it should not be rendered

= 3.1.2 =

Fix plugin conflicts

= 3.1.0 =
Updated to use WooCommerce version 8.8.3.
Adds a user editable description field.
Fix missing/inconsistent payment icons display.
Fixes warning about missing `fraud_details` data.
Code maintainability improvements.

= 3.0.9 =
Fix bug with order cancel webhook.

= 3.0.8 =
Register IPN handler outside of gateway initializer.
Hopefully fixes an issue where automatic updates cause webhooks to stop working.

= 3.0.7 =
Add JA translations for plugin store page FAQ.

= 3.0.6 =
Update docs for supported WordPress and WooCommerce versions.

= 3.0.5 =
Fix occasional instances of not correctly marking an order as refunded.
Update available payment method list.

= 3.0.4 =
Update docs for supported WC and WooCommerce versions.

= 3.0.3 =
Fix bug with multiple payments per order where even completed orders would be cancelled on payment cancel.

= 3.0.2 =
Fix a bug that redirected users to the wrong page when clicking on the KOMOJU payment link.

= 3.0.1 =
Fix bug with multiple payments per order where even completed orders would be cancelled on payment cancel.

= 3.0.0 =
New inline fields support. Common payment methods like credit card and konbini no longer redirect offsite for input.
The catch-all "KOMOJU" gateway now instead of radio buttons just relies on KOMOJU's own payment method selector.

= 2.7.1 =
Make DCC payments validate order amount against session instead of payment.
Request dynamic credit card icon from KOMOJU so that only supported brands are shown.

= 2.7.0 =
Change credit card icon to show brands.

= 2.6.5 =
Remove additional lingering currency check code.

= 2.6.4 =
Adjust supported versions.

= 2.6.3 =
Make sure payment gateways are always present when plugins are loaded.
Fix problem where quick setup failed on sites with a path prefix.

= 2.6.2 =
Swap first/last name order when sending to KOMOJU (KOMOJU expects given before family).

= 2.6.1 =
Fix webhooks with currencies that use cents.

= 2.6.0 =
Optionally perform order completion in the background.

= 2.5.0 =
Add 'komoju_session_return_url' filter.

= 2.4.1 =
Fixed bug where plugin would ignore locale strings that include a country code.

= 2.4.0 =
Refunding KOMOJU payments through the WooCommerce dashboard is now supported.
Added a link to the KOMOJU admin page for orders paid with KOMOJU.
Clicking "back to merchant" on KOMOJU will now take you to the pay-order page instead of checkout.
Can now toggle whether or not KOMOJU payment method icons appear.

= 2.3.1 =
Fixed cents conversion problem with currencies that use decimal points.

= 2.3.0 =
Introduced quick-setup, removing the need to copy/paste values from KOMOJU.
Removed currency restriction, allowing the plugin to be used with any store currency.

= 2.2.7 =
Fixed issue with stores that don't produce a customer name.

= 2.2.6 =
Fixed issue where new versions were not being registered automatically.

= 2.2.5 =
Fixed problem where some installs were not generating order IDs correctly.

= 2.2.4 =
Fixed issue where some stores saw errors on the settings page after filling in their secret key.

= 2.2.3 =
Fixed issue with orders that don't have an email address.

= 2.2.0 =
Users can now select individual payment methods to be exposed as WooCommerce payment gateways.
This should provide better compatibility with other plugins that filter or otherwise interact with payment gateways.

= 2.1.1 =
Update Plugin Name

= 2.1.0 =
Added filter 'woocommerce_komoju_payment_methods' to allow users to change the list of offered payment methods to their users.

= 2.0 =
Introduced new hosted checkout design
Added option to use 'on-hold' status for authorized payments.

= 1.1.0 =
Available payment methods on checkout will now match those available on the user's KOMOJU account.
Removed settings related to payment methods. Payment Methods should now be managed directly through KOMOJU.
Removed payment method icons on checkout (To be re-added at a later date)

= 1.0.1 =
Fix issue preventing coupon discounts from being applied at checkout.

= 1.0 =
Initial release for the Wordpress store.