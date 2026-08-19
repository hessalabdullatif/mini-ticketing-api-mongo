<?php

namespace App\Exceptions;

use App\Models\Event;
use Exception;
use Illuminate\Http\JsonResponse;

class EventHasPassedException extends Exception
{
    public function __construct(
        private readonly Event $event,
    ) {
        parent::__construct(
            "This event took place on {$event->date->format('d M Y')} and is no longer on sale."
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