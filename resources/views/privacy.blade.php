@extends('booking.layout')

@section('title', 'プライバシーポリシー')

@section('content')
    @php $contact = config('salon.contact'); @endphp
    <div class="bg-white shadow-xs rounded-lg p-6 space-y-6 text-sm leading-relaxed">
        <p>{{ config('app.name') }}（以下「当店」）は、お客様の個人情報を、次のとおり取り扱います。</p>

        <section>
            <h2 class="font-semibold text-base mb-2">1. お預かりする情報</h2>
            <ul class="list-disc pl-5 space-y-1">
                <li>お名前、電話番号、メールアドレス</li>
                <li>生年月日（任意。カルテ番号と、かんたんログインに使います）</li>
                <li>髪や頭皮のお悩み・ご要望（事前カウンセリング、AI相談、ご来店時のカウンセリング）</li>
                <li>ご予約・ご来店の記録、施術の内容、施術前後の写真</li>
                <li>LINE と連携した場合の、LINE のユーザーID（お名前やトークの内容は受け取りません）</li>
                <li>不正な利用を防ぐための、アクセスの記録（IPアドレス、日時など）</li>
            </ul>
        </section>

        <section>
            <h2 class="font-semibold text-base mb-2">2. 利用目的</h2>
            <ul class="list-disc pl-5 space-y-1">
                <li>ご予約の受付、確認のご連絡、前日のリマインド</li>
                <li>ご本人の確認（ログイン、確認コード）</li>
                <li>メニューのご提案、施術の準備と記録、次回以降のご案内</li>
                <li>お問い合わせへの対応</li>
                <li>不正な利用の防止</li>
            </ul>
            <p class="mt-2">これ以外の目的には使いません。</p>
        </section>

        <section>
            <h2 class="font-semibold text-base mb-2">3. 外部のサービスへの送信</h2>
            <ul class="list-disc pl-5 space-y-1">
                <li><span class="font-semibold">AI相談：</span>ご相談の内容は、メニューのご提案のため、Google の生成AIサービス（Gemini）に送信されます。利用条件によっては、送信した内容が Google のサービスの改善に使われることがあります。お名前・電話番号など、個人が特定できる情報は書かないでください。</li>
                <li><span class="font-semibold">メール・LINE：</span>確認メール・リマインド・確認コードの送信のため、メール配信サービス、および（連携した場合）LINE を使います。</li>
                <li><span class="font-semibold">保管：</span>情報は、当店が契約するクラウドのサーバー・データベース・ファイル置き場に保管します。</li>
            </ul>
            <p class="mt-2">法令に基づく場合を除き、ご本人の同意なく、個人情報を第三者に提供することはありません。</p>
        </section>

        <section>
            <h2 class="font-semibold text-base mb-2">4. 保存期間</h2>
            <ul class="list-disc pl-5 space-y-1">
                <li>お客様の情報、ご予約・ご来店の記録、施術写真は、ご利用が続くあいだ保存し、削除のご依頼があれば削除します。</li>
                <li>AI相談の内容は、メニューのご提案と改善のために保存し、削除のご依頼があれば削除します。</li>
                <li>確認コードは10分、メールアドレス確認前のご予約の入力内容は30分で使えなくなります。</li>
            </ul>
        </section>

        <section>
            <h2 class="font-semibold text-base mb-2">5. 開示・訂正・削除のご依頼</h2>
            <p>ご自身の情報の確認・訂正・削除、利用の停止をご希望の場合は、下記のお問い合わせ先までご連絡ください。ご本人であることを確認したうえで対応します。</p>
        </section>

        <section>
            <h2 class="font-semibold text-base mb-2">6. お問い合わせ先</h2>
            @if ($contact)
                <p class="whitespace-pre-line">{{ $contact }}</p>
            @else
                <p>ご来店時、またはご予約の確認メールの差出人あてに、お問い合わせください。</p>
            @endif
        </section>

        <p class="text-gray-500">{{ \Illuminate\Support\Carbon::parse(config('salon.privacy_updated_at'))->format('Y年n月j日') }} 制定</p>
    </div>
@endsection
