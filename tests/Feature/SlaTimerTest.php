<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Setting;
use App\Models\TransferTicket;
use App\Models\User;
use App\Services\SlaService;
use App\Services\TicketClaimService;
use App\Services\TicketService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SlaTimerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Mail::fake();
    }

    public function test_claim_starts_sla_with_default_minutes(): void
    {
        $branch = Branch::factory()->create();
        $submitter = User::factory()->create(['branch_id' => $branch->id]);
        $submitter->assignRole('submitter');
        $officer = User::factory()->create(['branch_id' => $branch->id]);
        $officer->assignRole('ft_officer');

        $ticket = TransferTicket::factory()->create([
            'submitter_id' => $submitter->id,
            'branch_id' => $branch->id,
            'status' => TransferTicket::STATUS_AVAILABLE,
            'assigned_officer_id' => null,
            'claimed_at' => null,
        ]);

        $claimed = app(TicketClaimService::class)->claim($ticket, $officer);

        $this->assertSame(SlaService::DEFAULT_MINUTES, (int) $claimed->sla_minutes);
        $this->assertNotNull($claimed->sla_due_at);
        $this->assertNull($claimed->sla_stopped_at);
        $this->assertTrue($claimed->sla_due_at->equalTo($claimed->claimed_at->copy()->addMinutes(SlaService::DEFAULT_MINUTES)));
    }

    public function test_per_category_sla_override_is_used(): void
    {
        Setting::setValue('claim_sla_minutes', 240, 'workflow');
        Setting::setValue('claim_sla_minutes_reversal_complaint', 60, 'workflow');

        $branch = Branch::factory()->create();
        $submitter = User::factory()->create(['branch_id' => $branch->id]);
        $submitter->assignRole('submitter');
        $officer = User::factory()->create(['branch_id' => $branch->id]);
        $officer->assignRole('ft_officer');

        $ticket = TransferTicket::factory()->reversal('REF-SLA-1')->create([
            'submitter_id' => $submitter->id,
            'branch_id' => $branch->id,
            'status' => TransferTicket::STATUS_AVAILABLE,
            'assigned_officer_id' => null,
        ]);

        $claimed = app(TicketClaimService::class)->claim($ticket, $officer);
        $this->assertSame(60, (int) $claimed->sla_minutes);
    }

    public function test_complete_stops_sla_and_records_duration(): void
    {
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
        $started = app(TicketService::class)->transition(
            $claimed,
            $officer,
            TransferTicket::STATUS_IN_PROGRESS,
            'Starting'
        );
        $done = app(TicketService::class)->transition(
            $started,
            $officer,
            TransferTicket::STATUS_COMPLETED,
            'Done',
            ['resolution_notes' => 'Processed', 'processing_outcome' => 'completed_in_portal']
        );

        $this->assertNotNull($done->sla_stopped_at);
        $this->assertNotNull($done->sla_elapsed_seconds);
        $this->assertGreaterThanOrEqual(0, (int) $done->sla_elapsed_seconds);
        $this->assertFalse($done->slaIsRunning());
    }

    public function test_ticket_show_and_list_render_sla_badge(): void
    {
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

        $this->actingAs($officer)
            ->get(route('tickets.show', $claimed))
            ->assertOk()
            ->assertSee('data-sla-live', false)
            ->assertSee('Pick-up SLA', false);

        $this->actingAs($officer)
            ->get(route('tickets.index'))
            ->assertOk()
            ->assertSee('sla-badge', false);
    }
}
