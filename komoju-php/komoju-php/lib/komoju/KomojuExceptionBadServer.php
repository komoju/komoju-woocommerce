<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KomojuExceptionBadServer extends Exception
{
    public $httpCode;
}
