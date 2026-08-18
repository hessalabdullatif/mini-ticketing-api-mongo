<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // POST /api/register
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            ...$request->validated(),

            // the role is decided here, by us — never taken from the request,
            // otherwise anyone could register as an admin by adding one field
            'role' => 'user',
        ]);

        // scopes passed as the second argument
        $token = $user->createToken('api-token', $this->scopesFor($user))->accessToken;

        Log::info('User registered', ['user_id' => $user->id]);

        return response()->json([
            'user'         => new UserResource($user),
            'access_token' => $token,
            'token_type'   => 'Bearer',
        ], 201);
    }

    // POST /api/login
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        // Auth::attempt hashes the given password and compares it to the stored hash.
        // it returns false for both a wrong password and a non-existent email
        if (! Auth::attempt($credentials)) {
            // one message for both cases, deliberately — saying "email not found"
            // would let someone test addresses and learn who has an account
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        $user = Auth::user();

        //scopes passed as the second argument
        $token = $user->createToken('api-token', $this->scopesFor($user))->accessToken;

        Log::info('User logged in', ['user_id' => $user->id]);

        return response()->json([
            'user'         => new UserResource($user),
            'access_token' => $token,
            'token_type'   => 'Bearer',
        ]);
    }

    // POST /api/logout
    public function logout(Request $request): JsonResponse
    {
        // revoke only the token used for this request, not every device the user owns.
        // logging out on your phone shouldn't log you out on your laptop
        $request->user()->token()->revoke();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    // a token carries only what its owner's role permits
    private function scopesFor(User $user): array
    {
        return $user->isAdmin()
            ? ['orders:create', 'orders:read', 'events:create', 'events:manage']
            : ['orders:create', 'orders:read'];
    }
}