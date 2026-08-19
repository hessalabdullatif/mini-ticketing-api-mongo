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
use OpenApi\Attributes as OA;
class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}
    #[OA\Get(
        path: '/orders',
        summary: 'List my orders',
        description: 'Returns only the authenticated user\'s own orders, read from the token. One user can never see another\'s.',
        tags: ['Orders'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'A paginated list of your orders'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
  

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
    #[OA\Post(
        path: '/orders',
        summary: 'Place an order',
        description: 'The total is computed server-side from the ticket price. Any total, status or user_id sent by the client is discarded.',
        tags: ['Orders'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['event_id', 'ticket_id', 'quantity'],
                properties: [
                    new OA\Property(property: 'event_id', type: 'string', example: '6a842696e4e5ed74df0bee52'),
                    new OA\Property(property: 'ticket_id', type: 'string', example: '6a84285ecff6bfe1600b0c72'),
                    new OA\Property(property: 'quantity', type: 'integer', minimum: 1, maximum: 10, example: 2),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Order created and paid'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(
                response: 422,
                description: 'Not enough tickets, or the event is paused, cancelled, or already past'
            ),
        ]
    )]
  

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
    #[OA\Post(
        path: '/orders/{id}/refund',
        summary: 'Refund an order',
        description: 'Marks the order refunded and returns the tickets to stock, atomically. Only your own orders — someone else\'s returns 404.',
        tags: ['Orders'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'reason', type: 'string', example: 'Changed my mind'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Refunded, stock returned'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Not your order, or it does not exist'),
            new OA\Response(response: 422, description: 'Already refunded, or was never paid'),
        ]
    )]
   
    // POST /api/orders/{id}/refund
    public function refund(Request $request, string $id): JsonResponse
    {
        // scoped to the authenticated user — you can only refund your own orders.
        // someone else's order returns 404, as though it doesn't exist
        $order = Order::forUser($request->user()->id)->findOrFail($id);

        $refunded = $this->orderService->refund(
            $order,
            $request->input('reason')
        );

        return (new OrderResource($refunded))->response();
    }
}