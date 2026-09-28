<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Stamped the first time status -> in_progress
            $table->timestamp('started_at')->nullable()->after('status');

            // Stamped every time status -> done (cleared if reopened).
            // actual_hours already exists on this table — we just start
            // computing it instead of allowing it to be typed in.
            $table->timestamp('completed_at')->nullable()->after('started_at');
        });

        Schema::table('time_entries', function (Blueprint $table) {
            // A completed task's hours can only be logged once. MySQL/Postgres
            // both allow multiple NULLs through a unique index, so this is
            // safe for internal entries where task_id is null.
            $table->unique('task_id');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropUnique(['task_id']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'completed_at']);
        });
    }
};