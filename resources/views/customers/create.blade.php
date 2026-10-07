<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">顧客登録</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <form method="POST" action="{{ route('customers.store') }}">
                    @csrf
                    @include('customers._form')
                    <div class="mt-6 flex items-center gap-4">
                        <x-primary-button>登録する</x-primary-button>
                        <a href="{{ route('customers.index') }}" class="text-sm text-gray-600 underline">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
