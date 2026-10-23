<p>{{ $name }} 様</p>

<p>ご予約の手続きで、メールアドレスを確認するためのコードです。予約の画面に入力してください。</p>

<p style="font-size: 20px; font-weight: bold; letter-spacing: 4px;">{{ $code }}</p>

<p>このコードの有効期間は{{ $minutes }}分です。コードを入力すると、ご予約が確定します。<br>
お心当たりがない場合は、このメールを破棄してください（ご予約は確定しません）。</p>

<p>{{ config('app.name') }}</p>
