<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Snapshotted at HR approval time, so a later change to the
            // leave type's rate or the staff's salary doesn't retroactively
            // change what was already approved.
            $table->decimal('pay_amount', 12, 2)->nullable()->after('days');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn('pay_amount');
        });
    }
};