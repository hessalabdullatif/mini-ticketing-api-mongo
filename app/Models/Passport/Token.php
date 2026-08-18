<?php

namespace App\Models\Passport;

use Laravel\Passport\Token as PassportToken;
use MongoDB\Laravel\Eloquent\DocumentModel;

class Token extends PassportToken
{
    use DocumentModel;

    protected $connection = 'mongodb';
    protected $table      = 'oauth_access_tokens';
    protected $primaryKey = '_id';
    protected $keyType    = 'string';

     protected $casts = [
        'revoked'    => 'bool',
        'expires_at' => 'datetime',
    ];
    
}