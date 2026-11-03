<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AuditLogImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_cannot_be_updated_or_deleted(): void
    {
        $user = User::factory()->create();
        $log = AuditLog::query()->create([
            'actor_id' => $user->id,
            'action' => 'test_action',
            'created_at' => now(),
        ]);

        try {
            $log->update(['action' => 'tampered']);
            $this->fail('Expected update to throw');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }

        $this->assertSame('test_action', $log->fresh()->action);

        try {
            $log->delete();
            $this->fail('Expected delete to throw');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'action' => 'test_action']);
    }
}
