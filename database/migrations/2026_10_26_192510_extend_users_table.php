<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('staff_id', 64)->nullable()->unique()->after('email');
            $table->foreignId('branch_id')->nullable()->after('staff_id')->constrained('branches')->nullOnDelete();
            $table->string('phone', 32)->nullable()->after('branch_id');
            $table->boolean('is_active')->default(true)->after('phone');
            $table->boolean('is_demo')->default(false)->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('is_demo');
            $table->unsignedTinyInteger('failed_login_attempts')->default(0)->after('last_login_at');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn([
                'staff_id', 'phone', 'is_active', 'is_demo',
                'last_login_at', 'failed_login_attempts', 'locked_until',
            ]);
        });
    }
};
