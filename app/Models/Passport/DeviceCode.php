<?php


namespace App\Models\Passport;

use Laravel\Passport\DeviceCode as PassportDeviceCode;
use MongoDB\Laravel\Eloquent\DocumentModel;

// for browserless devices — like logging into a TV with a code from your phone
// unused by us, same reasoning as AuthCode
class DeviceCode extends PassportDeviceCode
{
    use DocumentModel;

    protected $connection = 'mongodb';
    protected $table      = 'oauth_device_codes';
    protected $primaryKey = '_id';
    protected $keyType    = 'string';
}
//For browser-less devices, like logging into a TV. New in Passport 13, also unused by us.
?>