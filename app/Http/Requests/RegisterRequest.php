<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    // anyone may register — no authorisation needed
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            // unique:users,email queries Mongo before allowing the write.
            // this is the rule that stops the duplicate users you created earlier
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],

            // 'confirmed' expects a matching password_confirmation field
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ];
    }
}