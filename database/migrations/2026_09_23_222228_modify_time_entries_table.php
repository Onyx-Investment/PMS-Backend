<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A project entry nulls out time_code_id/internal_task_id; an internal
     * entry nulls out project_id/task_id. All four have to allow NULL for
     * that to work. Requires doctrine/dbal for ->change() — if that's not
     * installed, run the raw ALTER TABLE statements in the comment below
     * instead.
     */
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->change();
            $table->unsignedBigInteger('task_id')->nullable()->change();
            $table->unsignedBigInteger('time_code_id')->nullable()->change();
            $table->unsignedBigInteger('internal_task_id')->nullable()->change();
        });

        // Raw fallback if doctrine/dbal isn't installed:
        //
        // ALTER TABLE time_entries MODIFY project_id BIGINT UNSIGNED NULL;
        // ALTER TABLE time_entries MODIFY task_id BIGINT UNSIGNED NULL;
        // ALTER TABLE time_entries MODIFY time_code_id BIGINT UNSIGNED NULL;
        // ALTER TABLE time_entries MODIFY internal_task_id BIGINT UNSIGNED NULL;
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
            $table->unsignedBigInteger('task_id')->nullable(false)->change();
            $table->unsignedBigInteger('time_code_id')->nullable(false)->change();
            $table->unsignedBigInteger('internal_task_id')->nullable(false)->change();
        });
    }
};