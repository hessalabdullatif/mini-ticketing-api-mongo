<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Http\Requests\StoreEventRequest;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use App\Http\Requests\UpdateEventRequest;
class EventController extends Controller
{
        #[OA\Get(
        path: '/events',
        summary: 'List events',
        description: 'Public endpoint. Optionally filter by city.',
        tags: ['Events'],
        parameters: [
            new OA\Parameter(
                name: 'city',
                in: 'query',
                required: false,
                description: 'Filter events by city',
                schema: new OA\Schema(type: 'string', example: 'Riyadh')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'A paginated list of events'),
        ]
    )]
    // GET /api/events  — optionally filtered by ?city=Riyadh
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Event::query();

        // apply the filter only when the parameter is actually present
        // filled() is true when the key exists AND isn't an empty string
        if ($request->filled('city')) {
            $query->where('city', $request->query('city'));
        }

        // newest events first
        $events = $query->orderBy('date')->paginate(15);

        return EventResource::collection($events);
    }
#[OA\Get(
        path: '/events/{id}',
        summary: 'Get one event',
        description: 'Public. Includes the event\'s ticket types.',
        tags: ['Events'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'The event id',
                schema: new OA\Schema(type: 'string', example: '6a842696e4e5ed74df0bee52')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The event with its tickets'),
            new OA\Response(response: 404, description: 'Event not found'),
        ]
    )]
  
    // GET /api/events/{id}
    public function show(string $id): EventResource
    {
        // with('tickets') eager-loads in one extra query instead of one per event
        // findOrFail throws a 404 automatically if the id doesn't exist
        $event = Event::with('tickets')->findOrFail($id);

        return new EventResource($event);
    }
    #[OA\Post(
        path: '/events',
        summary: 'Create an event',
        description: 'Requires a token carrying the events:create scope. A regular user\'s token receives 403.',
        tags: ['Events'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'city', 'date'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Riyadh Season Concert'),
                    new OA\Property(property: 'city', type: 'string', example: 'Riyadh'),
                    new OA\Property(property: 'date', type: 'string', format: 'date', example: '2026-11-20'),
                    new OA\Property(property: 'status', type: 'string', enum: ['active', 'paused', 'cancelled'], example: 'active'),
                    new OA\Property(
                        property: 'meta',
                        type: 'object',
                        description: 'Free-form data that varies by event type',
                        example: ['artist' => 'Mohammed Abdu', 'venue' => 'Mrsool Park']
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Event created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Token lacks the events:create scope'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ]
    )]
   
    // POST /api/events — admins only, enforced by the route's scope middleware
    public function store(StoreEventRequest $request): JsonResponse
    {
        $event = Event::create($request->validated());

        return (new EventResource($event))
            ->response()
            ->setStatusCode(201);
    }
    // PATCH /api/events/{id} — admins only, via the events:manage scope
    public function update(UpdateEventRequest $request, string $id): EventResource
    {
        $event = Event::findOrFail($id);

        // validated() returns only fields that were actually sent,
        // so absent fields are left alone rather than overwritten with null
        $event->update($request->validated());

        return new EventResource($event->fresh());
    }

    // DELETE /api/events/{id}
    public function destroy(string $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        // refuse to delete an event people have already paid for.
        // cancelling is the correct action there — it preserves the record
        // and lets those orders be refunded
        if ($event->orders()->exists()) {
            return response()->json([
                'message' => 'This event has orders and cannot be deleted. Cancel it instead.',
            ], 422);
        }

        // ticket types are meaningless without their event
        $event->tickets()->delete();
        $event->delete();

        return response()->json(null, 204);
    }
}