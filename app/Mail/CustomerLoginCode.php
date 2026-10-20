<?php

namespace App\Mail;

use App\Services\CustomerLogin;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CustomerLoginCode extends Mailable
{
    public function __construct(public string $name, public string $code)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '【' . config('app.name') . '】ご利用履歴の確認コード');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.customer-login-code', with: ['minutes' => CustomerLogin::CODE_MINUTES]);
    }
}
