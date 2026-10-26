<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\TransferTicket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacAndIdorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_submitter_cannot_view_unrelated_ticket(): void
    {
        $branch = Branch::factory()->create();
        $owner = User::factory()->create(['branch_id' => $branch->id]);
        $owner->assignRole('submitter');
        $other = User::factory()->create(['branch_id' => $branch->id]);
        $other->assignRole('submitter');

        $ticket = TransferTicket::factory()->create([
            'submitter_id' => $owner->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($other)
            ->get(route('tickets.show', $ticket))
            ->assertForbidden();
    }

    public function test_submitter_cannot_claim_tickets(): void
    {
        $branch = Branch::factory()->create();
        $submitter = User::factory()->create(['branch_id' => $branch->id]);
        $submitter->assignRole('submitter');

        $ticket = TransferTicket::factory()->create([
            'submitter_id' => $submitter->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($submitter)
            ->post(route('tickets.claim', $ticket))
            ->assertForbidden();
    }

    public function test_auditor_can_view_audit_logs(): void
    {
        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');

        $this->actingAs($auditor)
            ->get(route('auditor.audit-logs'))
            ->assertOk();
    }
}
