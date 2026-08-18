<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    // constructor injection — Laravel resolves OrderService automatically
    // never `new OrderService()` inside a method: that makes the class untestable
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    // POST /api/orders
    public function store(StoreOrderRequest $request): JsonResponse
    {
        // temporary until step 5 — the real user will come from the token
        $user = User::first();

        // validated() returns only the fields that passed the rules
        // a client sending "total" or "status" gets them silently discarded here
        $order = $this->orderService->create($user, $request->validated());

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }
}