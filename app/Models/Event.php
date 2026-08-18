<?php

namespace App\Models;
//
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\HasMany;

class Event extends Model
{
    // the connection defined in config/database.php
    protected $connection = 'mongodb';

    // the collection name in Mongo (called table in Eloquent for compatibility)
    protected $table = 'events';

    protected $fillable = [
        'name',
        'city',
        'date',
        'meta',    //the flexible field
    ];

    protected $casts = [
        'date' => 'date',
    ];

    // default value — empty object instead of null, so no if ($event->meta) checks
    // a real array [] not the string '[]', because we're not casting
    protected $attributes = [
        'meta' => [],
    ];

    // each event has several ticket types — Laravel infers the event_id key
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}