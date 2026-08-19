<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    // defines exactly what the API returns for one event
    // the model holds everything; this decides what the outside world sees
    public function toArray(Request $request): array
    {
        return [
            // Mongo's _id is an ObjectId — cast to string so it serialises cleanly in JSON
            'id'      => (string) $this->id,

            'name'    => $this->name,
            'city'    => $this->city,

            // active | paused | cancelled — buyers need to know before trying to order
            'status'  => $this->status,

            // format explicitly rather than letting Carbon decide
            'date'    => $this->date->format('Y-m-d'),

            // the flexible field — passed through as-is
            'meta'    => $this->meta,

            // only included when the relation was actually loaded — guards against N+1
            'tickets' => TicketResource::collection($this->whenLoaded('tickets')),

            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
