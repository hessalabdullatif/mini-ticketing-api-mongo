<?php

namespace App\Exceptions;

use App\Models\Event;
use Exception;
use Illuminate\Http\JsonResponse;

class EventNotOnSaleException extends Exception
{
    public function __construct(
        private readonly Event $event,
    ) {
        parent::__construct(
            $event->status === Event::STATUS_CANCELLED
                ? 'This event has been cancelled.'
                : 'Ticket sales for this event are currently paused.'
        );
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors'  => [
                'event_id' => [$this->getMessage()],
            ],
        ], 422);
    }
}