<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">顧客詳細</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-3 bg-green-100 text-green-800 rounded-sm">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-xs sm:rounded-lg p-6">
                <dl class="text-sm divide-y">
                    <div class="flex py-3"><dt class="w-40 text-gray-500">カルテ番号</dt><dd>{{ $customer->karte_number ?? '—' }}</dd></div>
                    <div class="flex py-3"><dt class="w-40 text-gray-500">氏名</dt><dd>{{ $customer->name }}</dd></div>
                    <div class="flex py-3"><dt class="w-40 text-gray-500">電話番号</dt><dd>{{ $customer->phone ?? '—' }}</dd></div>
                    <div class="flex py-3"><dt class="w-40 text-gray-500">メールアドレス</dt><dd>{{ $customer->email ?? '—' }}</dd></div>
                    @if ($customer->email2)
                        <div class="flex py-3"><dt class="w-40 text-gray-500">メールアドレス（2つ目）</dt><dd>{{ $customer->email2 }}</dd></div>
                    @endif
                    <div class="flex py-3"><dt class="w-40 text-gray-500">LINE 連携</dt><dd>{{ $customer->line_user_id ? '連携済み（' . $customer->line_linked_at?->format('Y/m/d') . '）・リマインドは LINE' : '未連携・リマインドはメール' }}</dd></div>
                    <div class="flex py-3"><dt class="w-40 text-gray-500">住所</dt><dd>{{ $customer->address ?? '—' }}</dd></div>
                    <div class="flex py-3"><dt class="w-40 text-gray-500">生年月日</dt><dd>{{ $customer->birthday?->format('Y/m/d') ?? '—' }}</dd></div>
                    <div class="flex py-3"><dt class="w-40 text-gray-500">性別</dt><dd>{{ $customer->gender ?? '—' }}</dd></div>
                    <div class="flex py-3"><dt class="w-40 text-gray-500">備考</dt><dd>{{ $customer->memo ?? '—' }}</dd></div>
                </dl>

                <div class="mt-6 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <a href="{{ route('customers.edit', $customer) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">編集</a>
                        <a href="{{ route('customers.index') }}" class="text-sm text-gray-600 underline">一覧へ戻る</a>
                    </div>
                    @php $blockers = $customer->deletionBlockers(); @endphp
                    @if ($blockers)
                        <div class="text-right">
                            <button type="button" disabled class="inline-flex items-center px-4 py-2 bg-gray-300 rounded-md font-semibold text-xs text-white uppercase tracking-widest cursor-not-allowed">削除</button>
                            <p class="mt-1 text-xs text-gray-500">{{ \App\Http\Controllers\CustomerController::blockerMessage($blockers) }}</p>
                        </div>
                    @else
                        <form method="POST" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('この顧客を削除します。元に戻せません。よろしいですか？');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">削除</button>
                        </form>
                    @endif
                </div>
                @if (session('delete_error'))
                    <div class="mt-4 p-3 bg-red-100 text-red-800 rounded-sm text-sm">{{ session('delete_error') }}</div>
                @endif
            </div>

            <div class="bg-white shadow-xs sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-800">対応記録</h3>
                    <a href="{{ route('records.create', $customer) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">対応記録を追加</a>
                </div>
                @forelse ($customer->records()->orderByDesc('performed_on')->get() as $record)
                    <div class="border-t py-4 text-sm">
                        <div class="flex items-center justify-between">
                            <div class="font-semibold">{{ $record->performed_on->format('Y/m/d') }}　{{ $record->menu }}
                                @if ($record->duration_minutes)<span class="text-gray-500 font-normal">（{{ $record->duration_minutes }}分）</span>@endif
                            </div>
                            <div class="flex items-center gap-3">
                                <a href="{{ route('records.edit', $record) }}" class="text-gray-600 hover:underline">編集</a>
                                <form method="POST" action="{{ route('records.destroy', $record) }}" onsubmit="return confirm('この記録を削除します。よろしいですか？');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">削除</button>
                                </form>
                            </div>
                        </div>
                        @if ($record->concernLabels() || $record->concern_note)
                            <div class="mt-2 text-gray-700">
                                お悩み：{{ implode('、', $record->concernLabels()) }}
                                @if ($record->concern_note)<div class="whitespace-pre-line">{{ $record->concern_note }}</div>@endif
                            </div>
                        @endif
                        @if ($record->materials)<div class="mt-2 text-gray-700 whitespace-pre-line">薬剤：{{ $record->materials }}</div>@endif
                        @if ($record->memo)<div class="mt-1 text-gray-700 whitespace-pre-line">{{ $record->memo }}</div>@endif
                        @if ($record->photo_before || $record->photo_after)
                            <div class="mt-3 flex gap-3">
                                @if ($record->photo_before)<div><div class="text-xs text-gray-500">施術前</div><img src="{{ $record->photoUrl('photo_before') }}" class="w-32 rounded-sm"></div>@endif
                                @if ($record->photo_after)<div><div class="text-xs text-gray-500">施術後</div><img src="{{ $record->photoUrl('photo_after') }}" class="w-32 rounded-sm"></div>@endif
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500">対応記録はまだありません</p>
                @endforelse
            </div>

            <div class="bg-white shadow-xs sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">AI提案結果</h3>
                <table class="w-full text-sm text-left">
                    <thead class="text-gray-500">
                        <tr><th class="py-2">日時</th><th class="py-2">提案メニュー</th><th class="py-2"></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($customer->aiSessions()->with('resultMenu')->orderByDesc('created_at')->get() as $s)
                            <tr class="border-t">
                                <td class="py-2">{{ $s->created_at->format('Y/m/d H:i') }}</td>
                                <td class="py-2">{{ $s->resultMenuName() ?? '—' }}</td>
                                <td class="py-2 text-right"><a href="{{ route('ai-sessions.show', $s) }}" class="text-indigo-600 underline">会話を見る</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-4 text-center text-gray-500">AI提案結果はありません</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-white shadow-xs sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">予約履歴</h3>
                <table class="w-full text-sm text-left">
                    <thead class="text-gray-500">
                        <tr><th class="py-2">日時</th><th class="py-2">メニュー</th><th class="py-2">状態</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($customer->reservations()->with('menu')->orderByDesc('scheduled_at')->get() as $r)
                            <tr class="border-t">
                                <td class="py-2">{{ $r->scheduled_at->format('Y/m/d H:i') }}</td>
                                <td class="py-2">{{ $r->menu?->name ?? '—' }}
                                    @if ($r->counseled_at)
                                        <div class="text-xs text-gray-600">事前カウンセリング：{{ implode('、', $r->concernLabels()) }}@if ($r->concern_note)<span class="whitespace-pre-line">　{{ $r->concern_note }}</span>@endif</div>
                                    @endif
                                </td>
                                <td class="py-2">{{ \App\Models\Reservation::STATUSES[$r->status] ?? $r->status }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-4 text-center text-gray-500">予約履歴はありません</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
