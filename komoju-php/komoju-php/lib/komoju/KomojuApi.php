<?php

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
        $ch = curl_init($this->endpoint . $uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers());
        curl_setopt($ch, CURLOPT_USERPWD, $this->secretKey . ':');
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new KomojuExceptionBadServer($error);
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code !== 200) {
            $komojuException           = new KomojuExceptionBadServer($result);
            $komojuException->httpCode = $http_code;
            throw $komojuException;
        }

        curl_close($ch);

        $decoded = json_decode($result, $asArray);
        if ($decoded === null) {
            throw new KomojuExceptionBadJson($result);
        }

        return $decoded;
    }

    // e.g. $payload = array(
    //     'foo' => 'bar'
    // );
    private function post($uri, $payload)
    {
        $payload['fraud_details'] = [
            // Null-coalesce to avoid undefined index notices in logs.
            'customer_ip'        => $_SERVER['REMOTE_ADDR']          ?? '',
            'customer_email'     => $payload['customer_email']       ?? '',
            'browser_language'   => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            'browser_user_agent' => $_SERVER['HTTP_USER_AGENT']      ?? '',
        ];

        $ch        = curl_init($this->endpoint . $uri);
        $data_json = json_encode($payload);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->secretKey . ':');
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new KomojuExceptionBadServer($error);
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code !== 200) {
            $komojuException           = new KomojuExceptionBadServer($result);
            $komojuException->httpCode = $http_code;
            throw $komojuException;
        }

        curl_close($ch);

        $decoded = json_decode($result);
        if ($decoded === null) {
            throw new KomojuExceptionBadJson($result);
        }

        return $decoded;
    }

    private function headers()
    {
        $result = [
            'Content-Type: application/json',
            "komoju-via: {$this->via}",
        ];

        $waf_token = get_option('komoju_woocommerce_waf_staging_token');
        if ($waf_token) {
            $result[] = "Cookie: waf_staging_token=$waf_token";
        }

        return $result;
    }
}
