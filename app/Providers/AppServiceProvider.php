<?php

namespace App\Providers;

use App\Models\Passport\AuthCode;
use App\Models\Passport\Client;
use App\Models\Passport\DeviceCode;
use App\Models\Passport\RefreshToken;
use App\Models\Passport\Token;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use App\Contracts\PaymentGateway;
use App\Services\Payments\FakeMada;
use App\Services\Payments\FakeVisa;

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
        // tell Passport to use our Mongo-compatible models instead of its defaults
        Passport::useTokenModel(Token::class);
        Passport::useRefreshTokenModel(RefreshToken::class);
        Passport::useAuthCodeModel(AuthCode::class);
        Passport::useClientModel(Client::class);
        Passport::useDeviceCodeModel(DeviceCode::class);
    }
    
}