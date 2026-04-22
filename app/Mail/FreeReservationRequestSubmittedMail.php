<?php

namespace App\Mail;

use App\Models\FreeReservationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FreeReservationRequestSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public FreeReservationRequest $request)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('Zahtjev za besplatnu rezervaciju (učenička/humanitarna)')
            ->view('emails.free-reservation-request-submitted', [
                'r' => $this->request,
            ]);
    }
}

