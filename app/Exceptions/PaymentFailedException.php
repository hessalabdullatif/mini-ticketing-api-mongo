<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class PaymentFailedException extends Exception
{
    public function __construct(
        private readonly string $gateway,
        string $reason = 'Payment was declined.',
    ) {
        parent::__construct($reason);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'gateway' => $this->gateway,
        ], 402);   // 402 Payment Required
    }
}