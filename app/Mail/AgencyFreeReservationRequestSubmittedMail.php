<?php

namespace App\Mail;

use App\Models\FreeReservationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class AgencyFreeReservationRequestSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public FreeReservationRequest $request)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Zahtjev za besplatnu rezervaciju (FZBR)',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.agency-free-reservation-request-submitted',
        );
    }

    public function build(): static
    {
        $this->with([
            'req' => $this->request,
        ]);

        foreach ($this->request->attachments as $a) {
            $path = (string) $a->stored_path;
            if ($path !== '' && Storage::disk('local')->exists($path)) {
                $this->attach(Storage::disk('local')->path($path), [
                    'as' => $a->original_name,
                    'mime' => $a->mime_type ?: null,
                ]);
            }
        }

        return $this;
    }
}

