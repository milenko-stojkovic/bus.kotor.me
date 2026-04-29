<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class AgencyAdvanceYearlyStatementMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  non-empty-string  $pdfBinary
     */
    public function __construct(
        public User $agency,
        public int $year,
        public string $pdfBinary,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kartica avansa za godinu '.$this->year,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.agency-advance-yearly-statement',
        );
    }

    public function build(): static
    {
        $this->with([
            'year' => $this->year,
        ]);

        $this->attachData($this->pdfBinary, 'kartica-avansa-'.$this->year.'.pdf', [
            'mime' => 'application/pdf',
        ]);

        return $this;
    }
}

