<?php

namespace App\Services\Payments;

/** Default mode: officials enter the challan / transaction reference by hand; nothing is sent to any bank. */
class ManualGateway implements PaymentGateway
{
    public function name(): string { return 'manual'; }

    public function recordDeposit(array $in): array
    {
        // "received" until the accounts officer verifies it (step 22.1)
        return ['status' => 'received', 'reference' => $in['reference'], 'method' => $in['method'], 'paid_at' => $in['date']];
    }

    public function disburse(array $in): array
    {
        return ['status' => 'paid', 'reference' => $in['reference'], 'method' => $in['method'], 'paid_at' => now()];
    }
}
