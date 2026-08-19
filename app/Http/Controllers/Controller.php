<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Mini Ticketing API',
    description: 'Event ticketing API built with Laravel 13, MongoDB and Passport OAuth2.'
)]
#[OA\Server(
    url: 'http://localhost:8004/api',
    description: 'Local development server'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Obtain a token from /login, then send it as: Bearer {token}'
)]
abstract class Controller
{
    //
}
