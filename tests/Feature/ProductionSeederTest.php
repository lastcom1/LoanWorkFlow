<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DefaultAdminSeeder;
use Database\Seeders\DemoDatabaseSeeder;
use Database\Seeders\DemoTicketSeeder;
use Database\Seeders\ProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalEnv = $this->app['env'];
    }

    protected function tearDown(): void
    {
        $this->app['env'] = $this->originalEnv;
        parent::tearDown();
    }

    public function test_production_seeder_does_not_create_demo_users(): void
    {
        $this->seed(ProductionSeeder::class);

        $this->assertSame(0, User::query()->count());
        $this->assertDatabaseHas('branches', ['code' => 'HQ-ABJ']);
        $this->assertDatabaseHas('roles', ['name' => 'admin']);
    }

    public function test_database_seeder_skips_demo_when_app_env_production(): void
    {
        $this->app['env'] = 'production';

        // Call seeder class directly to avoid artisan production confirmation prompts.
        $this->app->make(DatabaseSeeder::class)->run();

        $this->assertSame(0, User::query()->where('is_demo', true)->count());
        $this->assertSame(0, User::query()->count());
        $this->assertDatabaseHas('roles', ['name' => 'ft_officer']);
    }

    public function test_demo_seeders_refuse_in_production(): void
    {
        $this->app->make(ProductionSeeder::class)->run();
        $this->app['env'] = 'production';

        $this->app->make(DemoDatabaseSeeder::class)->run();
        $this->app->make(DefaultAdminSeeder::class)->run();
        $this->app->make(DemoTicketSeeder::class)->run();

        $this->assertSame(0, User::query()->count());
    }
}
