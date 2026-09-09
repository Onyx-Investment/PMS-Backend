<?php
// database/migrations/[timestamp]_remove_level_unique_from_grade_levels.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Drop the existing unique constraint on level
        Schema::table('grade_levels', function (Blueprint $table) {
            $table->dropUnique('grade_levels_level_unique');
        });
    }

    public function down()
    {
        // Add back the unique constraint on level
        Schema::table('grade_levels', function (Blueprint $table) {
            $table->unique('level');
        });
    }
};