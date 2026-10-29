<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_tickets', function (Blueprint $table) {
            $table->unsignedInteger('sla_minutes')->nullable()->after('claimed_at');
            $table->timestamp('sla_due_at')->nullable()->after('sla_minutes');
            $table->timestamp('sla_stopped_at')->nullable()->after('sla_due_at');
            $table->unsignedInteger('sla_elapsed_seconds')->nullable()->after('sla_stopped_at');
            $table->index('sla_due_at', 'tt_sla_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('transfer_tickets', function (Blueprint $table) {
            $table->dropIndex('tt_sla_due_idx');
            $table->dropColumn(['sla_minutes', 'sla_due_at', 'sla_stopped_at', 'sla_elapsed_seconds']);
        });
    }
};
