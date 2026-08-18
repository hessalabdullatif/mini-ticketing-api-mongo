<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    // authorisation comes in step 5 — for now anyone may attempt an order
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 24-character hex string — the shape of a Mongo ObjectId
            // there's no exists: rule here because that's a database concern the service handles
            'event_id'  => ['required', 'string', 'size:24'],
            'ticket_id' => ['required', 'string', 'size:24'],

            // at least one, capped to stop someone ordering ten thousand seats
            'quantity'  => ['required', 'integer', 'min:1', 'max:10'],

            'gateway'   => ['sometimes', 'string', 'in:stripe,cmi'],
        ];
    }

    // clearer than Laravel's defaults for the API consumer
    public function messages(): array
    {
        return [
            'event_id.size'  => 'The event_id must be a valid 24-character identifier.',
            'ticket_id.size' => 'The ticket_id must be a valid 24-character identifier.',
            'quantity.max'   => 'You cannot order more than 10 tickets at once.',
        ];
    }
}