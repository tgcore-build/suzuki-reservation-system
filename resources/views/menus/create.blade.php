<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">メニュー登録</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('menus.store') }}">
                    @include('menus._form')
                    <div class="mt-6 flex items-center gap-4">
                        <x-primary-button>登録する</x-primary-button>
                        <a href="{{ route('menus.index') }}" class="text-sm text-gray-600 underline">キャンセル</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
