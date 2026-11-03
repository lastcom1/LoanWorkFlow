<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\TransferTicket;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use App\Services\TicketService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DuplicateDetectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_fund_transfer_duplicates_match_account_and_amount(): void
    {
        $branch = Branch::factory()->create();
        $submitter = User::factory()->create(['branch_id' => $branch->id]);
        $submitter->assignRole('submitter');

        TransferTicket::factory()->create([
            'submitter_id' => $submitter->id,
            'branch_id' => $branch->id,
            'customer_account' => '0123-456-789',
            'customer_account_normalized' => '0123456789',
            'amount' => 10000.00,
            'amount_normalized' => '10000.00',
            'category' => TransferTicket::CATEGORY_FUND_TRANSFER,
        ]);

        /** @var DuplicateDetectionService $dup */
        $dup = app(DuplicateDetectionService::class);
        $matches = $dup->findMatches(
            TransferTicket::CATEGORY_FUND_TRANSFER,
            $dup->normalizeAccount('0123456789'),
            $dup->normalizeAmount(10000),
            null
        );

        $this->assertCount(1, $matches);
    }

    public function test_reversal_duplicates_match_on_reference(): void
    {
        $branch = Branch::factory()->create();
        $submitter = User::factory()->create(['branch_id' => $branch->id]);
        $submitter->assignRole('submitter');

        TransferTicket::factory()->reversal('REV-ABC-999')->create([
            'submitter_id' => $submitter->id,
            'branch_id' => $branch->id,
            'customer_account' => '1111222233',
            'customer_account_normalized' => '1111222233',
            'amount' => 5000,
            'amount_normalized' => '5000.00',
        ]);

        /** @var DuplicateDetectionService $dup */
        $dup = app(DuplicateDetectionService::class);
        $matches = $dup->findMatches(
            TransferTicket::CATEGORY_REVERSAL,
            '9999999999',
            '1.00',
            $dup->normalizeReference('rev-abc-999')
        );

        $this->assertCount(1, $matches);
    }

    public function test_submission_requires_acknowledge_when_duplicate(): void
    {
        $branch = Branch::factory()->create();
        $submitter = User::factory()->create(['branch_id' => $branch->id]);
        $submitter->assignRole('submitter');

        TransferTicket::factory()->create([
            'submitter_id' => $submitter->id,
            'branch_id' => $branch->id,
            'customer_account' => '5555666677',
            'customer_account_normalized' => '5555666677',
            'amount' => 2500.00,
            'amount_normalized' => '2500.00',
        ]);

        /** @var TicketService $service */
        $service = app(TicketService::class);

        $this->expectException(RuntimeException::class);
        $service->create($submitter, [
            'category' => TransferTicket::CATEGORY_FUND_TRANSFER,
            'branch_id' => $branch->id,
            'customer_account' => '5555666677',
            'amount' => 2500,
            'confirmation_accepted' => true,
        ]);
    }
}
