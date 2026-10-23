<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;

// 管理画面で予約の日時を変えたときの、お客様へのお知らせ
class ReservationChanged extends Mailable
{
    public function __construct(public Reservation $reservation, public Carbon $before)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: '【' . config('app.name') . '】ご予約の日時を変更しました');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reservation-changed');
    }
}
