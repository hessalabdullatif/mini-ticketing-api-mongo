<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,

            // the frozen copies — served straight from the order document
            // no lookup against events or tickets needed, and no risk of showing today's price
            'event' => [
                'id'   => (string) $this->event_id,
                'name' => $this->event_name,
                'date' => $this->event_date->format('Y-m-d'),
            ],

            'ticket' => [
                'id'   => (string) $this->ticket_id,
                'type' => $this->ticket_type,
            ],

            'quantity'   => $this->quantity,
            'unit_price' => $this->unit_price,
            'total'      => $this->total,
            'status'     => $this->status,

            // null until the order is paid
            'paid_at'    => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}