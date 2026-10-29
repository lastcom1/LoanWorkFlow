<?php

namespace App\Services;

use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\TicketStatusHistory;
use App\Models\TransferTicket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TicketService
{
    public function __construct(
        private DuplicateDetectionService $duplicates,
        private AuditService $audit,
        private TicketNotificationService $notifications,
        private SlaService $sla,
    ) {}

    public function generateTicketRef(): string
    {
        do {
            $ref = 'TRP-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (TransferTicket::query()->where('ticket_ref', $ref)->exists());

        return $ref;
    }

    public function create(User $submitter, array $data, array $files = []): TransferTicket
    {
        $accountNorm = $this->duplicates->normalizeAccount($data['customer_account']);
        $amountNorm = $this->duplicates->normalizeAmount($data['amount']);
        $refNorm = $this->duplicates->normalizeReference($data['original_reference'] ?? null);

        if ($data['category'] === TransferTicket::CATEGORY_REVERSAL && $refNorm === '') {
            throw new RuntimeException('Reversal complaints require an original/transaction reference number.');
        }

        $matches = $this->duplicates->findMatches(
            $data['category'],
            $accountNorm,
            $amountNorm,
            $refNorm
        );

        if ($matches->isNotEmpty() && empty($data['acknowledge_duplicate'])) {
            throw new RuntimeException(
                'Potential duplicate detected. Review the warning and acknowledge before submitting, or contact a supervisor for override.'
            );
        }

        $ticket = DB::transaction(function () use ($submitter, $data, $files, $accountNorm, $amountNorm, $refNorm, $matches) {
            // Drafts removed: tickets are submitted as available immediately.
            $status = TransferTicket::STATUS_AVAILABLE;

            $ticket = TransferTicket::query()->create([
                'ticket_ref' => $this->generateTicketRef(),
                'category' => $data['category'],
                'status' => $status,
                'priority' => $data['priority'] ?? 'normal',
                'submitter_id' => $submitter->id,
                'branch_id' => $data['branch_id'] ?? $submitter->branch_id,
                'customer_account' => $data['customer_account'],
                'customer_account_normalized' => $accountNorm,
                'amount' => $amountNorm,
                'amount_normalized' => $amountNorm,
                'transaction_at' => $data['transaction_at'] ?? null,
                'original_reference' => $data['original_reference'] ?? null,
                'original_reference_normalized' => $refNorm ?: null,
                'source_channel' => $data['source_channel'] ?? null,
                'beneficiary_name' => $data['beneficiary_name'] ?? null,
                'beneficiary_bank' => $data['beneficiary_bank'] ?? null,
                'beneficiary_account' => $data['beneficiary_account'] ?? null,
                'description' => $data['description'] ?? null,
                'reversal_reason' => $data['reversal_reason'] ?? null,
                'confirmation_accepted' => (bool) ($data['confirmation_accepted'] ?? false),
                'is_duplicate_flagged' => $matches->isNotEmpty(),
                'duplicate_override' => (bool) ($data['acknowledge_duplicate'] ?? false),
                'duplicate_override_reason' => $data['duplicate_override_reason'] ?? null,
                'submitted_at' => now(),
            ]);

            TicketStatusHistory::query()->create([
                'ticket_id' => $ticket->id,
                'changed_by' => $submitter->id,
                'from_status' => null,
                'to_status' => $status,
                'reason' => 'Ticket submitted',
            ]);

            foreach ($files as $file) {
                if ($file instanceof UploadedFile) {
                    $this->storeAttachment($ticket, $submitter, $file);
                }
            }

            if ($matches->isNotEmpty()) {
                $this->duplicates->recordMatches($ticket, $matches);
            }

            $this->audit->log('ticket_submitted', $submitter, $ticket, $ticket, null, [
                'ticket_ref' => $ticket->ticket_ref,
                'category' => $ticket->category,
                'status' => $ticket->status,
                'duplicate_flagged' => $ticket->is_duplicate_flagged,
            ]);

            return $ticket->fresh(['branch', 'submitter', 'duplicateMatches.matchedTicket']);
        });

        try {
            $this->notifications->ticketSubmitted($ticket);
        } catch (\Throwable $e) {
            $this->audit->log('notification_failed', $submitter, $ticket, $ticket, null, null, [
                'context' => 'ticket_submitted',
                'error' => $e->getMessage(),
            ]);
        }

        return $ticket;
    }

    /**
     * Allowed status transitions after row lock.
     * Claimed → completed is intentionally disallowed (must start processing first).
     *
     * @return array<string, list<string>>
     */
    public function allowedTransitions(): array
    {
        return [
            TransferTicket::STATUS_CLAIMED => [
                TransferTicket::STATUS_IN_PROGRESS,
                TransferTicket::STATUS_INFO_REQUIRED,
                TransferTicket::STATUS_REJECTED,
            ],
            TransferTicket::STATUS_IN_PROGRESS => [
                TransferTicket::STATUS_INFO_REQUIRED,
                TransferTicket::STATUS_COMPLETED,
                TransferTicket::STATUS_REJECTED,
            ],
            TransferTicket::STATUS_INFO_REQUIRED => [
                TransferTicket::STATUS_IN_PROGRESS,
                TransferTicket::STATUS_COMPLETED,
                TransferTicket::STATUS_REJECTED,
            ],
        ];
    }

    public function assertTransitionAllowed(string $from, string $to): void
    {
        $allowed = $this->allowedTransitions()[$from] ?? [];
        if (! in_array($to, $allowed, true)) {
            throw new RuntimeException(
                "Invalid status transition from {$from} to {$to}."
            );
        }
    }

    public function transition(TransferTicket $ticket, User $actor, string $toStatus, ?string $reason = null, array $extra = []): TransferTicket
    {
        return DB::transaction(function () use ($ticket, $actor, $toStatus, $reason, $extra) {
            $locked = TransferTicket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            // Re-check after lock so concurrent complete+reject cannot both succeed.
            if (in_array($from, [TransferTicket::STATUS_COMPLETED, TransferTicket::STATUS_REJECTED], true)) {
                throw new RuntimeException('Ticket is already closed and cannot change status.');
            }

            $this->assertTransitionAllowed($from, $toStatus);

            $payload = array_merge(['status' => $toStatus], $extra);
            if ($toStatus === TransferTicket::STATUS_IN_PROGRESS && ! $locked->started_at) {
                $payload['started_at'] = now();
            }
            if ($toStatus === TransferTicket::STATUS_COMPLETED) {
                $payload['completed_at'] = now();
            }
            if ($toStatus === TransferTicket::STATUS_REJECTED) {
                $payload['rejected_at'] = now();
                $payload['rejected_by'] = $actor->id;
                $payload['rejection_reason'] = $reason;
            }
            if (in_array($toStatus, [TransferTicket::STATUS_COMPLETED, TransferTicket::STATUS_REJECTED], true)
                && $locked->claimed_at
                && ! $locked->sla_stopped_at) {
                $payload = array_merge($payload, $this->sla->stopPayload($locked));
            }

            $locked->forceFill($payload)->save();

            TicketStatusHistory::query()->create([
                'ticket_id' => $locked->id,
                'changed_by' => $actor->id,
                'from_status' => $from,
                'to_status' => $toStatus,
                'reason' => $reason,
            ]);

            $this->audit->log(
                'ticket_status_changed',
                $actor,
                $locked,
                $locked,
                ['status' => $from],
                ['status' => $toStatus, 'reason' => $reason]
            );

            $fresh = $locked->fresh(['submitter', 'assignedOfficer', 'branch']);

            try {
                $this->notifications->ticketStatusUpdated(
                    $fresh,
                    $reason ?: "Status changed to {$fresh->statusLabel()}"
                );
            } catch (\Throwable $e) {
                $this->audit->log('notification_failed', $actor, $fresh, $fresh, null, null, [
                    'context' => 'status_update',
                    'error' => $e->getMessage(),
                ]);
            }

            return $fresh;
        });
    }

    public function reassign(TransferTicket $ticket, User $actor, User $officer, string $reason): TransferTicket
    {
        return DB::transaction(function () use ($ticket, $actor, $officer, $reason) {
            $locked = TransferTicket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [TransferTicket::STATUS_COMPLETED, TransferTicket::STATUS_REJECTED], true)) {
                throw new RuntimeException('Cannot reassign a completed or rejected ticket.');
            }

            $fromOfficer = $locked->assigned_officer_id;
            $fromStatus = $locked->status;

            $locked->forceFill([
                'assigned_officer_id' => $officer->id,
                'status' => TransferTicket::STATUS_CLAIMED,
                'claimed_at' => now(),
            ])->save();

            $locked->assignments()->create([
                'officer_id' => $officer->id,
                'assigned_by' => $actor->id,
                'action' => 'reassigned',
                'notes' => $reason,
                'assigned_at' => now(),
            ]);

            TicketStatusHistory::query()->create([
                'ticket_id' => $locked->id,
                'changed_by' => $actor->id,
                'from_status' => $fromStatus,
                'to_status' => TransferTicket::STATUS_CLAIMED,
                'reason' => 'Reassigned: '.$reason,
            ]);

            $this->audit->log(
                'ticket_reassigned',
                $actor,
                $locked,
                $locked,
                ['assigned_officer_id' => $fromOfficer, 'status' => $fromStatus],
                ['assigned_officer_id' => $officer->id, 'status' => TransferTicket::STATUS_CLAIMED, 'reason' => $reason]
            );

            return $locked->fresh(['assignedOfficer', 'submitter', 'branch']);
        });
    }

    public function addComment(
        TransferTicket $ticket,
        User $actor,
        string $body,
        bool $internal = false,
        bool $infoRequest = false,
        bool $infoResponse = false,
    ): TicketComment {
        if ($internal && $actor->hasRole('submitter')) {
            throw new RuntimeException('Submitters cannot post internal notes.');
        }

        $comment = TicketComment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'body' => $body,
            'is_internal' => $internal,
            'is_info_request' => $infoRequest,
            'is_info_response' => $infoResponse,
        ]);

        $this->audit->log('ticket_comment_added', $actor, $ticket, $ticket, null, [
            'comment_id' => $comment->id,
            'is_internal' => $internal,
            'is_info_request' => $infoRequest,
        ]);

        return $comment;
    }

    public function storeAttachment(TransferTicket $ticket, User $actor, UploadedFile $file): TicketAttachment
    {
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, $allowed, true)) {
            throw new RuntimeException('Unsupported attachment type.');
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new RuntimeException('Attachment exceeds 5MB limit.');
        }

        $path = $file->store('attachments/'.$ticket->id, 'local');

        return TicketAttachment::query()->create([
            'ticket_id' => $ticket->id,
            'uploaded_by' => $actor->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
        ]);
    }
}
