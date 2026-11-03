<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\TransferTicket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RejectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_officer_can_reject_with_reason_and_ticket_is_retained(): void
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
            'status' => TransferTicket::STATUS_IN_PROGRESS,
            'claimed_at' => now(),
        ]);

        $this->actingAs($officer)
            ->post(route('tickets.reject', $ticket), [
                'rejection_reason' => 'Incomplete supporting documents provided by branch.',
                'confirm_reject' => '1',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame(TransferTicket::STATUS_REJECTED, $ticket->status);
        $this->assertNotNull($ticket->rejection_reason);
        $this->assertDatabaseHas('transfer_tickets', [
            'id' => $ticket->id,
            'status' => TransferTicket::STATUS_REJECTED,
        ]);
    }
}
