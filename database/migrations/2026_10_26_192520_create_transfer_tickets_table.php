<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_ref', 32)->unique();
            $table->string('category', 40); // fund_transfer | reversal_complaint
            $table->string('status', 40)->default('submitted')->index();
            $table->string('priority', 20)->default('normal');

            $table->foreignId('submitter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('assigned_officer_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('customer_account', 32);
            $table->string('customer_account_normalized', 32)->index();
            $table->decimal('amount', 18, 2);
            $table->string('amount_normalized', 40)->index();
            $table->dateTime('transaction_at')->nullable();
            $table->string('original_reference', 100)->nullable()->index();
            $table->string('original_reference_normalized', 100)->nullable()->index();
            $table->string('source_channel', 60)->nullable();
            $table->string('beneficiary_name')->nullable();
            $table->string('beneficiary_bank')->nullable();
            $table->string('beneficiary_account', 32)->nullable();
            $table->text('description')->nullable();
            $table->text('reversal_reason')->nullable();

            $table->boolean('confirmation_accepted')->default(false);
            $table->boolean('is_duplicate_flagged')->default(false);
            $table->boolean('duplicate_override')->default(false);
            $table->text('duplicate_override_reason')->nullable();
            $table->foreignId('duplicate_override_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('rejection_reason')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();

            $table->text('resolution_notes')->nullable();
            $table->string('processing_outcome', 80)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'status']);
            $table->index(['customer_account_normalized', 'amount_normalized']);
            $table->index(['assigned_officer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_tickets');
    }
};
