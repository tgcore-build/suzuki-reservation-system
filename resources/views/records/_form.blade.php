<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-5">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @if ($errors->any())
        <div class="p-3 bg-red-100 text-red-800 rounded text-sm">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div>
        <label class="block text-sm text-gray-700 mb-1">施術日</label>
        <input type="date" name="performed_on" value="{{ old('performed_on', $record->performed_on?->format('Y-m-d')) }}" class="border-gray-300 rounded-md shadow-sm text-sm">
    </div>

    <div>
        <label class="block text-sm text-gray-700 mb-1">メニュー</label>
        <input type="text" name="menu" value="{{ old('menu', $record->menu) }}" class="w-full border-gray-300 rounded-md shadow-sm text-sm">
    </div>

    <div>
        <label class="block text-sm text-gray-700 mb-1">使用薬剤・配合</label>
        <textarea name="materials" rows="3" class="w-full border-gray-300 rounded-md shadow-sm text-sm">{{ old('materials', $record->materials) }}</textarea>
    </div>

    <div>
        <label class="block text-sm text-gray-700 mb-1">所要時間（分）</label>
        <input type="number" name="duration_minutes" value="{{ old('duration_minutes', $record->duration_minutes) }}" class="w-32 border-gray-300 rounded-md shadow-sm text-sm">
    </div>

    <div>
        <label class="block text-sm text-gray-700 mb-1">メモ（髪の状態・次回への申し送り）</label>
        <textarea name="memo" rows="4" class="w-full border-gray-300 rounded-md shadow-sm text-sm">{{ old('memo', $record->memo) }}</textarea>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm text-gray-700 mb-1">施術前の写真</label>
            @if ($record->photo_before)
                <img src="{{ $record->photoUrl('photo_before') }}" class="w-40 rounded mb-2">
            @endif
            <input type="file" name="photo_before" accept="image/*" class="text-sm">
        </div>
        <div>
            <label class="block text-sm text-gray-700 mb-1">施術後の写真</label>
            @if ($record->photo_after)
                <img src="{{ $record->photoUrl('photo_after') }}" class="w-40 rounded mb-2">
            @endif
            <input type="file" name="photo_after" accept="image/*" class="text-sm">
        </div>
    </div>

    <div class="flex items-center gap-4">
        <button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">保存する</button>
        <a href="{{ route('customers.show', $customer) }}" class="text-sm text-gray-600 underline">戻る</a>
    </div>
</form>
