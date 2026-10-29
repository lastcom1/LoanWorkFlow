<?php

namespace App\Services;

use App\Models\TicketAssignment;
use App\Models\TicketStatusHistory;
use App\Models\TransferTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TicketClaimService
{
    public function __construct(
        private AuditService $audit,
        private TicketNotificationService $notifications,
        private SlaService $sla,
    ) {}

    /**
     * Atomically claim a ticket for an FT officer using a row lock.
     *
     * @throws RuntimeException when claim fails
     */
    public function claim(TransferTicket $ticket, User $officer): TransferTicket
    {
        if (! $officer->hasRole('ft_officer')) {
            throw new RuntimeException('Only Fund Transfer Officers may claim tickets.');
        }

        if (! $officer->is_active) {
            throw new RuntimeException('Your account is inactive.');
        }

        try {
            $claimed = DB::transaction(function () use ($ticket, $officer) {
                /** @var TransferTicket|null $locked */
                $locked = TransferTicket::query()
                    ->whereKey($ticket->id)
                    ->lockForUpdate()
                    ->first();

                if (! $locked) {
                    throw new RuntimeException('Ticket not found.');
                }

                if (! $locked->isAvailableForClaim()) {
                    $assignee = $locked->assignedOfficer?->name ?? 'another officer';
                    $this->audit->log(
                        'claim_failed',
                        $officer,
                        $locked,
                        $locked,
                        null,
                        null,
                        [
                            'reason' => 'not_available',
                            'status' => $locked->status,
                            'assigned_officer_id' => $locked->assigned_officer_id,
                            'assigned_officer_name' => $assignee,
                        ]
                    );

                    throw new RuntimeException(
                        "This ticket is no longer available. It is assigned to {$assignee}."
                    );
                }

                $from = $locked->status;
                $claimedAt = now();
                $locked->forceFill(array_merge([
                    'assigned_officer_id' => $officer->id,
                    'status' => TransferTicket::STATUS_CLAIMED,
                    'claimed_at' => $claimedAt,
                ], $this->sla->startPayload($locked, $claimedAt)))->save();

                TicketAssignment::query()->create([
                    'ticket_id' => $locked->id,
                    'officer_id' => $officer->id,
                    'assigned_by' => $officer->id,
                    'action' => 'claimed',
                    'notes' => 'Exclusive claim by FT Officer',
                    'assigned_at' => now(),
                ]);

                TicketStatusHistory::query()->create([
                    'ticket_id' => $locked->id,
                    'changed_by' => $officer->id,
                    'from_status' => $from,
                    'to_status' => TransferTicket::STATUS_CLAIMED,
                    'reason' => 'Ticket claimed',
                ]);

                $this->audit->log(
                    'ticket_claimed',
                    $officer,
                    $locked,
                    $locked,
                    ['status' => $from, 'assigned_officer_id' => null],
                    ['status' => TransferTicket::STATUS_CLAIMED, 'assigned_officer_id' => $officer->id]
                );

                return $locked->fresh(['assignedOfficer', 'submitter', 'branch']);
            });
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->audit->log('claim_failed', $officer, $ticket, $ticket, null, null, [
                'reason' => 'exception',
                'message' => $e->getMessage(),
            ]);
            throw new RuntimeException('Unable to claim ticket due to a system error. Please retry.');
        }

        // Notifications after successful commit — failures must not undo claim.
        try {
            $this->notifications->ticketClaimed($claimed);
        } catch (\Throwable $e) {
            $this->audit->log('notification_failed', $officer, $claimed, $claimed, null, null, [
                'context' => 'ticket_claimed',
                'error' => $e->getMessage(),
            ]);
        }

        return $claimed;
    }
}
