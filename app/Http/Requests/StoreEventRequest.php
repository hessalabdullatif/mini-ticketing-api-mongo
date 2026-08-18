<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    // authorisation is handled by the route's scope middleware, not here
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],

            // after:today stops anyone creating an event in the past
            'date' => ['required', 'date', 'after:today'],

            // meta is free-form — that's the entire point of it
            'meta' => ['sometimes', 'array'],
        ];
    }
}