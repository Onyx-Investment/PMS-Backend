<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 6, 2);
            $table->text('reason')->nullable();

            // pending_manager -> pending_hr -> approved, or rejected/cancelled at any point
            $table->enum('status', ['pending_manager', 'pending_hr', 'approved', 'rejected', 'cancelled'])
                ->default('pending_manager');

            // Snapshot of the approving manager at submission time (staff.staff_manager_id)
            $table->foreignId('manager_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->enum('manager_action', ['approved', 'rejected'])->nullable();
            $table->foreignId('manager_acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('manager_acted_at')->nullable();
            $table->text('manager_notes')->nullable();

            $table->enum('hr_action', ['approved', 'rejected'])->nullable();
            $table->foreignId('hr_acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('hr_acted_at')->nullable();
            $table->text('hr_notes')->nullable();

            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};