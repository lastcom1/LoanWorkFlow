@props(['ticket'])

@if($ticket->hasSlaClock())
    @php
        $running = $ticket->slaIsRunning();
        $breached = $ticket->slaIsBreached();
        $near = $ticket->slaIsNear();
        $remaining = $ticket->slaRemainingSeconds();
        $elapsed = $ticket->slaElapsedSecondsResolved();
        $class = $breached ? 'sla-breached' : ($near ? 'sla-near' : 'sla-ok');
        if (! $running && $ticket->sla_stopped_at) {
            $class = $breached ? 'sla-breached' : 'sla-met';
        }
        $fmt = function (?int $seconds): string {
            if ($seconds === null) {
                return '—';
            }
            $neg = $seconds < 0;
            $seconds = abs($seconds);
            $h = intdiv($seconds, 3600);
            $m = intdiv($seconds % 3600, 60);
            $s = $seconds % 60;
            $out = $h > 0
                ? sprintf('%d:%02d:%02d', $h, $m, $s)
                : sprintf('%d:%02d', $m, $s);

            return ($neg ? '-' : '').$out;
        };
    @endphp
    <div
        class="sla-badge {{ $class }}"
        @if($running)
            data-sla-live="1"
            data-claimed-at="{{ $ticket->claimed_at->toIso8601String() }}"
            data-due-at="{{ $ticket->sla_due_at->toIso8601String() }}"
            data-minutes="{{ (int) $ticket->sla_minutes }}"
        @endif
    >
        <div class="sla-label">{{ $ticket->slaStatusLabel() }}</div>
        <div class="sla-times">
            <span class="sla-elapsed" title="Elapsed">⏱ {{ $fmt($elapsed) }}</span>
            @if($running)
                <span class="sla-remaining" title="Remaining">· left {{ $fmt($remaining) }}</span>
            @elseif($ticket->sla_stopped_at)
                <span class="sla-remaining" title="Recorded duration">· {{ $fmt($elapsed) }} total</span>
            @endif
        </div>
        <div class="sla-target muted">Target {{ (int) $ticket->sla_minutes }} min</div>
    </div>
@else
    <span class="muted">—</span>
@endif
