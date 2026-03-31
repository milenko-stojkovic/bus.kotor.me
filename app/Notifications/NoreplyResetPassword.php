<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class NoreplyResetPassword extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $mail = parent::toMail($notifiable);

        $from = (array) config('mail.from_noreply', []);
        $address = $from['address'] ?? null;
        $name = $from['name'] ?? null;

        return $mail
            ->mailer('noreply')
            ->from(is_string($address) ? $address : null, is_string($name) ? $name : null);
    }
}

