<?php

namespace App\Models;
//
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\HasMany;

class Event extends Model
{

    //the allowed states, as constants rather than loose strings
    const STATUS_ACTIVE    = 'active';     // on sale
    const STATUS_PAUSED    = 'paused';     // temporarily not selling
    const STATUS_CANCELLED = 'cancelled';

    // the connection defined in config/database.php
    protected $connection = 'mongodb';

    // the collection name in Mongo (called table in Eloquent for compatibility)
    protected $table = 'events';

    protected $fillable = [
        'name',
        'city',
        'date',
        'meta', 
        'status',   //the flexible field
    ];

    protected $casts = [
        'date' => 'date',
    ];

    // default value — empty object instead of null, so no if ($event->meta) checks
    // a real array [] not the string '[]', because we're not casting
    protected $attributes = [
        'meta' => [],
        'status' => self::STATUS_ACTIVE,
    ];

    // each event has several ticket types — Laravel infers the event_id key
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
    //only events currently selling
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    //centralised so the rule lives in one place
    public function isOnSale(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
    //has the event date already passed?
    // isPast() comes from Carbon, available because of the 'date' cast
    public function hasPassed(): bool
    {
        return $this->date->isPast();
}
}