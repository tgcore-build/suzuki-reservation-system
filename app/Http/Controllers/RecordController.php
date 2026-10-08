<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Record;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class RecordController extends Controller
{
    public function create(Customer $customer): View
    {
        return view('records.create', [
            'customer' => $customer,
            'record' => new Record(['performed_on' => today()]),
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
                Storage::disk('public')->delete($record->$field);
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
                Storage::disk('public')->delete($record->$field);
            }
        }
        $customerId = $record->customer_id;
        $record->delete();

        return redirect()->route('customers.show', $customerId)->with('status', '対応記録を削除しました。');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'performed_on' => ['required', 'date'],
            'menu' => ['required', 'string', 'max:100'],
            'materials' => ['nullable', 'string'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'memo' => ['nullable', 'string'],
            'photo_before' => ['nullable', 'image', 'max:5120'],
            'photo_after' => ['nullable', 'image', 'max:5120'],
        ], [], [
            'performed_on' => '施術日',
            'menu' => 'メニュー',
            'materials' => '使用薬剤',
            'duration_minutes' => '所要時間',
            'memo' => 'メモ',
            'photo_before' => '施術前の写真',
            'photo_after' => '施術後の写真',
        ]);
    }

    private function storePhotos(Request $request, array $data): array
    {
        foreach (['photo_before', 'photo_after'] as $field) {
            if ($request->hasFile($field)) {
                $data[$field] = $request->file($field)->store('records', 'public');
            } else {
                unset($data[$field]);
            }
        }

        return $data;
    }
}
