<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],

            // no Password::min() here — we're checking an existing password,
            // not setting a new one. rejecting a short one would tell an
            // attacker their guess was too short to be a real password
            'password' => ['required', 'string'],
        ];
    }
}
