<?php

namespace App\Models\Passport;

use Laravel\Passport\Client as PassportClient;
use MongoDB\Laravel\Eloquent\DocumentModel;

// the application requesting tokens — in our case, our own app
class Client extends PassportClient
{
    use DocumentModel;

    protected $connection = 'mongodb';
    protected $table      = 'oauth_clients';
    protected $primaryKey = '_id';
    protected $keyType    = 'string';
}
//The application requesting tokens. In our case, our own app. This is what passport:client --personal created in the previous project.
?>