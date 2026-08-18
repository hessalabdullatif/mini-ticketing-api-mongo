<x-mail::message>
# Your tickets are confirmed

Hi {{ $order->user->name }},

Your order for **{{ $order->event_name }}** is confirmed.

<x-mail::panel>
**Event:** {{ $order->event_name }}
**Date:** {{ $order->event_date->format('d M Y') }}
**Ticket:** {{ $order->ticket_type }}
**Quantity:** {{ $order->quantity }}
**Total:** {{ number_format($order->total, 2) }} SAR
</x-mail::panel>

Order reference: `{{ $order->id }}`

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>