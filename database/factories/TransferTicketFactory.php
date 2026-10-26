<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\TransferTicket;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransferTicket>
 */
class TransferTicketFactory extends Factory
{
    public function definition(): array
    {
        $dup = app(DuplicateDetectionService::class);
        $account = fake()->numerify('##########');
        $amount = fake()->randomFloat(2, 1000, 500000);

        return [
            'ticket_ref' => 'TRP-'.now()->format('Ymd').'-'.strtoupper(fake()->unique()->bothify('??????')),
            'category' => TransferTicket::CATEGORY_FUND_TRANSFER,
            'status' => TransferTicket::STATUS_AVAILABLE,
            'priority' => 'normal',
            'submitter_id' => User::factory(),
            'branch_id' => Branch::factory(),
            'customer_account' => $account,
            'customer_account_normalized' => $dup->normalizeAccount($account),
            'amount' => $amount,
            'amount_normalized' => $dup->normalizeAmount($amount),
            'confirmation_accepted' => true,
            'submitted_at' => now(),
        ];
    }

    public function reversal(string $reference = 'REV-TEST-001'): static
    {
        return $this->state(function () use ($reference) {
            $dup = app(DuplicateDetectionService::class);

            return [
                'category' => TransferTicket::CATEGORY_REVERSAL,
                'original_reference' => $reference,
                'original_reference_normalized' => $dup->normalizeReference($reference),
                'reversal_reason' => 'Test reversal reason',
            ];
        });
    }
}
