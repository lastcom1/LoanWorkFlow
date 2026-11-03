<?php

namespace App\Listeners;

use App\Services\LocalSendmailConfigurator;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Force From, Sender, and Return-Path onto the bank domain before the
 * message is handed to sendmail. Exim on this host rejects other envelope
 * senders.
 */
class EnforceLocalMailEnvelope
{
    public function __construct(private LocalSendmailConfigurator $mailConfig) {}

    public function handle(MessageSending $event): void
    {
        if (! config('mail.enforce_local_envelope')) {
            return;
        }

        $message = $event->message;

        if (! $message instanceof Email) {
            return;
        }

        $from = $this->mailConfig->approvedFromAddress();
        $name = (string) (config('mail.from.name') ?: 'NNMFB Transfer Portal');
        $address = new Address($from, $name);

        $message->from($address);
        $message->sender($address);
        $message->returnPath($from);
    }
}
