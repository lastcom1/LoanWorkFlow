<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\TransferTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public function log(
        string $action,
        ?User $actor = null,
        ?Model $auditable = null,
        ?TransferTicket $ticket = null,
        ?array $before = null,
        ?array $after = null,
        ?array $meta = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'ticket_id' => $ticket?->id ?? ($auditable instanceof TransferTicket ? $auditable->id : null),
            'before' => $this->sanitize($before),
            'after' => $this->sanitize($after),
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 500),
            'meta' => $this->sanitize($meta),
            'created_at' => now(),
        ]);
    }

    private function sanitize(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $blocked = ['password', 'password_confirmation', 'remember_token', 'pin', 'cvv', 'otp', 'secret'];

        $clean = [];
        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $blocked, true)) {
                $clean[$key] = '[redacted]';
                continue;
            }
            if (is_array($value)) {
                $clean[$key] = $this->sanitize($value);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
