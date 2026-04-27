<?php

namespace App\Mail;

use App\Models\AgencyAdvanceTopup;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class AdvanceTopupConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  non-empty-string  $pdfBinary
     */
    public function __construct(
        public User $agency,
        public AgencyAdvanceTopup $topup,
        public string $pdfBinary,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Potvrda o evidentiranoj avansnoj uplati',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.advance-topup-confirmation',
        );
    }

    public function build(): static
    {
        $this->with([
            'topup' => $this->topup,
        ]);

        $this->attachData($this->pdfBinary, 'potvrda-avans-'.$this->topup->id.'.pdf', [
            'mime' => 'application/pdf',
        ]);

        return $this;
    }
}

