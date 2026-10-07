<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">顧客情報の編集</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <form method="POST" action="{{ route('customers.update', $customer) }}">
                    @csrf
                    @method('PUT')
                    @include('customers._form', ['customer' => $customer])
                    <div class="mt-6 flex items-center gap-4">
                        <x-primary-button>更新する</x-primary-button>
                        <a href="{{ route('customers.show', $customer) }}" class="text-sm text-gray-600 underline">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
