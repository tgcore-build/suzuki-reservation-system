<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">顧客一覧</h2>
            <a href="{{ route('customers.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white tracking-widest hover:bg-gray-700">新規登録</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 text-sm text-green-600">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow sm:rounded-lg p-6">
                <form method="GET" action="{{ route('customers.index') }}" class="mb-6 flex gap-2">
                    <x-text-input name="q" type="text" class="block w-full" placeholder="氏名・電話番号・カルテ番号で検索" :value="$keyword" />
                    <x-secondary-button type="submit">検索</x-secondary-button>
                </form>

                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b">
                            <th class="py-2 pr-4">カルテ番号</th>
                            <th class="py-2 pr-4">氏名</th>
                            <th class="py-2 pr-4">電話番号</th>
                            <th class="py-2 pr-4">メール</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr class="border-b">
                                <td class="py-2 pr-4">{{ $customer->karte_number ?: '—' }}</td>
                                <td class="py-2 pr-4">
                                    <a href="{{ route('customers.show', $customer) }}" class="text-indigo-600 hover:underline">{{ $customer->name }}</a>
                                </td>
                                <td class="py-2 pr-4">{{ $customer->phone }}</td>
                                <td class="py-2 pr-4">{{ $customer->email ?: '—' }}</td>
                                <td class="py-2 text-right">
                                    <a href="{{ route('customers.edit', $customer) }}" class="text-gray-600 hover:underline">編集</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-gray-500">該当する顧客がいません。</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">{{ $customers->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
