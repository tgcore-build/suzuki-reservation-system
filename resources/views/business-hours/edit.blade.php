<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">営業時間の設定</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('status') }}</div>
            @endif

            @foreach ($errors->all() as $message)
                <div class="mb-2 p-3 bg-red-100 text-red-800 rounded text-sm">{{ $message }}</div>
            @endforeach

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('business-hours.update') }}">
                    @csrf
                    @method('PUT')

                    <table class="w-full text-sm text-left">
                        <thead class="text-gray-500 border-b">
                            <tr>
                                <th class="py-2">曜日</th>
                                <th class="py-2">営業</th>
                                <th class="py-2">開店</th>
                                <th class="py-2">閉店</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($hours as $h)
                                @php $d = $h->weekday; @endphp
                                <tr class="border-b">
                                    <td class="py-2 font-semibold">{{ \App\Models\BusinessHour::WEEKDAYS[$d] }}曜日</td>
                                    <td class="py-2">
                                        <input type="checkbox" name="hours[{{ $d }}][is_open]" value="1" class="rounded border-gray-300" @checked(old() ? old("hours.$d.is_open") : $h->is_open)>
                                    </td>
                                    <td class="py-2">
                                        <input type="time" name="hours[{{ $d }}][open_time]" value="{{ old("hours.$d.open_time", $h->open_time ? substr($h->open_time, 0, 5) : '') }}" class="border-gray-300 rounded-md shadow-sm text-sm">
                                    </td>
                                    <td class="py-2">
                                        <input type="time" name="hours[{{ $d }}][close_time]" value="{{ old("hours.$d.close_time", $h->close_time ? substr($h->close_time, 0, 5) : '') }}" class="border-gray-300 rounded-md shadow-sm text-sm">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <p class="mt-3 text-xs text-gray-500">「営業」のチェックを外した曜日は定休日になります。</p>

                    <div class="mt-6">
                        <x-primary-button>保存する</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
