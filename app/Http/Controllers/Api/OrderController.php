<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    // GET /api/orders — only the authenticated user's own orders
    public function index(Request $request): AnonymousResourceCollection
    {
        // forUser is the scope defined on the Order model.
        // this line is the entire authorisation rule: you see yours, nobody else's
        $orders = Order::forUser($request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return OrderResource::collection($orders);
    }

    // POST /api/orders
    public function store(StoreOrderRequest $request): JsonResponse
    {
        // the real authenticated user, read from the token
        // was User::first(), which attributed every order to the same person
        $order = $this->orderService->create($request->user(), $request->validated());

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }
}