<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code')->unique();
            // proposal_id left nullable/unconstrained here — proposals table
            // lands in Phase 4 (Business Development). Wire the FK then.
            $table->unsignedBigInteger('proposal_id')->nullable();

            $table->foreignId('client_id')
                ->constrained('clients')->cascadeOnDelete();

            $table->string('title');
            $table->enum('assignment_type', [
                'consulting', 'training', 'research', 'internal','m&e', 'transaction', 'advisory', 'investment',
            ])->default('consulting');

            $table->foreignId('assignment_lead_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('assignment_manager_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('client_relationship_partner_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->decimal('budget_hours', 10, 2)->nullable();
            $table->decimal('budget_cost', 14, 2)->nullable();

            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->date('actual_start')->nullable();
            $table->date('actual_end')->nullable();

            $table->enum('status', [
                'planned', 'active', 'on_hold', 'completed', 'cancelled',
            ])->default('planned');

            $table->timestamps();
        });

        // Many staff per project, with a role on that project and a
        // billable rate that may differ from their default rate.
        Schema::create('project_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();

            $table->enum('role', ['lead', 'am', 'consultant', 'reviewer'])
                ->default('consultant');

            $table->decimal('planned_hours', 10, 2)->nullable();
            $table->decimal('billable_rate', 12, 2)->nullable();

            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_team');
        Schema::dropIfExists('projects');
    }
};
