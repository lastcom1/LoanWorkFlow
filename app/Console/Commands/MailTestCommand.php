<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailTestCommand extends Command
{
    protected $signature = 'mail:test {email : Recipient address on a mailbox you can read}';

    protected $description = 'Send one test message through the configured mail transport (local sendmail in production)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');

        $this->line('Mailer: '.$mailer);
        $this->line('From: '.$from);
        $this->line('Sendmail: '.(string) config('mail.mailers.sendmail.path'));

        try {
            Mail::raw(
                'NNMFB portal mail test at '.now()->toDateTimeString().'. If you can read this, local sendmail accepted the message.',
                function ($message) use ($email): void {
                    $message->to($email)->subject('[NNMFB] Mail transport test');
                }
            );
        } catch (Throwable $e) {
            Log::error('[mail] mail:test failed', [
                'mailer' => $mailer,
                'from' => $from,
                'email_hash' => hash('sha256', $email),
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            $this->error('Mail transport failed: '.$e->getMessage());
            $this->error('See storage/logs/laravel.log for the [mail] error line.');

            return self::FAILURE;
        }

        $this->info('Test message was accepted by the '.$mailer.' transport. Check the inbox and spam folder.');

        return self::SUCCESS;
    }
}
