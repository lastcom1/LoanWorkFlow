<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('transfer_tickets')->cascadeOnDelete();
            $table->foreignId('officer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40); // claimed, reassigned, released, escalated
            $table->text('notes')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamps();

            $table->index(['ticket_id', 'assigned_at']);
        });

        Schema::create('ticket_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('transfer_tickets')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('transfer_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->boolean('is_info_request')->default(false);
            $table->boolean('is_info_response')->default(false);
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('transfer_tickets')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();
        });

        Schema::create('duplicate_match_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('transfer_tickets')->cascadeOnDelete();
            $table->foreignId('matched_ticket_id')->constrained('transfer_tickets')->cascadeOnDelete();
            $table->string('match_type', 40); // account_amount | original_reference
            $table->string('match_key');
            $table->json('match_snapshot')->nullable();
            $table->boolean('was_overridden')->default(false);
            $table->timestamps();

            $table->unique(['ticket_id', 'matched_ticket_id', 'match_type'], 'dup_ticket_pair_type_unique');
            $table->index('match_key');
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ticket_id')->nullable()->constrained('transfer_tickets')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 40)->default('mail');
            $table->string('notification_type');
            $table->string('recipient_email');
            $table->string('subject')->nullable();
            $table->string('status', 40)->default('queued'); // queued, sent, failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->foreignId('ticket_id')->nullable()->constrained('transfer_tickets')->nullOnDelete();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('ticket_id');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group', 60)->default('general');
            $table->timestamps();
        });

        Schema::create('installer_locks', function (Blueprint $table) {
            $table->id();
            $table->boolean('installed')->default(false);
            $table->timestamp('installed_at')->nullable();
            $table->string('installed_by_email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installer_locks');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('duplicate_match_records');
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('ticket_status_histories');
        Schema::dropIfExists('ticket_assignments');
    }
};
