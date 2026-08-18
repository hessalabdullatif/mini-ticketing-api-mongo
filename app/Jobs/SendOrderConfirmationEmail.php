<?php

namespace App\Jobs;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// ShouldQueue is the whole trick — without it this runs inline and blocks the request
class SendOrderConfirmationEmail implements ShouldQueue
{
    use Queueable;

    // retry up to three times before giving up
    public int $tries = 3;

    // wait 10s, then 30s, then 60s between attempts
    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly Order $order,
    ) {}

    public function handle(): void
    {
        $user = $this->order->user;

        // guard: the user may have been deleted between queueing and running
        if (! $user) {
            Log::warning('Skipping confirmation email, user not found', [
                'order_id' => $this->order->id,
            ]);
            return;
        }

        Mail::to($user->email)->send(new OrderConfirmation($this->order));

        Log::info('Order confirmation email sent', [
            'order_id' => $this->order->id,
            // the email address itself isn't logged — it's personal data
        ]);
    }

    // called when all retries are exhausted
    public function failed(\Throwable $exception): void
    {
        Log::error('Order confirmation email failed permanently', [
            'order_id' => $this->order->id,
            'error'    => $exception->getMessage(),
        ]);
    }
}