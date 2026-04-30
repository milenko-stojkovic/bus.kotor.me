<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ScheduledAdminReportsMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, array{filename:string,binary:string}>  $pdfAttachments
     */
    public function __construct(
        public string $subjectLine,
        public string $bodyText,
        public array $pdfAttachments,
    ) {
    }

    /**
     * Convenience for tests / logging.
     *
     * @return array<int, string>
     */
    public function attachmentNames(): array
    {
        return array_map(fn (array $a) => (string) ($a['filename'] ?? ''), $this->pdfAttachments);
    }

    public function build(): self
    {
        $m = $this->subject($this->subjectLine)
            ->view('emails.scheduled-admin-reports', [
                'bodyText' => $this->bodyText,
            ]);

        foreach ($this->pdfAttachments as $a) {
            $filename = (string) ($a['filename'] ?? '');
            $binary = (string) ($a['binary'] ?? '');
            if ($filename === '' || $binary === '') {
                continue;
            }
            $m->attachData($binary, $filename, ['mime' => 'application/pdf']);
        }

        return $m;
    }
}

