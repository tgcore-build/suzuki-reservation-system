@php $m = $menu ?? null; @endphp
@csrf

<div>
    <x-input-label for="name" value="メニュー名（必須）" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $m?->name)" required />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="price" value="料金（円・必須）" />
    <x-text-input id="price" name="price" type="number" min="0" class="mt-1 block w-full" :value="old('price', $m?->price)" required />
    <x-input-error :messages="$errors->get('price')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="duration_minutes" value="所要時間（分・必須）" />
    <x-text-input id="duration_minutes" name="duration_minutes" type="number" min="5" step="5" class="mt-1 block w-full" :value="old('duration_minutes', $m?->duration_minutes)" required />
    <x-input-error :messages="$errors->get('duration_minutes')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="description" value="説明" />
    <textarea id="description" name="description" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('description', $m?->description) }}</textarea>
    <x-input-error :messages="$errors->get('description')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="sort_order" value="表示順（小さいほど上）" />
    <x-text-input id="sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full" :value="old('sort_order', $m?->sort_order ?? 0)" />
    <x-input-error :messages="$errors->get('sort_order')" class="mt-2" />
</div>

<div class="mt-4">
    <label class="inline-flex items-center">
        <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300" @checked(old() ? old('is_active') : ($m?->is_active ?? true))>
        <span class="ms-2 text-sm text-gray-600">お客様に公開する（オフにすると予約画面に出ません）</span>
    </label>
</div>
