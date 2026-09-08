<x-mail::message>
# Bedankt voor je testbestelling

Bestelling {{ $order->number }} is bevestigd met een gesimuleerde betaling. Er is geen geld afgeschreven.

Totaal: € {{ number_format($order->total_cents / 100, 2, ',', '.') }}.

@if($order->user_id)
<x-mail::button :url="route('account.orders.show', $order)">Bekijk bestelling</x-mail::button>
@endif
</x-mail::message>
