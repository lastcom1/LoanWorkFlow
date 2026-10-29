<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\TransferTicket;
use Carbon\CarbonInterface;

/**
 * Claim/pick SLA clock — starts on exclusive claim, stops on resolve/reject.
 *
 * Defaults (minutes):
 * - Global fallback: 240 (4 hours)
 * - Fund transfer: claim_sla_minutes_fund_transfer (falls back to global)
 * - Reversal complaint: claim_sla_minutes_reversal_complaint (falls back to global)
 */
class SlaService
{
    public const DEFAULT_MINUTES = 240;

    public const WARN_RATIO = 0.85;

    public function minutesForCategory(string $category): int
    {
        $global = (int) Setting::getValue('claim_sla_minutes', (string) self::DEFAULT_MINUTES);
        if ($global < 15) {
            $global = self::DEFAULT_MINUTES;
        }

        $key = match ($category) {
            TransferTicket::CATEGORY_FUND_TRANSFER => 'claim_sla_minutes_fund_transfer',
            TransferTicket::CATEGORY_REVERSAL => 'claim_sla_minutes_reversal_complaint',
            default => null,
        };

        if ($key === null) {
            return $global;
        }

        $specific = Setting::getValue($key, '');
        if ($specific === null || $specific === '') {
            return $global;
        }

        $minutes = (int) $specific;

        return $minutes >= 15 ? $minutes : $global;
    }

    /**
     * @return array{sla_minutes: int, sla_due_at: \Illuminate\Support\Carbon}
     */
    public function startPayload(TransferTicket $ticket, ?CarbonInterface $claimedAt = null): array
    {
        $claimedAt = $claimedAt ? \Illuminate\Support\Carbon::instance($claimedAt) : now();
        $minutes = $this->minutesForCategory($ticket->category);

        return [
            'sla_minutes' => $minutes,
            'sla_due_at' => $claimedAt->copy()->addMinutes($minutes),
            'sla_stopped_at' => null,
            'sla_elapsed_seconds' => null,
        ];
    }

    /**
     * @return array{sla_stopped_at: \Illuminate\Support\Carbon, sla_elapsed_seconds: int}
     */
    public function stopPayload(TransferTicket $ticket, ?CarbonInterface $stoppedAt = null): array
    {
        $stoppedAt = $stoppedAt ? \Illuminate\Support\Carbon::instance($stoppedAt) : now();
        $start = $ticket->claimed_at ?? $stoppedAt;
        $elapsed = max(0, $start->diffInSeconds($stoppedAt));

        return [
            'sla_stopped_at' => $stoppedAt,
            'sla_elapsed_seconds' => $elapsed,
        ];
    }
}
