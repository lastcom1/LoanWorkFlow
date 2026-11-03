<?php

namespace Tests\Feature;

use App\Mail\WelcomeUserMail;
use App\Models\User;
use App\Services\LocalSendmailConfigurator;
use App\Services\UserOnboardingService;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WelcomeMailTransportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        LocalSendmailConfigurator::flushLogGuards();
    }

    public function test_welcome_mailable_is_sent_immediately_and_not_queued(): void
    {
        $this->assertFalse(is_subclass_of(WelcomeUserMail::class, ShouldQueue::class));

        Queue::fake();
        Mail::fake();

        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Queued Check',
            'email' => 'queued-check@nnmfb.test',
            'role' => 'submitter',
            'is_active' => '1',
            'send_welcome' => '1',
        ])->assertRedirect();

        Mail::assertSent(WelcomeUserMail::class);
        Queue::assertNothingPushed();
    }

    public function test_welcome_failure_is_logged_as_an_error_and_does_not_block_create(): void
    {
        $this->useFailingMailTransport();

        $logged = [];
        Log::listen(function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Mail Fail',
            'email' => 'welcome-fail@nnmfb.test',
            'role' => 'ft_officer',
            'is_active' => '1',
            'send_welcome' => '1',
        ])->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['email' => 'welcome-fail@nnmfb.test']);

        $match = collect($logged)->first(function (MessageLogged $event): bool {
            return $event->level === 'error'
                && str_contains($event->message, '[mail] Welcome email failed');
        });

        $this->assertNotNull($match);
        $this->assertSame('failing', $match->context['mailer'] ?? null);
        $this->assertArrayHasKey('error', $match->context);
        $this->assertArrayNotHasKey('temporaryPassword', $match->context);
        $this->assertStringNotContainsString('Temp', json_encode($match->context));
    }

    public function test_production_smtp_is_forced_onto_local_sendmail_with_bank_envelope(): void
    {
        $this->app['env'] = 'production';
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.zoho.com',
            'mail.mailers.smtp.port' => 587,
            'mail.from.address' => 'portal@zoho.com',
            'mail.from.name' => 'NNMFB Transfer Portal',
            'mail.mailers.sendmail.path' => '/usr/sbin/sendmail -bs -i',
        ]);

        $logged = [];
        Log::listen(function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });

        app(LocalSendmailConfigurator::class)->apply();

        $this->assertSame('sendmail', config('mail.default'));
        $this->assertTrue(config('mail.enforce_local_envelope'));
        $this->assertSame('noreply@nigerianavymfb.com', config('mail.from.address'));
        $path = (string) config('mail.mailers.sendmail.path');
        $this->assertStringContainsString('/usr/sbin/sendmail', $path);
        $this->assertStringContainsString('-t', $path);
        $this->assertStringContainsString('-i', $path);
        $this->assertStringNotContainsString('-bs', $path);
        $this->assertStringContainsString(escapeshellarg('noreply@nigerianavymfb.com'), $path);

        $this->assertTrue(collect($logged)->contains(
            fn (MessageLogged $event) => $event->level === 'error' && str_contains($event->message, 'outbound SMTP is blocked')
        ));
    }

    public function test_message_envelope_is_rewritten_to_the_bank_domain(): void
    {
        config([
            'mail.enforce_local_envelope' => true,
            'mail.from.address' => 'other@example.com',
            'mail.from.name' => 'NNMFB Transfer Portal',
        ]);

        $user = User::factory()->create(['email' => 'envelope@nnmfb.test']);
        $captured = null;
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$captured): void {
            $captured = $event->message;
        });

        Mail::to($user->email)->send(new WelcomeUserMail($user, 'TempPass@12345'));

        $this->assertNotNull($captured);
        $this->assertSame('noreply@nigerianavymfb.com', $captured->getFrom()[0]->getAddress());
        $this->assertSame('noreply@nigerianavymfb.com', $captured->getSender()->getAddress());
        $this->assertSame('noreply@nigerianavymfb.com', $captured->getReturnPath()->getAddress());
    }

    public function test_resend_welcome_rotates_the_password_for_an_admin(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $user = User::factory()->create([
            'email' => 'resend-admin@nnmfb.test',
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('submitter');
        $oldHash = $user->password;

        $this->actingAs($admin)
            ->post(route('admin.users.resend-welcome', $user))
            ->assertRedirect()
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue($user->must_change_password);
        $this->assertNull($user->password_changed_at);
        $this->assertNotSame($oldHash, $user->password);
        Mail::assertSent(WelcomeUserMail::class, fn (WelcomeUserMail $mail) => $mail->hasTo($user->email));
    }

    public function test_direct_send_still_swallows_transport_errors(): void
    {
        $this->useFailingMailTransport();
        $user = User::factory()->create();
        $user->assignRole('submitter');

        $ok = app(UserOnboardingService::class)->sendWelcomeEmail($user, 'TempPass@12345');

        $this->assertFalse($ok);
    }
}
