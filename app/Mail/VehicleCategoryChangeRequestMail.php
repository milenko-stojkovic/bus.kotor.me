<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class VehicleCategoryChangeRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $agencyName,
        public readonly string $agencyEmail,
        public readonly string $licensePlate,
        public readonly string $oldCategory,
        public readonly string $requestedCategory,
        public readonly int $attachmentCount,
        public readonly string $adminReviewUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Zahtjev za promjenu kategorije vozila',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.vehicle-category-change-request',
        );
    }
}
