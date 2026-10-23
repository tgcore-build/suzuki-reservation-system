<p>{{ $reservation->booked_name ?? $reservation->customer?->name }} 様</p>

<p>ご予約のお知らせです。</p>

<p>
日時：{{ $reservation->scheduled_at->format('Y年n月j日 H:i') }}<br>
メニュー：{{ $reservation->menu?->name ?? '—' }}<br>
所要時間：約{{ $reservation->minutes() }}分@if ($reservation->price !== null)<br>
料金：{{ number_format($reservation->price) }}円@endif
</p>

@include('emails._contact')

<p>{{ config('app.name') }}</p>
