<?php

use App\Database\PromoteAdminRole;
use Illuminate\Database\Migrations\Migration;

/**
 * Safe to re-run on nigeri45_transfer_nnmfb_portal. Renames the existing
 * full-access admin role in place and adds the limited Admin role.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new PromoteAdminRole)->up();
    }

    public function down(): void
    {
        (new PromoteAdminRole)->down();
    }
};
