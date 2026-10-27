<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginPageTest extends TestCase
{
    public function test_demo_note_is_hidden_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('demo-note', false)
            ->assertDontSee('DEMO credentials', false);
    }

    public function test_demo_note_is_visible_outside_production(): void
    {
        // phpunit defaults to APP_ENV=testing (not production)
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('demo-note', false)
            ->assertSee('DEMO credentials', false);
    }
}
