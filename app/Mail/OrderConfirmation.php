<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    // the order arrives through the constructor and becomes available in the view
    public function __construct(
        public readonly Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your tickets for {$this->order->event_name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.orders.confirmation',
        );
    }
}