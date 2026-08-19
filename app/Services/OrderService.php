<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Exceptions\EventHasPassedException;
use App\Exceptions\EventNotOnSaleException;
use App\Exceptions\InsufficientTicketsException;
use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    // the gateway arrives already built — the service never knows which company it is
    public function __construct(
        private readonly PaymentGateway $gateway,
    ) {}

    public function create(User $user, array $data): Order
    {
        $ticket = Ticket::with('event')->findOrFail($data['ticket_id']);

        // refuse to sell for a paused or cancelled event.
        // checked before stock, because "this event is cancelled" is more
        // useful to the buyer than "only 3 tickets left"
        if (! $ticket->event->isOnSale()) {
            throw new EventNotOnSaleException($ticket->event);
        }

        // refuse to sell for an event that already happened.
        // StoreEventRequest has after:today, but that only guards creation.
        // an event created legitimately becomes past simply by time passing
        if ($ticket->event->hasPassed()) {
            throw new EventHasPassedException($ticket->event);
        }

        $quantity = $data['quantity'];

        if ($ticket->quantity_available < $quantity) {
            throw new InsufficientTicketsException($quantity, $ticket->quantity_available);
        }

        $total = $this->calculateTotal($ticket->price, $quantity);

        Log::info('Order attempt', [
            'user_id'   => $user->id,
            'ticket_id' => $ticket->id,
            'quantity'  => $quantity,
            'total'     => $total,
        ]);

        // capture the result instead of returning directly
        $order = DB::connection('mongodb')->transaction(function () use ($user, $ticket, $quantity, $total) {

            $ticket->decrement('quantity_available', $quantity);

            $reference = $this->gateway->charge($total, [
                'user_id'   => $user->id,
                'ticket_id' => $ticket->id,
            ]);

            $event = $ticket->event;

            return Order::create([
                'user_id'   => $user->id,
                'event_id'  => $event->id,
                'ticket_id' => $ticket->id,

                'event_name'  => $event->name,
                'event_date'  => $event->date,
                'ticket_type' => $ticket->type,
                'unit_price'  => $ticket->price,

                'quantity' => $quantity,
                'total'    => $total,

                'status'  => Order::STATUS_PAID,
                'paid_at' => now(),

                'payment_gateway'   => $this->gateway->name(),
                'payment_reference' => $reference,
            ]);
        });

        // dispatched AFTER the transaction commits
        // inside it, a rollback would leave a job referencing an order that no longer exists
        SendOrderConfirmationEmail::dispatch($order);

        return $order;
    }

    // extracted so it can be unit tested without a database
    public function calculateTotal(float $unitPrice, int $quantity): float
    {
        return round($unitPrice * $quantity, 2);
    }
}