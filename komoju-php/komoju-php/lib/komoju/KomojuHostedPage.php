<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KomojuHostedPage
{
    public function __construct($uuid, $secretKey)
    {
        $this->endpoint  = 'https://komoju.com';
        $this->uuid      = $uuid;
        $this->secretKey = $secretKey;
    }

    public function setEndpoint($endpoint)
    {
        $this->endpoint = $endpoint;
    }

    public function url($paymentMethod, $params)
    {
        $qsParams = [];
        if (!isset($params['timestamp'])) {
            $params['timestamp'] = time();
        }
        foreach ($params as $key => $val) {
            $qsParams[] = urlencode($key) . '=' . urlencode($val);
        }
        sort($qsParams);
        $queryString = implode('&', $qsParams);

        $komojuEndpoint = '/en/api/' . $this->uuid . '/transactions/';
        $url            = $komojuEndpoint . $paymentMethod . '/new?' . $queryString;
        $hmac           = hash_hmac('sha256', $url, $this->secretKey);
        $queryString .= '&hmac=' . $hmac;

        return $this->endpoint . $komojuEndpoint . $paymentMethod . '/new?' . $queryString;
    }

    public function validate($requestUri, $incomingHmac)
    {
        $qsUri    = explode('?', $requestUri)[0];
        $qsParams = explode('?', $requestUri)[1];
        $qsParams = explode('&', $qsParams);
        foreach ($qsParams as $subKey => $qsParam) {
            if ('hmac' === explode('=', $qsParam)[0]) {
                unset($qsParams[$subKey]);
                break;
            }
        }
        sort($qsParams);
        $queryString = implode('&', $qsParams);
        $url         = $qsUri . '?' . $queryString;
        $hmac        = hash_hmac('sha256', $url, $this->secretKey);

        return $hmac === $incomingHmac;
    }
}
