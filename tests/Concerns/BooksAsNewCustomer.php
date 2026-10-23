<?php

namespace Tests\Concerns;

use App\Mail\BookingEmailCode;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

// はじめての方の予約：送信 → メールの確認コードを受け取る → コードを入力、までをまとめて行う（Mail::fake() が必要）
trait BooksAsNewCustomer
{
    protected function bookAsNewCustomer(array $data): TestResponse
    {
        $this->post('/book', $data)->assertRedirect(route('booking.verify'));

        return $this->post('/book/verify', ['code' => $this->lastBookingCode()]);
    }

    protected function lastBookingCode(): string
    {
        return Mail::sent(BookingEmailCode::class)->last()->code;
    }
}
