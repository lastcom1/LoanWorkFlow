<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FailsMailTransport;

abstract class TestCase extends BaseTestCase
{
    /**
     * Force the default mailer to throw TransportException on every send.
     */
    protected function useFailingMailTransport(): void
    {
        Mail::extend('failing', fn () => new FailsMailTransport);

        config([
            'mail.default' => 'failing',
            'mail.mailers.failing' => [
                'transport' => 'failing',
            ],
        ]);
    }
}

