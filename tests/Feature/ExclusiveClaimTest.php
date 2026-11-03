<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\TransferTicket;
use App\Models\User;
use App\Services\TicketClaimService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ExclusiveClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_only_one_officer_can_claim_a_ticket(): void
    {
        $branch = Branch::factory()->create();
        $submitter = User::factory()->create(['branch_id' => $branch->id]);
        $submitter->assignRole('submitter');

        $officerA = User::factory()->create(['branch_id' => $branch->id]);
        $officerA->assignRole('ft_officer');
        $officerB = User::factory()->create(['branch_id' => $branch->id]);
        $officerB->assignRole('ft_officer');

        $ticket = TransferTicket::factory()->create([
            'submitter_id' => $submitter->id,
            'branch_id' => $branch->id,
            'status' => TransferTicket::STATUS_AVAILABLE,
            'assigned_officer_id' => null,
        ]);

        /** @var TicketClaimService $service */
        $service = app(TicketClaimService::class);

        $claimed = $service->claim($ticket, $officerA);
        $this->assertSame($officerA->id, $claimed->assigned_officer_id);
        $this->assertSame(TransferTicket::STATUS_CLAIMED, $claimed->status);

        $this->expectException(RuntimeException::class);
        $service->claim($ticket->fresh(), $officerB);
    }

    public function test_http_claim_endpoint_is_race_safe(): void
    {
        $branch = Branch::factory()->create();
        $submitter = User::factory()->create(['branch_id' => $branch->id]);
        $submitter->assignRole('submitter');
        $officer = User::factory()->create(['branch_id' => $branch->id]);
        $officer->assignRole('ft_officer');
        $other = User::factory()->create(['branch_id' => $branch->id]);
        $other->assignRole('ft_officer');

        $ticket = TransferTicket::factory()->create([
            'submitter_id' => $submitter->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($officer)
            ->post(route('tickets.claim', $ticket))
            ->assertRedirect();

        $this->actingAs($other)
            ->post(route('tickets.claim', $ticket->fresh()))
            ->assertSessionHasErrors('claim');

        $this->assertSame($officer->id, $ticket->fresh()->assigned_officer_id);
    }
}
