<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use App\Support\UiText;

class NoreplyVerifyEmail extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $locale = is_string($notifiable?->lang ?? null) && ($notifiable->lang === 'cg') ? 'cg' : 'en';

        $mail = (new MailMessage())
            ->subject(UiText::t('auth', 'verify_mail_subject', 'Verify Email Address', $locale))
            ->line(UiText::t('auth', 'verify_mail_line1', 'Please click the button below to verify your email address.', $locale))
            ->action(
                UiText::t('auth', 'verify_mail_action', 'Verify Email Address', $locale),
                $this->verificationUrl($notifiable)
            )
            ->line(UiText::t('auth', 'verify_mail_line2', 'If you did not create an account, no further action is required.', $locale));

        $from = (array) config('mail.from_noreply', []);
        $address = $from['address'] ?? null;
        $name = $from['name'] ?? null;

        return $mail
            ->mailer('noreply')
            ->from(is_string($address) ? $address : null, is_string($name) ? $name : null);
    }
}

