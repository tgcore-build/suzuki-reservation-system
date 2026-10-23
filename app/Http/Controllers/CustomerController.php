<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $keyword = $request->query('q');

        $customers = Customer::query()
            ->when($keyword, function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%")
                        ->orWhere('phone', 'like', "%{$keyword}%")
                        ->when(preg_replace('/\D/', '', $keyword), fn ($q, $digits) => $q->orWhereRaw("REPLACE(phone, '-', '') like ?", ["%{$digits}%"]))
                        ->orWhere('karte_number', 'like', "%{$keyword}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('customers.index', compact('customers', 'keyword'));
    }

    public function create()
    {
        return view('customers.create');
    }

    public function store(Request $request)
    {
        $customer = Customer::create($this->validated($request));

        return redirect()->route('customers.show', $customer)
            ->with('status', '顧客を登録しました。');
    }

    public function show(Customer $customer)
    {
        return view('customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $customer->update($this->validated($request, $customer));

        return redirect()->route('customers.show', $customer)
            ->with('status', '顧客情報を更新しました。');
    }

    public function destroy(Customer $customer)
    {
        if ($blockers = $customer->deletionBlockers()) {
            return redirect()->route('customers.show', $customer)
                ->with('delete_error', self::blockerMessage($blockers));
        }

        $customer->delete();

        return redirect()->route('customers.index')
            ->with('status', '顧客を削除しました。');
    }

    public static function blockerMessage(array $blockers): string
    {
        $items = collect($blockers)->map(fn ($n, $label) => "{$label}{$n}件")->implode('・');

        return "この顧客には{$items}があるため、削除できません（履歴が消えてしまうため）。";
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:20', PhoneNumber::rule(), function (string $attribute, mixed $value, \Closure $fail) use ($customer) {
                // ハイフンの有無にかかわらず、同じ番号の重複を見つける（090-1111-2222 と 09011112222 は同じ）
                $exists = Customer::whereRaw("REPLACE(phone, '-', '') = ?", [PhoneNumber::normalize($value)])
                    ->when($customer, fn ($q) => $q->whereKeyNot($customer->id))
                    ->exists();
                if ($exists) {
                    $fail('この電話番号は、すでに登録されています。');
                }
            }],
            'email' => ['nullable', 'email', 'max:100'],
            'email2' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'birthday' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', 'string', 'max:10'],
            'memo' => ['nullable', 'string'],
        ], [], ['phone' => '電話番号']);

        $data['phone'] = PhoneNumber::normalize($data['phone']); // 数字だけにそろえて保存する

        return $data;
    }
}
