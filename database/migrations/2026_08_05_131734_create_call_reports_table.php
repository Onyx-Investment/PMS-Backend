<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()
                ->constrained('leads')->nullOnDelete();
            $table->foreignId('client_id')
                ->constrained('clients')->cascadeOnDelete();
            $table->foreignId('staff_id')
                ->constrained('users')->cascadeOnDelete();

            $table->date('visit_date');
            $table->text('background')->nullable();
            $table->text('meeting_highlights')->nullable();
            $table->text('tasks')->nullable();
            $table->text('action_required')->nullable();
            $table->date('followup_date')->nullable();

            $table->enum('status', ['open', 'followed_up', 'closed'])
                ->default('open');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_reports');
    }
};