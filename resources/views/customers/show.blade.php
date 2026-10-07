<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">顧客詳細</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 text-sm text-green-600">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow sm:rounded-lg p-6">
                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="py-3 grid grid-cols-3 gap-4"><dt class="text-gray-500">カルテ番号</dt><dd class="col-span-2">{{ $customer->karte_number ?: '—' }}</dd></div>
                    <div class="py-3 grid grid-cols-3 gap-4"><dt class="text-gray-500">氏名</dt><dd class="col-span-2">{{ $customer->name }}</dd></div>
                    <div class="py-3 grid grid-cols-3 gap-4"><dt class="text-gray-500">電話番号</dt><dd class="col-span-2">{{ $customer->phone }}</dd></div>
                    <div class="py-3 grid grid-cols-3 gap-4"><dt class="text-gray-500">メールアドレス</dt><dd class="col-span-2">{{ $customer->email ?: '—' }}</dd></div>
                    <div class="py-3 grid grid-cols-3 gap-4"><dt class="text-gray-500">住所</dt><dd class="col-span-2">{{ $customer->address ?: '—' }}</dd></div>
                    <div class="py-3 grid grid-cols-3 gap-4"><dt class="text-gray-500">生年月日</dt><dd class="col-span-2">{{ $customer->birthday?->format('Y年n月j日') ?? '—' }}</dd></div>
                    <div class="py-3 grid grid-cols-3 gap-4"><dt class="text-gray-500">性別</dt><dd class="col-span-2">{{ $customer->gender ?: '—' }}</dd></div>
                    <div class="py-3 grid grid-cols-3 gap-4"><dt class="text-gray-500">備考</dt><dd class="col-span-2 whitespace-pre-wrap">{{ $customer->memo ?: '—' }}</dd></div>
                </dl>

                <div class="mt-6 flex items-center gap-4">
                    <a href="{{ route('customers.edit', $customer) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white tracking-widest hover:bg-gray-700">編集</a>
                    <a href="{{ route('customers.index') }}" class="text-sm text-gray-600 underline">一覧へ戻る</a>

                    <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="ml-auto" onsubmit="return confirm('この顧客を削除します。紐づく予約も削除されます。よろしいですか？')">
                        @csrf
                        @method('DELETE')
                        <x-danger-button>削除</x-danger-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
