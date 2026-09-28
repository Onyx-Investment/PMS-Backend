<?php
// database/migrations/2026_09_25_000000_add_curtailment_and_payment_tracking_to_leave_requests_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // Curtailment
            $table->date('original_end_date')->nullable()->after('end_date');
            $table->decimal('original_days', 5, 2)->nullable()->after('days');
            $table->timestamp('curtailed_at')->nullable()->after('cancelled_at');
            $table->foreignId('curtailed_by')->nullable()->after('curtailed_at')->constrained('users')->nullOnDelete();
            $table->string('curtailment_notes')->nullable()->after('curtailed_by');

            // Payment tracking — separate from the approval workflow.
            // pay_amount is what HR's approval says is owed; payment_status
            // tracks whether Finance has actually disbursed it. Null when
            // there's no pay_amount at all (unpaid leave type, or pay not
            // configured).
            $table->string('payment_status')->nullable()->after('pay_amount'); // 'unpaid' | 'paid'
            $table->timestamp('paid_at')->nullable()->after('payment_status');
            $table->foreignId('paid_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
            $table->string('payment_notes')->nullable()->after('paid_by');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('curtailed_by');
            $table->dropConstrainedForeignId('paid_by');
            $table->dropColumn([
                'original_end_date', 'original_days', 'curtailed_at', 'curtailment_notes',
                'payment_status', 'paid_at', 'payment_notes',
            ]);
        });
    }
};