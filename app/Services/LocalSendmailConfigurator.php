<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * This host blocks outbound SMTP (Zoho). Production mail must leave through
 * local sendmail / PHP mail(), with an @nigerianavymfb.com envelope sender.
 */
class LocalSendmailConfigurator
{
    public const FROM_DOMAIN = 'nigerianavymfb.com';

    public const FALLBACK_FROM = 'noreply@nigerianavymfb.com';

    private static bool $loggedMailerOverride = false;

    private static bool $loggedFromOverride = false;

    public static function flushLogGuards(): void
    {
        self::$loggedMailerOverride = false;
        self::$loggedFromOverride = false;
    }

    public function apply(): void
    {
        if (! $this->shouldEnforce()) {
            return;
        }

        if (config('mail.default') === 'smtp') {
            if (! self::$loggedMailerOverride) {
                Log::error('[mail] MAIL_MAILER=smtp cannot leave this host (outbound SMTP is blocked). Using local sendmail.', [
                    'configured_host' => config('mail.mailers.smtp.host'),
                    'configured_port' => config('mail.mailers.smtp.port'),
                ]);
                self::$loggedMailerOverride = true;
            }

            config(['mail.default' => 'sendmail']);
        }

        $from = $this->approvedFromAddress();
        $name = (string) (config('mail.from.name') ?: 'NNMFB Transfer Portal');
        $binary = $this->sendmailBinary();

        config([
            'mail.enforce_local_envelope' => true,
            'mail.from.address' => $from,
            'mail.from.name' => $name,
            'mail.mailers.sendmail.transport' => 'sendmail',
            // -t -i matches PHP mail(). Symfony appends -f from the envelope
            // when it is not already present; we set it explicitly so Exim
            // accepts the sender even if a message omits the header.
            'mail.mailers.sendmail.path' => $binary.' -t -i -f'.escapeshellarg($from),
        ]);
    }

    public function shouldEnforce(): bool
    {
        return app()->environment('production') || config('mail.default') === 'sendmail';
    }

    public function approvedFromAddress(): string
    {
        $configured = strtolower(trim((string) config('mail.from.address')));

        if ($this->isApprovedAddress($configured)) {
            return $configured;
        }

        if ($configured !== '' && ! self::$loggedFromOverride) {
            Log::error('[mail] MAIL_FROM_ADDRESS is not an @'.self::FROM_DOMAIN.' address. Using '.self::FALLBACK_FROM.' so local sendmail accepts the envelope.', [
                'configured_from' => $configured,
            ]);
            self::$loggedFromOverride = true;
        }

        return self::FALLBACK_FROM;
    }

    public function isApprovedAddress(string $address): bool
    {
        return (bool) preg_match('/^[a-z0-9._+\-]+@'.preg_quote(self::FROM_DOMAIN, '/').'$/', strtolower($address));
    }

    private function sendmailBinary(): string
    {
        $configured = trim((string) config('mail.mailers.sendmail.path'));

        if (preg_match('/^(\S+)/', $configured, $matches) === 1 && $matches[1] !== '') {
            return $matches[1];
        }

        return '/usr/sbin/sendmail';
    }
}
