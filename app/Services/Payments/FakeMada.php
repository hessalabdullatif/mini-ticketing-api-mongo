<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FakeMada implements PaymentGateway
{
    public function charge(float $amount, array $meta = []): string
    {
        $reference = 'mada_' . Str::random(16);

        Log::info('Charge processed', [
            'gateway'   => $this->name(),
            'amount'    => $amount,
            'reference' => $reference,
        ]);

        return $reference;
    }

    public function name(): string
    {
        return 'mada';
    }
}