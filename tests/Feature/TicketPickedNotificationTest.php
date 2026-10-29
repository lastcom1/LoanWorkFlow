<?php

namespace Tests\Feature;

use App\Mail\TicketClaimedMail;
use App\Models\Branch;
use App\Models\TransferTicket;
use App\Models\User;
use App\Services\TicketClaimService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TicketPickedNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_submitter_receives_picked_email_with_officer_details(): void
    {
        Mail::fake();

        $branch = Branch::factory()->create(['name' => 'Lagos Branch']);
        $submitter = User::factory()->create([
            'branch_id' => $branch->id,
            'name' => 'Branch Submitter',
        ]);
        $submitter->assignRole('submitter');

        $officer = User::factory()->create([
            'branch_id' => $branch->id,
            'name' => 'Ada FT Officer',
        ]);
        $officer->assignRole('ft_officer');

        $ticket = TransferTicket::factory()->create([
            'submitter_id' => $submitter->id,
            'branch_id' => $branch->id,
            'status' => TransferTicket::STATUS_AVAILABLE,
        ]);

        $claimed = app(TicketClaimService::class)->claim($ticket, $officer);

        Mail::assertQueued(TicketClaimedMail::class, function (TicketClaimedMail $mail) use ($submitter, $claimed) {
            return $mail->hasTo($submitter->email)
                && str_contains($mail->envelope()->subject, 'Picked Up')
                && str_contains($mail->envelope()->subject, $claimed->ticket_ref);
        });
    }

    public function test_pick_notification_failure_does_not_undo_claim(): void
    {
        $this->useFailingMailTransport();

        $branch = Branch::factory()->create();
        $submitter = User::factory()->create(['branch_id' => $branch->id]);
        $submitter->assignRole('submitter');
        $officer = User::factory()->create(['branch_id' => $branch->id]);
        $officer->assignRole('ft_officer');

        $ticket = TransferTicket::factory()->create([
            'submitter_id' => $submitter->id,
            'branch_id' => $branch->id,
            'status' => TransferTicket::STATUS_AVAILABLE,
        ]);

        $claimed = app(TicketClaimService::class)->claim($ticket, $officer);

        $this->assertSame($officer->id, $claimed->assigned_officer_id);
        $this->assertSame(TransferTicket::STATUS_CLAIMED, $claimed->status);
        $this->assertNotNull($claimed->sla_due_at);
    }
}
