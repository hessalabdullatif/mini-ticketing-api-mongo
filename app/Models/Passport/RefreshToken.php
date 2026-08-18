<?php
namespace App\Models\Passport;

use Laravel\Passport\RefreshToken as PassportRefreshToken;
use MongoDB\Laravel\Eloquent\DocumentModel;

// the token that renews an access token after expiry, without re-entering the password
class RefreshToken extends PassportRefreshToken
{
    use DocumentModel;

    protected $connection = 'mongodb';
    protected $table      = 'oauth_refresh_tokens';
    protected $primaryKey = '_id';
    protected $keyType    = 'string';
}
