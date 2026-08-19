<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'  => ['sometimes', 'string', 'max:50'],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:100000'],
            'quantity_available' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }
}