<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Http\Requests\StoreEventRequest;
use Illuminate\Http\JsonResponse;

class EventController extends Controller
{
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

    // GET /api/events/{id}
    public function show(string $id): EventResource
    {
        // with('tickets') eager-loads in one extra query instead of one per event
        // findOrFail throws a 404 automatically if the id doesn't exist
        $event = Event::with('tickets')->findOrFail($id);

        return new EventResource($event);
    }
    // POST /api/events — admins only, enforced by the route's scope middleware
    public function store(StoreEventRequest $request): JsonResponse
    {
        $event = Event::create($request->validated());

        return (new EventResource($event))
            ->response()
            ->setStatusCode(201);
    }
}