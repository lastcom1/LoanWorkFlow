<?php

namespace Tests\Support;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Symfony mail transport that always throws — simulates unreachable SMTP.
 */
class FailsMailTransport implements TransportInterface
{
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        throw new TransportException('SMTP unreachable (simulated for tests)');
    }

    public function __toString(): string
    {
        return 'failing://test';
    }
}
