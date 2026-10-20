<p>{{ $name }} 様</p>

<p>ご利用履歴を表示するための確認コードです。</p>

<p style="font-size: 20px; font-weight: bold; letter-spacing: 4px;">{{ $code }}</p>

<p>このコードの有効期間は{{ $minutes }}分です。<br>
お心当たりがない場合は、このメールを破棄してください。コードは、ほかの人に教えないでください。</p>

<p>{{ config('app.name') }}</p>
