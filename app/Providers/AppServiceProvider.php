<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Models\Passport\AuthCode;
use App\Models\Passport\Client;
use App\Models\Passport\DeviceCode;
use App\Models\Passport\RefreshToken;
use App\Models\Passport\Token;
use App\Services\Payments\FakeMada;
use App\Services\Payments\FakeVisa;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // when anything type-hints PaymentGateway, build the one named in config
        // swapping providers becomes a config change, never a code change
        $this->app->bind(PaymentGateway::class, function () {
            return match (config('services.default_gateway', 'visa')) {
                'mada'  => new FakeMada(),
                default => new FakeVisa(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // tell Passport to use our Mongo-compatible models instead of its SQL defaults.
        // without these five lines, every authenticated request fails
        Passport::useTokenModel(Token::class);
        Passport::useRefreshTokenModel(RefreshToken::class);
        Passport::useAuthCodeModel(AuthCode::class);
        Passport::useClientModel(Client::class);
        Passport::useDeviceCodeModel(DeviceCode::class);

        // the permissions a token can carry
        Passport::tokensCan([
            'orders:create' => 'Place orders',
            'orders:read'   => 'View own orders',
            'events:create' => 'Create events',
            'events:manage' => 'Update and delete events',
        ]);

        // tokens that don't request specific scopes get these
        Passport::defaultScopes([
            'orders:create',
            'orders:read',
        ]);
    }
}