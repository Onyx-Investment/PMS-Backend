<?php
// database/migrations/[timestamp]_add_review_notes_to_timesheets_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->text('review_notes')->nullable()->after('status');
        });
    }

    public function down()
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropColumn('review_notes');
        });
    }
};