<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">顧客一覧</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('customer-import.create') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">CSV取り込み</a>
                <a href="{{ route('customers.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">新規登録</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="GET" action="{{ route('customers.index') }}" class="flex gap-2 mb-6">
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="氏名・電話番号・カルテ番号で検索" class="flex-1 border-gray-300 rounded-md shadow-sm text-sm">
                    <button type="submit" class="px-4 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50">検索</button>
                </form>

                <table class="w-full text-sm text-left">
                    <thead class="text-gray-500">
                        <tr>
                            <th class="py-2">カルテ番号</th>
                            <th class="py-2">氏名</th>
                            <th class="py-2">電話番号</th>
                            <th class="py-2">メール</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr class="border-t">
                                <td class="py-2">{{ $customer->karte_number ?? '—' }}</td>
                                <td class="py-2"><a href="{{ route('customers.show', $customer) }}" class="text-indigo-600 hover:underline">{{ $customer->name }}</a></td>
                                <td class="py-2">{{ $customer->phone ?? '—' }}</td>
                                <td class="py-2">{{ $customer->email ?? '—' }}</td>
                                <td class="py-2 text-right"><a href="{{ route('customers.edit', $customer) }}" class="text-gray-600 hover:underline">編集</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-gray-500">顧客が見つかりません</td></tr>
                        @endforelse
                    </tbody>
                </table>

                @if ($customers instanceof \Illuminate\Pagination\AbstractPaginator)
                    <div class="mt-4">{{ $customers->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
