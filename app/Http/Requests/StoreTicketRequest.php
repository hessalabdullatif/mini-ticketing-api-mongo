<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    // authorisation is handled by the route's events:manage scope
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'  => ['required', 'string', 'max:50'],

            // min:0 allows a free ticket type, which is legitimate.
            // max guards against a typo adding three zeros
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],

            'quantity_available' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }
}