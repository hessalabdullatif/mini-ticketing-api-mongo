<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => (string) $this->id,
            'type'               => $this->type,
            'price'              => $this->price,
            'quantity_available' => $this->quantity_available,

            // a derived field — computed here, not stored in the database
            // storing it would mean keeping two things in sync for no benefit
            'sold_out'           => $this->quantity_available === 0,
        ];
    }
}