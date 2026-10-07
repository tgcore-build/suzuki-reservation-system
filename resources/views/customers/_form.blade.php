@php($c = $customer ?? null)

<div class="space-y-6">
    <div>
        <x-input-label for="name" value="氏名（必須）" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $c?->name)" required autofocus />
        <x-input-error class="mt-2" :messages="$errors->get('name')" />
    </div>

    <div>
        <x-input-label for="phone" value="電話番号（必須）" />
        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $c?->phone)" required />
        <x-input-error class="mt-2" :messages="$errors->get('phone')" />
    </div>

    <div>
        <x-input-label for="email" value="メールアドレス" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $c?->email)" />
        <x-input-error class="mt-2" :messages="$errors->get('email')" />
    </div>

    <div>
        <x-input-label for="address" value="住所" />
        <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" :value="old('address', $c?->address)" />
        <x-input-error class="mt-2" :messages="$errors->get('address')" />
    </div>

    <div>
        <x-input-label for="birthday" value="生年月日" />
        <x-text-input id="birthday" name="birthday" type="date" class="mt-1 block w-full" :value="old('birthday', $c?->birthday?->format('Y-m-d'))" />
        <p class="mt-1 text-xs text-gray-500">入力すると、カルテ番号（誕生日の月日4桁）が自動で付きます。</p>
        <x-input-error class="mt-2" :messages="$errors->get('birthday')" />
    </div>

    <div>
        <x-input-label for="gender" value="性別" />
        <select id="gender" name="gender" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">未選択</option>
            @foreach (['女性', '男性', 'その他'] as $g)
                <option value="{{ $g }}" @selected(old('gender', $c?->gender) === $g)>{{ $g }}</option>
            @endforeach
        </select>
        <x-input-error class="mt-2" :messages="$errors->get('gender')" />
    </div>

    <div>
        <x-input-label for="memo" value="備考（アレルギー・好みなど）" />
        <textarea id="memo" name="memo" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('memo', $c?->memo) }}</textarea>
        <x-input-error class="mt-2" :messages="$errors->get('memo')" />
    </div>
</div>
