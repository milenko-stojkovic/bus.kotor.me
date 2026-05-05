<?php

namespace App\Mail;

use App\Models\LimoIncident;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

final class LimoCommunalPoliceIncidentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly LimoIncident $incident,
        public readonly string $evidenterLabel,
        public readonly string $typeLabelCg,
        public readonly string $occurredAtFormatted,
    ) {}

    public function build(): static
    {
        $this->subject('Limo incident – '.$this->typeLabelCg.' – '.$this->occurredAtFormatted);
        $this->view('emails.limo-communal-police-incident');
        $this->text('emails.limo-communal-police-incident-text');

        $platePath = $this->incident->plate_photo_path;
        if ($platePath !== '' && Storage::disk('local')->exists($platePath)) {
            $this->attachFromStorageDisk('local', $platePath, 'tablica_'.basename($platePath));
        }

        $brandingPath = $this->incident->branding_photo_path;
        if ($brandingPath !== null && $brandingPath !== '' && Storage::disk('local')->exists($brandingPath)) {
            $this->attachFromStorageDisk('local', $brandingPath, 'brending_'.basename($brandingPath));
        }

        return $this;
    }
}
