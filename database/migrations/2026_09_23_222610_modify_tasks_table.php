<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * actual_hours predates the time-tracking changes and was created as
     * NOT NULL. applyStatus() on Task needs to set it back to null when a
     * "done" task is reopened (so a redo gets timed cleanly), which fails
     * against a NOT NULL column. Requires doctrine/dbal for ->change() —
     * if that's not installed, run the raw ALTER TABLE below instead.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->decimal('actual_hours', 8, 2)->nullable()->change();
        });

        // Raw fallback if doctrine/dbal isn't installed:
        //
        // ALTER TABLE tasks MODIFY actual_hours DECIMAL(8,2) NULL;
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->decimal('actual_hours', 8, 2)->nullable(false)->default(0)->change();
        });
    }
};