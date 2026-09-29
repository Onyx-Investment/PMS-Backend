<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tasks can now have several assignees, each with their own planned hours
 * (no single "primary" assignee — see conversation for the reasoning).
 *
 * tasks.assigned_to and tasks.planned_hours are left in place but become
 * LEGACY / best-effort only:
 *   - assigned_to is kept in sync as the first row in task_assignees, purely
 *     so anything not yet updated to read the pivot (older mobile clients,
 *     ad-hoc reports, etc.) keeps working for single-assignee tasks.
 *   - planned_hours is no longer written to by new code. Task::totalPlannedHours
 *     (see updated Task model) sums the pivot instead and should be used
 *     everywhere going forward.
 *
 * up() also backfills every existing task with an assignee into the new
 * pivot, carrying its old planned_hours across as that one assignee's hours,
 * so nothing existing silently loses its hours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('planned_hours', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
        });

        // Backfill: every task that already has a single assigned_to becomes
        // that one assignee's row, carrying its planned_hours across.
        DB::statement(<<<SQL
            INSERT INTO task_assignees (task_id, user_id, planned_hours, created_at, updated_at)
            SELECT id, assigned_to, planned_hours, NOW(), NOW()
            FROM tasks
            WHERE assigned_to IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignees');
    }
};