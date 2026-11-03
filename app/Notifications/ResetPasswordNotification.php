<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('[NNMFB] Password reset request')
            ->view('emails.password-reset', [
                'recipient' => $notifiable,
                'resetUrl' => $url,
                'expiresMinutes' => config('auth.passwords.users.expire', 60),
                'portalUrl' => url('/login'),
            ]);
    }
}
