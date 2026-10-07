@php
    $r = $reservation ?? null;
    $at = old('scheduled_at', $r?->scheduled_at?->format('Y-m-d H:i'));
    $atDate = $at ? substr($at, 0, 10) : '';
    $atTime = $at ? substr($at, 11, 5) : '';
@endphp
@csrf

<div>
    <x-input-label for="customer_id" value="顧客（必須）" />
    <select id="customer_id" name="customer_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
        <option value="">選択してください</option>
        @foreach ($customers as $c)
            <option value="{{ $c->id }}" @selected(old('customer_id', $r?->customer_id ?? request('customer_id')) == $c->id)>
                {{ $c->name }}（{{ $c->phone }}）
            </option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('customer_id')" class="mt-2" />
</div>

<div class="mt-4">
    <x-input-label for="menu_id" value="メニュー（必須）" />
    <select id="menu_id" name="menu_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
        <option value="">選択してください</option>
        @foreach ($menus as $m)
            <option value="{{ $m->id }}" @selected(old('menu_id', $r?->menu_id) == $m->id)>
                {{ $m->name }}（{{ number_format($m->price) }}円・{{ $m->duration_minutes }}分）
            </option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('menu_id')" class="mt-2" />
</div>

<div class="mt-4">
    @php
        $atHour = $atTime ? substr($atTime, 0, 2) : '';
        $atMin = $atTime ? substr($atTime, 3, 2) : '';
    @endphp
    <x-input-label for="res_date" value="予約日時（必須）" />
    <div class="mt-1 flex items-center gap-2">
        <input id="res_date" type="date" class="border-gray-300 rounded-md shadow-sm" value="{{ $atDate }}" required>
        <select id="res_hour" class="border-gray-300 rounded-md shadow-sm" required>
            <option value="">--</option>
            @for ($h = 6; $h <= 22; $h++)
                <option value="{{ sprintf('%02d', $h) }}" @selected($atHour === sprintf('%02d', $h))>{{ $h }}</option>
            @endfor
        </select>
        <span class="text-sm text-gray-600">時</span>
        <select id="res_min" class="border-gray-300 rounded-md shadow-sm" required>
            <option value="">--</option>
            @foreach (['00', '15', '30', '45'] as $mm)
                <option value="{{ $mm }}" @selected($atMin === $mm)>{{ $mm }}</option>
            @endforeach
        </select>
        <span class="text-sm text-gray-600">分</span>
    </div>
    <input type="hidden" id="scheduled_at" name="scheduled_at" value="{{ $at }}">
    <x-input-error :messages="$errors->get('scheduled_at')" class="mt-2" />
    <script>
        (function () {
            const d = document.getElementById('res_date');
            const hr = document.getElementById('res_hour');
            const mn = document.getElementById('res_min');
            const h = document.getElementById('scheduled_at');
            const sync = () => { h.value = (d.value && hr.value && mn.value) ? d.value + ' ' + hr.value + ':' + mn.value : ''; };
            [d, hr, mn].forEach((el) => { el.addEventListener('input', sync); el.addEventListener('change', sync); });
            sync();
        })();
    </script>
</div>


<div class="mt-4">
    <x-input-label for="notes" value="メモ" />
    <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">{{ old('notes', $r?->notes) }}</textarea>
    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
</div>

@if ($r)
    <div class="mt-4">
        <x-input-label for="status" value="状態" />
        <select id="status" name="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            @foreach (\App\Models\Reservation::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $r->status) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('status')" class="mt-2" />
    </div>
@endif
