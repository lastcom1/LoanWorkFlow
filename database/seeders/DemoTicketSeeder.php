<?php

namespace Database\Seeders;

use App\Models\TransferTicket;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use App\Services\TicketService;
use Illuminate\Database\Seeder;

class DemoTicketSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Refusing DemoTicketSeeder while APP_ENV=production.');

            return;
        }

        $submitter = User::query()->where('email', 'submitter@nnmfb.demo')->first();
        if (! $submitter) {
            return;
        }

        /** @var TicketService $service */
        $service = app(TicketService::class);
        /** @var DuplicateDetectionService $dup */
        $dup = app(DuplicateDetectionService::class);

        $samples = [
            [
                'category' => TransferTicket::CATEGORY_FUND_TRANSFER,
                'customer_account' => '0123456789',
                'amount' => 250000.00,
                'beneficiary_name' => 'DEMO Beneficiary A',
                'beneficiary_bank' => 'Demo Commercial Bank',
                'beneficiary_account' => '9876543210',
                'description' => 'DEMO fund transfer instruction for supplier settlement.',
                'source_channel' => 'Branch Counter',
                'original_reference' => 'FT-DEMO-001',
            ],
            [
                'category' => TransferTicket::CATEGORY_REVERSAL,
                'customer_account' => '1122334455',
                'amount' => 75000.50,
                'original_reference' => 'REV-REF-DEMO-9001',
                'reversal_reason' => 'DEMO erroneous debit — customer dispute.',
                'description' => 'DEMO reversal complaint for failed NIP debit.',
                'source_channel' => 'Email',
            ],
            [
                'category' => TransferTicket::CATEGORY_FUND_TRANSFER,
                'customer_account' => '0123456789',
                'amount' => 250000.00,
                'beneficiary_name' => 'DEMO Beneficiary A Duplicate Check',
                'beneficiary_bank' => 'Demo Commercial Bank',
                'beneficiary_account' => '9876543210',
                'description' => 'DEMO intentional near-duplicate for account+amount warning.',
                'source_channel' => 'Branch Counter',
                'original_reference' => 'FT-DEMO-002',
                'acknowledge_duplicate' => true,
                'duplicate_override_reason' => 'DEMO: intentional duplicate sample for training.',
            ],
        ];

        foreach ($samples as $sample) {
            $exists = TransferTicket::query()
                ->where('description', $sample['description'])
                ->exists();
            if ($exists) {
                continue;
            }

            $payload = array_merge($sample, [
                'branch_id' => $submitter->branch_id,
                'confirmation_accepted' => true,
                'priority' => 'normal',
            ]);

            try {
                $service->create($submitter, $payload);
            } catch (\Throwable $e) {
                // Skip if duplicate acknowledgement path fails unexpectedly.
                $this->command?->warn('Demo ticket skipped: '.$e->getMessage());
            }
        }
    }
}
