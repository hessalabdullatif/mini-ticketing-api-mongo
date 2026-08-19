<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
class TicketController extends Controller
{
    #[OA\Post(
        path: '/events/{eventId}/tickets',
        summary: 'Create a ticket type',
        description: 'Adds a ticket type to an event. The event_name is copied from the event, never taken from the request.',
        tags: ['Tickets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'eventId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type', 'price', 'quantity_available'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', example: 'VIP'),
                    new OA\Property(property: 'price', type: 'number', example: 500),
                    new OA\Property(property: 'quantity_available', type: 'integer', example: 100),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Ticket type created'),
            new OA\Response(response: 403, description: 'Token lacks the events:manage scope'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
   
    // POST /api/events/{eventId}/tickets — admins only
    public function store(StoreTicketRequest $request, string $eventId): JsonResponse
    {
        $event = Event::findOrFail($eventId);

        $ticket = Ticket::create([
            ...$request->validated(),

            'event_id' => $event->id,

            // the denormalized copy, filled here so listings need no lookup.
            // set by us, never taken from the request
            'event_name' => $event->name,
        ]);

        return (new TicketResource($ticket))
            ->response()
            ->setStatusCode(201);
    }
    #[OA\Patch(
        path: '/tickets/{id}',
        summary: 'Update a ticket type',
        description: 'Partial update. Changing the price does not affect existing orders — they store the price paid at purchase time.',
        tags: ['Tickets'],
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
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'type', type: 'string', example: 'VIP'),
                    new OA\Property(property: 'price', type: 'number', example: 600),
                    new OA\Property(property: 'quantity_available', type: 'integer', example: 150),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Ticket type updated'),
            new OA\Response(response: 403, description: 'Token lacks the events:manage scope'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]

    // PATCH /api/tickets/{id}
    public function update(UpdateTicketRequest $request, string $id): TicketResource
    {
        $ticket = Ticket::findOrFail($id);

        $ticket->update($request->validated());

        return new TicketResource($ticket->fresh());
    }
    #[OA\Delete(
        path: '/tickets/{id}',
        summary: 'Delete a ticket type',
        description: 'Only possible when no orders reference it.',
        tags: ['Tickets'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 403, description: 'Token lacks the events:manage scope'),
            new OA\Response(response: 422, description: 'The ticket type has orders'),
        ]
    )]

    // DELETE /api/tickets/{id}
    public function destroy(string $id): JsonResponse
    {
        $ticket = Ticket::findOrFail($id);

        // same reasoning as events: a ticket type people have paid for
        // can't be deleted without orphaning their orders
        if ($ticket->orders()->exists()) {
            return response()->json([
                'message' => 'This ticket type has orders and cannot be deleted.',
            ], 422);
        }

        $ticket->delete();

        return response()->json(null, 204);
    }
}