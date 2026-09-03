<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')->cascadeOnDelete();
            $table->foreignId('stage_id')->nullable()
                ->constrained('project_stages')->nullOnDelete();

            // Self-referential FK for subtasks. Added after table creation
            // below since a column can't reference its own table mid-create.
            $table->foreignId('parent_task_id')->nullable();

            $table->string('title');
            $table->text('description')->nullable();

            $table->foreignId('assigned_to')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->decimal('planned_hours', 8, 2)->nullable();
            $table->decimal('actual_hours', 8, 2)->default(0);

            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();

            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])
                ->default('medium');

            $table->enum('status', ['todo', 'in_progress', 'review', 'done', 'blocked'])
                ->default('todo');

            $table->timestamps();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('parent_task_id')
                ->references('id')->on('tasks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['parent_task_id']);
        });
        Schema::dropIfExists('tasks');
    }
};
