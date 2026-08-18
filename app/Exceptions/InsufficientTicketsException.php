<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InsufficientTicketsException extends Exception
{
    public function __construct(
        private readonly int $requested,
        private readonly int $available,
    ) {
        parent::__construct("Only {$available} tickets remaining, {$requested} requested.");
    }

    // Laravel calls this automatically when the exception escapes a controller
    // so the service can just throw, and the HTTP concern is handled here
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors'  => [
                'quantity' => [$this->getMessage()],
            ],
        ], 422);
    }
}
