<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;

class TicketController extends Controller
{
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

    // PATCH /api/tickets/{id}
    public function update(UpdateTicketRequest $request, string $id): TicketResource
    {
        $ticket = Ticket::findOrFail($id);

        $ticket->update($request->validated());

        return new TicketResource($ticket->fresh());
    }

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