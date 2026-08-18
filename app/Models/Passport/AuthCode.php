<?php

namespace App\Models\Passport;

use Laravel\Passport\AuthCode as PassportAuthCode;
use MongoDB\Laravel\Eloquent\DocumentModel;

// a temporary code in the authorization code flow — like "Sign in with Google"
// unused by us, but defined because Passport resolves all its references at boot
class AuthCode extends PassportAuthCode
{
    use DocumentModel;

    protected $connection = 'mongodb';
    protected $table      = 'oauth_auth_codes';
    protected $primaryKey = '_id';
    protected $keyType    = 'string';
}
?>