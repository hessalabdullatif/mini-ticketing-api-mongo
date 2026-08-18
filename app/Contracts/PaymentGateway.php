<?php

namespace App\Contracts;

// a contract, not a class — it declares WHAT can be done, never HOW
// any class implementing this promises to have a charge() method with this exact shape
interface PaymentGateway
{
    // returns a reference for the charge (a transaction id from the provider)
    // throws PaymentFailedException if the charge doesn't go through
    public function charge(float $amount, array $meta = []): string;

    // used for logging and for storing which provider handled an order
    public function name(): string;
}