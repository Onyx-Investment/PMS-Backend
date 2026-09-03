<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timesheet_id')
                ->constrained('timesheets')->cascadeOnDelete();

            $table->foreignId('project_id')->nullable()
                ->constrained('projects')->nullOnDelete();
            $table->foreignId('task_id')->nullable()
                ->constrained('tasks')->nullOnDelete();
            $table->foreignId('time_code_id')
                ->constrained('time_codes')->restrictOnDelete();

            $table->date('date');
            $table->decimal('hours', 5, 2);
            $table->string('description')->nullable();

            $table->boolean('billable')->default(true);
            $table->boolean('approved')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
