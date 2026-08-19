<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class Order extends Model
{
    // status constants — avoids typos and centralises the allowed values
    const STATUS_PENDING = 'pending';
    const STATUS_PAID    = 'paid';
     const STATUS_REFUNDED = 'refunded'; 

    protected $connection = 'mongodb';
    protected $table      = 'orders';

    protected $fillable = [
        'user_id',
        'event_id',
        'ticket_id',
        'refunded_at',       
        'refund_reason', 

        // frozen copies — the receipt must show what was true at purchase time
        'event_name',
        'event_date',
        'ticket_type',
        'unit_price',

        'quantity',
        'total',
        'status',
        'paid_at',

          'payment_gateway',      
        'payment_reference',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'unit_price' => 'float',
        'total'      => 'float',
        'event_date' => 'date',
        'paid_at'    => 'datetime',
        'refunded_at' => 'datetime',
    ];

    // a new order always starts unpaid
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    // "my orders" — the most-called query in the whole API
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    // convenience check instead of comparing strings at call sites
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
    // an order can only be refunded once, and only if it was paid
    public function isRefundable(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}