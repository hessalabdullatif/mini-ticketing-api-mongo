<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// implements the contract — PHP errors if charge() or name() is missing
class FakeVisa implements PaymentGateway
{
    public function charge(float $amount, array $meta = []): string
    {
        // a real gateway would make an HTTP call to the provider here
        // we log and return a fake reference instead
        $reference = 'visa_' . Str::random(16);

        Log::info('Charge processed', [
            'gateway'   => $this->name(),
            'amount'    => $amount,
            'reference' => $reference,
            // never log card numbers, CVVs, or tokens
        ]);

        return $reference;
    }

    public function name(): string
    {
        return 'visa';
    }
}