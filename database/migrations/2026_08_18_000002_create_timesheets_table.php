<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();
            $table->date('week_start');
            $table->date('week_end');

            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();

            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])
                ->default('draft');

            $table->timestamps();

            // One timesheet per user per week — entries live underneath it.
            $table->unique(['user_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheets');
    }
};
