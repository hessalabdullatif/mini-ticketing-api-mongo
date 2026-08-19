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
use OpenApi\Attributes as OA;
class AuthController extends Controller
{
    #[OA\Post(
        path: '/register',
        summary: 'Register a new user',
        description: 'Public. Creates a user with the "user" role and returns an access token. The role is assigned server-side and cannot be set by the client.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Hessa'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'hessa@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'User created, token returned'),
            new OA\Response(response: 422, description: 'Validation failed — email already taken or password too short'),
        ]
    )]
   
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
        #[OA\Post(
        path: '/login',
        summary: 'Log in',
        description: 'Public. Returns an access token whose scopes depend on the user role.',
        tags: ['Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'hessa@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Token returned'),
            new OA\Response(response: 422, description: 'Credentials do not match'),
        ]
    )]

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
     #[OA\Post(
        path: '/logout',
        summary: 'Log out',
        description: 'Revokes only the token used for this request, not every device.',
        tags: ['Auth'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logged out'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]

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