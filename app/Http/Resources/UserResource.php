<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'    => (string) $this->id,
            'name'  => $this->name,
            'email' => $this->email,
            'role'  => $this->role,
            // password is absent here, and also in the model's $hidden —
            // two independent guards, because leaking a hash is serious
        ];
    }
}