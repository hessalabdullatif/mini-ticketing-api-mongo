<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;
use MongoDB\Laravel\Relations\HasMany;

class Ticket extends Model
{
    protected $connection = 'mongodb';
    protected $table      = 'tickets';

    protected $fillable = [
        'event_id',
        'event_name',      // denormalized copy — avoids a lookup when listing tickets
        'type',
        'price',
        'quantity_available',
    ];

    // both are numeric — without these, Mongo may store them as strings from JSON input
    protected $casts = [
        'price'              => 'float',
        'quantity_available' => 'integer',
    ];

    // every ticket type belongs to one event
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    // every ticket type can have many orders placed against it
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // only ticket types that still have stock
    public function scopeAvailable($query)
    {
        return $query->where('quantity_available', '>', 0);
    }
}