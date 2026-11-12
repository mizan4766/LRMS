<?php

namespace App\Services;

/** Sec 5(3): above 50 bigha (16.50 acre) the Ministry decides, otherwise the Divisional Commissioner. */
class AuthorityRouter
{
    public static function route(float $acre, float $threshold = 16.50): string
    {
        return $acre > $threshold ? 'ministry' : 'commissioner';
    }
}
