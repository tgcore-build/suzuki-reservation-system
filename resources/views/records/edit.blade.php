<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">対応記録の編集（{{ $customer->name }}）</h2>
    </x-slot>
    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                @include('records._form', ['action' => route('records.update', $record), 'method' => 'PUT'])
            </div>
        </div>
    </div>
</x-app-layout>
