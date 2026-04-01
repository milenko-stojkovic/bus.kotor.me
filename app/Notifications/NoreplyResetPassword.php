<?php

namespace App\Notifications;

use App\Support\UiText;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class NoreplyResetPassword extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $locale = is_string($notifiable?->lang ?? null) && ($notifiable->lang === 'cg') ? 'cg' : 'en';

        $resetUrl = $this->resetUrl($notifiable);
        $actionText = UiText::t('auth', 'reset_mail_action', 'Reset Password', $locale);

        $expireMinutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        $subcopyIntro = str_replace(
            ':actionText',
            $actionText,
            UiText::t(
                'auth',
                'reset_mail_subcopy_intro',
                'If you\'re having trouble clicking the ":actionText" button, copy and paste the URL below into your web browser:',
                $locale
            )
        );

        $salutation = str_replace(
            ':app',
            (string) config('app.name'),
            UiText::t('auth', 'reset_mail_salutation', 'Regards, :app', $locale)
        );

        $mail = (new MailMessage)
            ->greeting(UiText::t('auth', 'reset_mail_greeting', 'Hello!', $locale))
            ->subject(UiText::t('auth', 'reset_mail_subject', 'Reset Password Notification', $locale))
            ->line(UiText::t(
                'auth',
                'reset_mail_line1',
                'You are receiving this email because we received a password reset request for your account.',
                $locale
            ))
            ->action($actionText, $resetUrl)
            ->line(str_replace(
                ':count',
                (string) $expireMinutes,
                UiText::t(
                    'auth',
                    'reset_mail_line_expire',
                    'This password reset link will expire in :count minutes.',
                    $locale
                )
            ))
            ->line(UiText::t(
                'auth',
                'reset_mail_line_ignore',
                'If you did not request a password reset, no further action is required.',
                $locale
            ))
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
