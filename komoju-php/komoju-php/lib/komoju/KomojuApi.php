<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KomojuApi
{
    /* Fix for Deprecated: Creation of dynamic property */
    public $endpoint;
    public $via;
    public $secretKey;

    public static function defaultEndpoint()
    {
        return 'https://komoju.com';
    }

    public static function endpoint()
    {
        $endpoint = get_option('komoju_woocommerce_api_endpoint');
        if (!$endpoint) {
            $endpoint = self::defaultEndpoint();
        }

        return $endpoint;
    }

    public function __construct($secretKey)
    {
        $this->endpoint  = self::endpoint();
        $this->via       = 'woocommerce';
        $this->secretKey = $secretKey;
    }

    public function setEndpoint($endpoint)
    {
        $this->endpoint = $endpoint;
    }

    public function paymentMethods()
    {
        return $this->get('/api/v1/payment_methods', true);
    }

    public function createSession($payload)
    {
        return $this->post('/api/v1/sessions', $payload);
    }

    public function paySession($sessionUuid, $payload)
    {
        return $this->post('/api/v1/sessions/' . $sessionUuid . '/pay', $payload);
    }

    public function session($sessionUuid)
    {
        return $this->get('/api/v1/sessions/' . $sessionUuid);
    }

    public function refund($paymentUuid, $payload)
    {
        return $this->post('/api/v1/payments/' . $paymentUuid . '/refund', $payload);
    }

    public function cancel($paymentUuid, $payload)
    {
        return $this->post('/api/v1/payments/' . $paymentUuid . '/cancel', $payload);
    }

    private function get($uri, $asArray = false)
    {
        $url = $this->endpoint . $uri;

        $response = wp_remote_get($url, [
            'headers'   => $this->wp_headers(),
            'timeout'   => 30,
        ]);

        if (is_wp_error($response)) {
            throw new KomojuExceptionBadServer($response->get_error_message());
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body      = wp_remote_retrieve_body($response);

        if ($http_code !== 200) {
            $komojuException           = new KomojuExceptionBadServer($body);
            $komojuException->httpCode = $http_code;
            throw $komojuException;
        }

        $decoded = json_decode($body, $asArray);
        if ($decoded === null) {
            throw new KomojuExceptionBadJson($body);
        }

        return $decoded;
    }

    // e.g. $payload = array(
    //     'foo' => 'bar'
    // );
    private function post($uri, $payload)
    {
        $payload['fraud_details'] = [
            'customer_ip'        => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ),
            'customer_email'     => sanitize_email( $payload['customer_email'] ?? '' ),
            'browser_language'   => sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '' ) ),
            'browser_user_agent' => sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ),
        ];

        $url       = $this->endpoint . $uri;
        $data_json = wp_json_encode($payload);

        $response = wp_remote_post($url, [
            'headers' => $this->wp_headers(),
            'body'    => $data_json,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new KomojuExceptionBadServer($response->get_error_message());
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body      = wp_remote_retrieve_body($response);

        if ($http_code !== 200) {
            $komojuException           = new KomojuExceptionBadServer($body);
            $komojuException->httpCode = $http_code;
            throw $komojuException;
        }

        $decoded = json_decode($body);
        if ($decoded === null) {
            throw new KomojuExceptionBadJson($body);
        }

        return $decoded;
    }

    /**
     * Build headers array for wp_remote_* functions.
     *
     * @return array
     */
    private function wp_headers()
    {
        $headers = [
            'Content-Type'  => 'application/json',
            'komoju-via'    => $this->via,
            'Authorization' => 'Basic ' . base64_encode($this->secretKey . ':'),
        ];

        $waf_token = get_option('komoju_woocommerce_waf_staging_token');
        if ($waf_token) {
            $headers['Cookie'] = 'waf_staging_token=' . $waf_token;
        }

        return $headers;
    }
}
