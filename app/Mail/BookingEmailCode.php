<?php

namespace App\Mail;

use App\Services\VerificationCode;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// はじめての予約で、メールアドレスがご本人のものか確かめるためのコード
class BookingEmailCode extends Mailable
{
    public function __construct(public string $name, public string $code)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '【' . config('app.name') . '】メールアドレスの確認コード');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.booking-email-code', with: ['minutes' => VerificationCode::CODE_MINUTES]);
    }
}
