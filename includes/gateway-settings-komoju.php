<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Gateway-specific Settings for Komoju
 */

return [
    'enabled' => [
        'title'   => __('Enable/Disable', 'komoju-japanese-payments'),
        'type'    => 'checkbox',
        'label'   => __('Enable Komoju', 'komoju-japanese-payments'),
        'default' => 'no',
    ],
    'showIcon' => [
        'title'       => __('Icon', 'komoju-japanese-payments'),
        'label'       => __('Show icon on checkout', 'komoju-japanese-payments'),
        'type'        => 'checkbox',
        'default'     => 'yes',
    ],
    'title' => [
        'title'       => __('Title', 'komoju-japanese-payments'),
        'type'        => 'text',
        'description' => __('This controls the title which the user sees during checkout.', 'komoju-japanese-payments'),
        'default'     => $this->default_title(),
        'desc_tip'    => true,
    ],
    'description' => [
        'title'       => __('Description', 'komoju-japanese-payments'),
        'type'        => 'textarea',
        'description' => __('This controls the description which the user sees during checkout.', 'komoju-japanese-payments'),
        'default'     => $this->default_description(),
        'desc_tip'    => true,
    ],
    'inlineFields' => [
        'title'       => __('Inline payment fields', 'komoju-japanese-payments'),
        'type'        => 'checkbox',
        'description' => __('If checked, this payment method will show fields directly in the checkout page (if supported).', 'komoju-japanese-payments'),
        'default'     => 'yes',
        'desc_tip'    => true,
    ],
];
