<?php

namespace App\Services\Payments;

use RuntimeException;

class Gateways
{
    /** To add iBAS++ later: create app/Services/Payments/IbasGateway.php (implements PaymentGateway) and register it here. */
    private const MAP = ['manual' => ManualGateway::class];

    public static function make(): PaymentGateway
    {
        $name = config('larms.payment_gateway', 'manual');
        $class = self::MAP[$name] ?? throw new RuntimeException("Unknown payment gateway: $name");
        return new $class();
    }
}
