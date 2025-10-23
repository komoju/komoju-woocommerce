=== KOMOJU Payments ===
Contributors: degica
Tags: WooCommerce,Payment Gateway,Komoju
Requires at least: 6.0
Tested up to: 6.7.2
Stable tag: trunk
Requires PHP: 7.2
WC requires at least: 6.0.0
WC tested up to: 9.7.1
License: MIT
License URI: https://directory.fsf.org/wiki/License:X11

== Description ==

## Quick Start Guide

If you'd like to set up this plugin quickly, please see our  

- [**KOMOJU-WooCommerce Quick Start Guide**](https://tech.degica.com/komoju-woocommerce/en/user_guide/getting_started/)
- [**KOMOJU-WooCommerce プラグインの始め方**](https://tech.degica.com/komoju-woocommerce/user_guide/getting_started/)

---

## KOMOJU Global Payment Gateway

**Expand your business to global markets and accept payments as a local!**

Give your customers a local payment experience they expect. Increase conversion rates with our local payment methods such as Alipay, Credit Cards (South-Korea & Japan), GrabPay (Singapore), Konbini (Japan) and many more!

If you're expanding your business globally and need a payment partner with local experience, [KOMOJU](https://en.komoju.com/) is right for you. Adding the payment methods your customer expects is key to success and will drastically decrease abandon rates and increase conversion and sales. Give your customers a local payment experience, in the local currency they are familiar with and with their preferred payment method.

[KOMOJU](https://en.komoju.com/) integrates seamlessly with your checkout and all payment options are displayed dynamically. This means you do not have to make any additional configuration after setting up our plugin.

### Features

KOMOJU lets you accept payments in multiple currencies through a variety of local payment methods and credit/debit cards. We cover payment methods in Japan, South-Korea, China, South-East Asia, Australia and Europe!

- **Clear pricing**: You only pay per transaction - no hidden fees, charges or setup costs!
- **Fast Payout**: Choose between a monthly or weekly cycle - you'll get your funds quickly.
- **Multi-Currency:** Accept payments in local currency, and receive your funds in your preferred currency. We support USD, EUR, JPY, SGD, HKD and AUD for payouts.
- **Easy onboarding:** We quickly review every merchant and can provide payments fast.
- **Many payment methods accepted**: We provide payment methods across 6 regions, with most payment methods covered.

### Benefits

When enabling KOMOJU Payment Gateway on your store, you will be able to accept payments from your customers, worldwide. Your customers will pay in their preferred currency and payment method.

Our plugin is fully PCI-DSS compliant, so you do not need to worry about regulations and cardholder safety. With 3D-Secure 2.0, you can be rest assured that your payments are secured and authenticated.

You can accept payments with digital wallets such as Alipay, WeChat Pay, PayPay, Toss, PAYCO, GrabPay, Doku, OVO and many more. Payments with digital wallets give your customers a customised, trusted and convenient payment option.

We support businesses that are conducting business cross-border. For the majority of the payment methods we do not require a local entity or bank account. Accept payments wherever you're located, and ship your products worldwide.

**KOMOJU is the ideal way to accept payments on your store to ensure your customers can pay without friction with the payment methods they want.**

### Accepted Payment Methods

We currently accept the following payment methods:

**Japan**

- Credit / Debit Cards Japan (Visa, Mastercard, American Express, JCB, Diners Club)
- Convenience Store (Konbini)
- Pay-Easy
- Japan Bank Transfer
- PayPay
- MerPay
- RakutenPay
- Paidy
- Carrier Billing (NTT Docomo, Softbank, au)
- Prepaid Vouchers (WebMoney, BitCash, NET Cash)

**South-Korea**

- Credit / Debit Cards (Samsung, Lotte, Hyundai, Hana, BC, NH, Shinhan, KB)
- Toss
- Payco

**China**

- Alipay
- WeChat Pay
- UnionPay

**South-East Asia**

- Doku
- OVO
- GrabPay
- Dragonpay
- FPX

**Europe**

- Bancontact
- Multibanco
- EPS
- Giropay
- Przelewy24
- BLIK
- MyBank
- Sofort

== Frequently Asked Questions ==

= What versions of WordPress and WooCommerce is this compatible with? =

At the moment, this plugin has been tested and is known to work up to WordPress
6.7.2 and WooCommerce 9.7.1 If you are using a later version, please check the
next section or contact us regarding this.

= What should I do if I am using newer versions of WordPress and WooCommerce? =

We recommend performing a fresh install of WordPress 6.7.2 and WooCommerce
9.7.1 before proceeding to install this plugin. You can temporarily downgrade
from a newer version of WordPress and WooCommerce before continuing installation.
However, downgrading from newer versions of WordPress and WooCommerce may result in
issues with installing this plugin. If you are experiencing problems, please
contact our support team (contact@komoju.com).

= Where can I get more information? =

Please contact contact@komoju.com if you have any questions about
the installation of the module.

= どのWordPress・WooCommerceのバージョンに対応していますか？=
現時点でこのプラグインは、WordPress 6.7.2およびWooCommerce 9.7.1まで動作することが確認されています。
それ以降のバージョンをお使いの場合は、以下をお試し頂くか、contact@komoju.comまでご連絡ください。

= 新しいバージョンのWordPressとWooCommerceを使用している場合はどうすればよいですか？ =
このプラグインをインストールする前に、まずWordPress 6.7.2とWooCommerce 9.7.1を新規インストールすることをお勧めします。
新しいバージョンから旧バージョンへ一時的にダウングレードし、接続頂くことも可能ですが、新しいバージョンからダウングレードすると、このプラグインのインストールに問題が生じる可能性がございます。
問題が発生した場合は、サポートチーム（contact@komoju.com）までご連絡ください。

= 詳細はどこで入手できますか？=
KOMOJUの接続方法についてご不明な点がありましたら、弊社のサポートチーム（contact@komoju.com） までお問合せください。

== Installation ==

1. Upload the plugin to your server where wordpress and WooCommerce are installed via FTP or other file transfer method to the wordpress/wp-content/plugins directory
1. Change to the plugin directory
1. Run: `unzip woocommerce-komoju.zip`

Login to Wordpress as Administrator

Click on the 'Plugins' menu on the left hand side.

You should see the WooCommerce Komoju Gateway listed among the plugins. Click the Activate Link for this gateway.
Next you need to configure the Plugin in WooCommerce. To do so, from the left hand menu, select 'WooCommerce' and then 'Settings'.

Then click 'Checkout' from the Top Menu.

Click on the 'Komoju' Link just below the top tabbed menu.

Click the Enable/Disable Box to enable this gateway.

Enter your KOMOJU API credentials in this configuration page. The information to set here is defined in your KOMOJU Merchant Settings page. Ignore the "Webhook Secret Token" for now, as it will be filled out after your Komoju account has been configured.
The value for "Komoju merchant ID" field is the Merchant UUID value on the Merchant Settings page.
The value for the "Secret Key from Komoju" field is the Secret Key value on the Merchant Settings page.
Make sure they match.
Always start the configuration by using the test mode secret key and do a few tests before going live.

To enable Debug click the 'Enable logging' box.

= Configuring your Komoju account =
To ensure that the WooCommerce plugin works correctly you will need to set up a webhook from your KOMOJU dashboard to the wordpress instance. To do this you will need to go to your [Webhook page on the Komoju dashboard](https://komoju.com/admin/webhooks) and click "New Webhook". If you don't know what the webhook URL should be you can check the admin page for this plugin on your wordpress instance to see the default address. The secret can be anything you want (as long as you remember it), but you must make sure the following events are ticked:

- payment.authorized
- payment.captured
- payment.expired
- payment.cancelled
- payment.refunded

Ensure that the "Active" checkbox is also ticked and then click "Create Webhook".

Go back to your Wordpress instance and set the "Webhook Secret Token" value on the Komoju Woocommerce plugin to be the same as the secret set for the webhook.

== Changelog ==

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
Updated compatibility for WordPress 6.7.2.
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
