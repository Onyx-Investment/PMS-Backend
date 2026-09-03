<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')->cascadeOnDelete();
            $table->foreignId('consultant_id')
                ->constrained('users')->cascadeOnDelete();

            $table->date('week');

            $table->text('activities')->nullable();
            $table->text('outputs')->nullable();
            $table->text('client_interaction')->nullable();
            $table->text('issues')->nullable();
            $table->text('next_week')->nullable();

            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['project_id', 'consultant_id', 'week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_reports');
    }
};
