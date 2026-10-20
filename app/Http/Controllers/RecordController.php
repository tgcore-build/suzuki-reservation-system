<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Record;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class RecordController extends Controller
{
    public function create(Customer $customer): View
    {
        // 今日までの予約で、事前カウンセリングが入力されていれば、最初から入れておく
        $counseled = $customer->reservations()->with('menu')
            ->whereNotNull('counseled_at')
            ->where('status', '!=', 'cancelled')
            ->where('scheduled_at', '<=', today()->endOfDay())
            ->orderByDesc('scheduled_at')
            ->first();

        return view('records.create', [
            'customer' => $customer,
            'record' => new Record([
                'performed_on' => today(),
                'menu' => $counseled?->menu?->name,
                'concerns' => $counseled?->concerns,
                'concern_note' => $counseled?->concern_note,
            ]),
            'counseled' => $counseled,
        ]);
    }

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $data = $this->validated($request);
        $data = $this->storePhotos($request, $data);

        $customer->records()->create($data);

        return redirect()->route('customers.show', $customer)->with('status', '対応記録を登録しました。');
    }

    public function edit(Record $record): View
    {
        return view('records.edit', ['record' => $record, 'customer' => $record->customer]);
    }

    public function update(Request $request, Record $record): RedirectResponse
    {
        $data = $this->validated($request);

        foreach (['photo_before', 'photo_after'] as $field) {
            if ($request->hasFile($field) && $record->$field) {
                Storage::disk(config('salon.photo_disk'))->delete($record->$field);
            }
        }
        $data = $this->storePhotos($request, $data);

        $record->update($data);

        return redirect()->route('customers.show', $record->customer_id)->with('status', '対応記録を更新しました。');
    }

    public function destroy(Record $record): RedirectResponse
    {
        foreach (['photo_before', 'photo_after'] as $field) {
            if ($record->$field) {
                Storage::disk(config('salon.photo_disk'))->delete($record->$field);
            }
        }
        $customerId = $record->customer_id;
        $record->delete();

        return redirect()->route('customers.show', $customerId)->with('status', '対応記録を削除しました。');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'performed_on' => ['required', 'date'],
            'menu' => ['required', 'string', 'max:100'],
            'concerns' => ['nullable', 'array'],
            'concerns.*' => ['string', Rule::in(array_keys(config('salon.concerns')))],
            'concern_note' => ['nullable', 'string', 'max:1000'],
            'materials' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'memo' => ['nullable', 'string'],
            'photo_before' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'photo_after' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [], [
            'performed_on' => '施術日',
            'menu' => 'メニュー',
            'concerns' => 'お悩み',
            'concerns.*' => 'お悩み',
            'concern_note' => 'お悩み・ご要望',
            'materials' => '使用薬剤',
            'duration_minutes' => '所要時間',
            'memo' => 'メモ',
            'photo_before' => '施術前の写真',
            'photo_after' => '施術後の写真',
        ]);

        // チェックを全部外したときは、お悩みを空にする（チェックボックスは、何も選ばないと送られないため）
        $data['concerns'] = array_values($data['concerns'] ?? []);

        return $data;
    }

    private function storePhotos(Request $request, array $data): array
    {
        foreach (['photo_before', 'photo_after'] as $field) {
            if ($request->hasFile($field)) {
                $data[$field] = $request->file($field)->store('records', config('salon.photo_disk'));
            } else {
                unset($data[$field]);
            }
        }

        return $data;
    }
}
