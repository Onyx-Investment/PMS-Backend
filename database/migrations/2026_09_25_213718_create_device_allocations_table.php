<?php
// database/migrations/2026_09_25_000002_create_device_allocations_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();

            $table->timestamp('allocated_at');
            $table->foreignId('allocated_by')->constrained('users');
            $table->string('condition_at_allocation')->nullable(); // new, good, fair, poor
            $table->text('allocation_notes')->nullable();

            $table->timestamp('retrieved_at')->nullable();
            $table->foreignId('retrieved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('condition_at_retrieval')->nullable();
            $table->text('retrieval_notes')->nullable();

            // 'allocated' while the staff member still has it, 'retrieved'
            // once HR has gotten it back. Kept as an explicit column
            // (rather than inferred from retrieved_at being null) so
            // queries and indexes stay simple.
            $table->string('status')->default('allocated');

            $table->timestamps();

            $table->index(['device_id', 'status']);
            $table->index(['staff_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_allocations');
    }
};