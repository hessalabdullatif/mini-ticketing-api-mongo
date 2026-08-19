<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    // authorisation is handled by the route's events:manage scope
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 'sometimes' — this is a partial update, so every field is optional.
            // a field absent from the request is left untouched, not nulled
            'name' => ['sometimes', 'string', 'max:255'],
            'city' => ['sometimes', 'string', 'max:100'],

            // note: no after:today here, unlike creation.
            // an admin may legitimately need to correct the date of a past event
            'date' => ['sometimes', 'date'],

            'status' => ['sometimes', 'string', 'in:active,paused,cancelled'],
            'meta'   => ['sometimes', 'array'],
        ];
    }
}