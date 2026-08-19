<?php

namespace App\Exceptions;

use App\Models\Order;
use Exception;
use Illuminate\Http\JsonResponse;

class OrderNotRefundableException extends Exception
{
    public function __construct(
        private readonly Order $order,
    ) {
        parent::__construct(
            $order->status === Order::STATUS_REFUNDED
                ? 'This order has already been refunded.'
                : 'Only paid orders can be refunded.'
        );
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors'  => [
                'order' => [$this->getMessage()],
            ],
        ], 422);
    }
}