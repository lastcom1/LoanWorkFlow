<?php

namespace App\Services;

use App\Models\DuplicateMatchRecord;
use App\Models\TransferTicket;
use Illuminate\Support\Collection;

class DuplicateDetectionService
{
    /**
     * Normalize account number: digits only.
     */
    public function normalizeAccount(?string $account): string
    {
        return preg_replace('/\D+/', '', (string) $account) ?? '';
    }

    /**
     * Normalize amount to fixed 2-decimal string for exact matching.
     */
    public function normalizeAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * Normalize transaction/original reference for reversal duplicate checks.
     */
    public function normalizeReference(?string $reference): string
    {
        $ref = strtoupper(trim((string) $reference));
        $ref = preg_replace('/\s+/', '', $ref) ?? '';

        return $ref;
    }

    /**
     * Find potential duplicates for a ticket (or proposed attributes).
     *
     * Fund Transfer: normalized account + amount.
     * Reversal Complaint: original/transaction reference as primary key.
     *
     * @return Collection<int, TransferTicket>
     */
    public function findMatches(
        string $category,
        string $accountNormalized,
        string $amountNormalized,
        ?string $referenceNormalized = null,
        ?int $excludeTicketId = null,
    ): Collection {
        $query = TransferTicket::query()
            ->with(['branch', 'submitter', 'assignedOfficer'])
            ->where('category', $category);

        if ($excludeTicketId) {
            $query->where('id', '!=', $excludeTicketId);
        }

        if ($category === TransferTicket::CATEGORY_REVERSAL) {
            if ($referenceNormalized === null || $referenceNormalized === '') {
                return collect();
            }

            $query->where('original_reference_normalized', $referenceNormalized);
        } else {
            $query->where('customer_account_normalized', $accountNormalized)
                ->where('amount_normalized', $amountNormalized);
        }

        return $query->orderByDesc('created_at')->get();
    }

    /**
     * Persist duplicate match records for a newly submitted/updated ticket.
     *
     * @return Collection<int, DuplicateMatchRecord>
     */
    public function recordMatches(TransferTicket $ticket, Collection $matches): Collection
    {
        $records = collect();

        foreach ($matches as $match) {
            $matchType = $ticket->category === TransferTicket::CATEGORY_REVERSAL
                ? 'original_reference'
                : 'account_amount';

            $matchKey = $ticket->category === TransferTicket::CATEGORY_REVERSAL
                ? (string) $ticket->original_reference_normalized
                : $ticket->customer_account_normalized.'|'.$ticket->amount_normalized;

            $record = DuplicateMatchRecord::query()->updateOrCreate(
                [
                    'ticket_id' => $ticket->id,
                    'matched_ticket_id' => $match->id,
                    'match_type' => $matchType,
                ],
                [
                    'match_key' => $matchKey,
                    'match_snapshot' => [
                        'ticket_ref' => $match->ticket_ref,
                        'status' => $match->status,
                        'masked_account' => $match->maskedAccount(),
                        'amount' => $match->formattedAmount(),
                        'branch' => $match->branch?->name,
                        'submitter' => $match->submitter?->name,
                        'assigned_officer' => $match->assignedOfficer?->name,
                        'is_open' => $match->isOpen(),
                        'submitted_at' => optional($match->submitted_at)->toIso8601String(),
                    ],
                    'was_overridden' => (bool) $ticket->duplicate_override,
                ]
            );

            $records->push($record);
        }

        if ($records->isNotEmpty()) {
            $ticket->forceFill(['is_duplicate_flagged' => true])->save();
        }

        return $records;
    }
}
