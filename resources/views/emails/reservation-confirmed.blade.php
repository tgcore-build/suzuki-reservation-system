<p>{{ $reservation->customer->name }} 様</p>

<p>ご予約ありがとうございます。下記の内容で承りました。</p>

<p>
日時：{{ $reservation->scheduled_at->format('Y年n月j日 H:i') }}<br>
メニュー：{{ $reservation->menu?->name ?? '—' }}
</p>

<p>ご都合が悪くなった場合は、お早めにご連絡ください。<br>
ご来店をお待ちしております。</p>

<p>{{ config('app.name') }}</p>
