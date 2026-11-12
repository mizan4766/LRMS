<?php

namespace App\Services\Payments;

/**
 * Single place where an outside payment system (iBAS++, EFT, bKash ...) can be connected later.
 * Each method returns the values to store in the payments row: status, reference, method, paid_at.
 */
interface PaymentGateway
{
    public function name(): string;

    /** Requiring body's deposit of the compensation money. */
    public function recordDeposit(array $in): array;

    /** Payment of compensation to one interested person. */
    public function disburse(array $in): array;
}
