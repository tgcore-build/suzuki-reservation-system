<p>{{ $reservation->booked_name ?? $reservation->customer?->name }} 様</p>

<p>ご予約の日時を、下記のとおり変更しました。</p>

<p>
変更前：{{ $before->format('Y年n月j日 H:i') }}<br>
変更後：{{ $reservation->scheduled_at->format('Y年n月j日 H:i') }}<br>
メニュー：{{ $reservation->menu?->name ?? '—' }}<br>
所要時間：約{{ $reservation->minutes() }}分
</p>

<p>お心当たりがない場合や、ご都合が合わない場合は、お手数ですがご連絡ください。</p>

@include('emails._contact')

<p>{{ config('app.name') }}</p>
