<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent synchronously on admin create/resend so the temp password is not
 * persisted in the queue payload. Failures are caught by UserOnboardingService.
 */
class WelcomeUserMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $temporaryPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[NNMFB] Your Transfer Portal account',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome-user',
            with: [
                'user' => $this->user,
                'temporaryPassword' => $this->temporaryPassword,
                'loginUrl' => url('/login'),
                'portalUrl' => url('/login'),
            ],
        );
    }
}
