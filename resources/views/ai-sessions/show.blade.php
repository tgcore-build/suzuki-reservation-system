<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">AI提案結果の詳細</h2>
            <a href="{{ route('ai-sessions.index') }}" class="text-sm text-indigo-600 underline">一覧に戻る</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <dl class="grid grid-cols-[8rem_1fr] gap-y-2 text-sm">
                    <dt class="text-gray-500">日時</dt>
                    <dd>{{ $aiSession->created_at->format('Y/m/d H:i') }}</dd>
                    <dt class="text-gray-500">提案メニュー</dt>
                    <dd>{{ $aiSession->result_menu ?? '—' }}</dd>
                    <dt class="text-gray-500">状態</dt>
                    <dd>{{ \App\Models\AiSession::STATUSES[$aiSession->status] ?? $aiSession->status }}</dd>
                    <dt class="text-gray-500">予約</dt>
                    <dd>
                        @if ($aiSession->customer)
                            済（<a href="{{ route('customers.show', $aiSession->customer) }}" class="text-indigo-600 underline">{{ $aiSession->customer->name }}</a>）
                        @else
                            未
                        @endif
                    </dd>
                </dl>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-gray-800 mb-4">会話内容</h3>
                <div class="space-y-3">
                    @forelse ($aiSession->messages as $m)
                        <div class="flex {{ $m->role === 'user' ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[85%]">
                                <div class="text-xs text-gray-500 mb-1 {{ $m->role === 'user' ? 'text-right' : '' }}">{{ $m->role === 'user' ? 'お客様' : 'AI' }}</div>
                                <div class="rounded-lg px-3 py-2 text-sm whitespace-pre-line {{ $m->role === 'user' ? 'bg-gray-800 text-white' : 'bg-gray-100' }}">{{ $m->content }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">会話はありません。</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
