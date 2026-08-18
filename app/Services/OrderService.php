<?php

namespace App\Services;

use App\Exceptions\InsufficientTicketsException;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function create(User $user, array $data): Order
    {
        // fetch the ticket — findOrFail gives a 404 if the id is valid-shaped but doesn't exist
        // this is why the Form Request didn't need an exists: rule
        $ticket = Ticket::with('event')->findOrFail($data['ticket_id']);

        $quantity = $data['quantity'];

        // the availability check — throws rather than returning false
        if ($ticket->quantity_available < $quantity) {
            throw new InsufficientTicketsException($quantity, $ticket->quantity_available);
        }

        // the total is computed here, never taken from the client
        $total = $this->calculateTotal($ticket->price, $quantity);

        Log::info('Order attempt', [
            'user_id'   => $user->id,
            'ticket_id' => $ticket->id,
            'quantity'  => $quantity,
            'total'     => $total,
        ]);

        // both writes succeed together or neither happens
        // this is why we configured a replica set on day one
        return DB::connection('mongodb')->transaction(function () use ($user, $ticket, $quantity, $total) {

            // decrement stock atomically — Mongo performs the subtraction itself
            $ticket->decrement('quantity_available', $quantity);

            $event = $ticket->event;

            return Order::create([
                'user_id'   => $user->id,
                'event_id'  => $event->id,
                'ticket_id' => $ticket->id,

                // frozen copies — this order must always show what was true today
                'event_name'  => $event->name,
                'event_date'  => $event->date,
                'ticket_type' => $ticket->type,
                'unit_price'  => $ticket->price,

                'quantity' => $quantity,
                'total'    => $total,
                'status'   => Order::STATUS_PENDING,
            ]);
        });
    }

    // extracted so it can be unit tested without a database
    // step 6 asks for exactly this: "one unit test for your total-calculation method"
    public function calculateTotal(float $unitPrice, int $quantity): float
    {
        return round($unitPrice * $quantity, 2);
    }
}