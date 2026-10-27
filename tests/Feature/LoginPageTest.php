<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginPageTest extends TestCase
{
    public function test_login_demo_note_is_hidden_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('demo-note', false)
            ->assertDontSee('DEMO credentials', false);
    }

    public function test_login_demo_note_is_visible_outside_production(): void
    {
        // phpunit defaults to APP_ENV=testing (not production)
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('demo-note', false)
            ->assertSee('DEMO credentials', false);
    }

    public function test_forgot_password_demo_note_is_hidden_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->get(route('password.request'))
            ->assertOk()
            ->assertDontSee('demo-note', false)
            ->assertDontSee('submitter@nnmfb.demo', false)
            ->assertDontSee('MAIL_MAILER=log', false)
            ->assertDontSee('storage/logs/laravel.log', false);
    }

    public function test_forgot_password_demo_note_is_visible_outside_production(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('demo-note', false)
            ->assertSee('submitter@nnmfb.demo', false);
    }

    public function test_reset_password_guest_page_has_no_demo_note(): void
    {
        $this->app['env'] = 'production';

        // Token page is guest-accessible; must not leak demo/dev hints either.
        $this->get(route('password.reset', ['token' => 'test-token', 'email' => 'user@example.com']))
            ->assertOk()
            ->assertDontSee('demo-note', false)
            ->assertDontSee('nnmfb.demo', false)
            ->assertDontSee('MAIL_MAILER', false);
    }
}
