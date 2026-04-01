<?php

namespace App\Notifications;

use App\Support\UiText;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class NoreplyVerifyEmail extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $locale = is_string($notifiable?->lang ?? null) && ($notifiable->lang === 'cg') ? 'cg' : 'en';

        $verifyUrl = $this->verificationUrl($notifiable);
        $actionText = UiText::t('auth', 'verify_mail_action', 'Verify Email Address', $locale);

        $subcopyIntro = str_replace(
            ':actionText',
            $actionText,
            UiText::t(
                'auth',
                'verify_mail_subcopy_intro',
                'If you\'re having trouble clicking the ":actionText" button, copy and paste the URL below into your web browser:',
                $locale
            )
        );

        $salutation = str_replace(
            ':app',
            (string) config('app.name'),
            UiText::t('auth', 'verify_mail_salutation', 'Regards, :app', $locale)
        );

        $mail = (new MailMessage)
            ->greeting(UiText::t('auth', 'verify_mail_greeting', 'Hello!', $locale))
            ->subject(UiText::t('auth', 'verify_mail_subject', 'Verify Email Address', $locale))
            ->line(UiText::t('auth', 'verify_mail_line1', 'Please click the button below to verify your email address.', $locale))
            ->action($actionText, $verifyUrl)
            ->line(UiText::t('auth', 'verify_mail_line2', 'If you did not create an account, no further action is required.', $locale))
            ->salutation($salutation);

        $mail->viewData = array_merge($mail->viewData, [
            'mailSubcopyIntro' => $subcopyIntro,
        ]);

        $from = (array) config('mail.from_noreply', []);
        $address = $from['address'] ?? null;
        $name = $from['name'] ?? null;

        return $mail
            ->mailer('noreply')
            ->from(is_string($address) ? $address : null, is_string($name) ? $name : null);
    }
}
