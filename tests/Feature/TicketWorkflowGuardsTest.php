<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\TicketStatusHistory;
use App\Models\TransferTicket;
use App\Models\User;
use App\Services\TicketService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TicketWorkflowGuardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_cannot_complete_from_claimed_without_starting(): void
    {
        [$ticket, $officer] = $this->claimedTicket();

        $this->expectException(RuntimeException::class);
        app(TicketService::class)->transition(
            $ticket,
            $officer,
            TransferTicket::STATUS_COMPLETED,
            'skip start'
        );
    }

    public function test_second_closing_transition_fails_after_lock(): void
    {
        [$ticket, $officer] = $this->claimedTicket();
        $service = app(TicketService::class);

        $service->transition($ticket, $officer, TransferTicket::STATUS_IN_PROGRESS, 'start');
        $service->transition($ticket->fresh(), $officer, TransferTicket::STATUS_COMPLETED, 'done', [
            'processing_outcome' => 'ok',
            'resolution_notes' => 'done',
        ]);

        $this->expectException(RuntimeException::class);
        $service->transition($ticket->fresh(), $officer, TransferTicket::STATUS_REJECTED, 'too late reject reason');
    }

    public function test_reassign_refuses_completed_and_writes_history_when_open(): void
    {
        $branch = Branch::factory()->create();
        $submitter = User::factory()->create(['branch_id' => $branch->id]);
        $submitter->assignRole('submitter');
        $officerA = User::factory()->create(['branch_id' => $branch->id]);
        $officerA->assignRole('ft_officer');
        $officerB = User::factory()->create(['branch_id' => $branch->id]);
        $officerB->assignRole('ft_officer');
        $supervisor = User::factory()->create(['branch_id' => $branch->id]);
        $supervisor->assignRole('supervisor');

        $ticket = TransferTicket::factory()->create([
            'submitter_id' => $submitter->id,
            'branch_id' => $branch->id,
            'assigned_officer_id' => $officerA->id,
            'status' => TransferTicket::STATUS_IN_PROGRESS,
            'claimed_at' => now(),
        ]);

        $service = app(TicketService::class);
        $service->reassign($ticket, $supervisor, $officerB, 'Workload balancing');

        $ticket->refresh();
        $this->assertSame($officerB->id, $ticket->assigned_officer_id);
        $this->assertSame(TransferTicket::STATUS_CLAIMED, $ticket->status);
        $this->assertTrue(
            TicketStatusHistory::query()
                ->where('ticket_id', $ticket->id)
                ->where('to_status', TransferTicket::STATUS_CLAIMED)
                ->where('reason', 'like', 'Reassigned:%')
                ->exists()
        );

        $service->transition($ticket->fresh(), $officerB, TransferTicket::STATUS_IN_PROGRESS, 'start');
        $service->transition($ticket->fresh(), $officerB, TransferTicket::STATUS_COMPLETED, 'done', [
            'processing_outcome' => 'ok',
            'resolution_notes' => 'done',
        ]);

        $this->expectException(RuntimeException::class);
        $service->reassign($ticket->fresh(), $supervisor, $officerA, 'Should fail');
    }

    public function test_duplicate_check_minimal_payload_for_submitter(): void
    {
        $branch = Branch::factory()->create();
        $submitter = User::factory()->create(['branch_id' => $branch->id]);
        $submitter->assignRole('submitter');
        $officer = User::factory()->create(['branch_id' => $branch->id, 'name' => 'Secret Officer']);
        $officer->assignRole('ft_officer');

        TransferTicket::factory()->create([
            'submitter_id' => $submitter->id,
            'branch_id' => $branch->id,
            'assigned_officer_id' => $officer->id,
            'customer_account' => '5555666677',
            'customer_account_normalized' => '5555666677',
            'amount' => 1000,
            'amount_normalized' => '1000.00',
            'status' => TransferTicket::STATUS_CLAIMED,
        ]);

        $response = $this->actingAs($submitter)->postJson(route('tickets.check-duplicates'), [
            'category' => TransferTicket::CATEGORY_FUND_TRANSFER,
            'customer_account' => '5555666677',
            'amount' => 1000,
        ]);

        $response->assertOk();
        $match = $response->json('matches.0');
        $this->assertArrayHasKey('ticket_ref', $match);
        $this->assertArrayHasKey('masked_account', $match);
        $this->assertArrayNotHasKey('assigned_officer', $match);
        $this->assertArrayNotHasKey('submitter', $match);
        $this->assertArrayNotHasKey('branch', $match);
        $this->assertArrayNotHasKey('url', $match);
        $this->assertStringNotContainsString('Secret Officer', $response->getContent());
    }

    /** @return array{0: TransferTicket, 1: User} */
    private function claimedTicket(): array
    {
        $branch = Branch::factory()->create();
        $submitter = User::factory()->create(['branch_id' => $branch->id]);
        $submitter->assignRole('submitter');
        $officer = User::factory()->create(['branch_id' => $branch->id]);
        $officer->assignRole('ft_officer');

        $ticket = TransferTicket::factory()->create([
            'submitter_id' => $submitter->id,
            'branch_id' => $branch->id,
            'assigned_officer_id' => $officer->id,
            'status' => TransferTicket::STATUS_CLAIMED,
            'claimed_at' => now(),
        ]);

        return [$ticket, $officer];
    }
}
