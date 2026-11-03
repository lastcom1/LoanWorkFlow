<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\TransferTicket;
use App\Models\User;
use App\Services\TicketClaimService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailFailureIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        // phpunit uses QUEUE_CONNECTION=sync — queued mailables send inline and can throw.
        $this->useFailingMailTransport();
    }

    public function test_http_claim_succeeds_when_smtp_is_down(): void
    {
        $branch = Branch::factory()->create();
        $submitter = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $submitter->assignRole('submitter');
        $officer = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $officer->assignRole('ft_officer');

        $ticket = TransferTicket::factory()->create([
            'submitter_id' => $submitter->id,
            'branch_id' => $branch->id,
            'status' => TransferTicket::STATUS_AVAILABLE,
            'assigned_officer_id' => null,
        ]);

        $this->actingAs($officer)
            ->post(route('tickets.claim', $ticket))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame($officer->id, $ticket->assigned_officer_id);
        $this->assertSame(TransferTicket::STATUS_CLAIMED, $ticket->status);
    }

    public function test_claim_service_keeps_claim_when_notification_mail_fails(): void
    {
        $branch = Branch::factory()->create();
        $submitter = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $submitter->assignRole('submitter');
        $officer = User::factory()->create(['branch_id' => $branch->id, 'is_active' => true]);
        $officer->assignRole('ft_officer');

        $ticket = TransferTicket::factory()->create([
            'submitter_id' => $submitter->id,
            'branch_id' => $branch->id,
            'status' => TransferTicket::STATUS_AVAILABLE,
            'assigned_officer_id' => null,
        ]);

        /** @var TicketClaimService $service */
        $service = app(TicketClaimService::class);
        $claimed = $service->claim($ticket, $officer);

        $this->assertSame($officer->id, $claimed->assigned_officer_id);
        $this->assertSame(TransferTicket::STATUS_CLAIMED, $claimed->status);
    }
}
