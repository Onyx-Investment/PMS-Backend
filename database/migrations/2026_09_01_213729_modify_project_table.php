<?php
// database/migrations/[timestamp]_change_project_team_user_id_to_staff_id.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('project_team', function (Blueprint $table) {
            // Drop existing foreign key and column
            // $table->dropForeign(['user_id']);
            // $table->dropColumn('user_id');
            
            // Add staff_id column
            // $table->foreignId('staff_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->foreign('staff_id')->references('id')->on('staff')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('project_team', function (Blueprint $table) {
            $table->dropForeign(['staff_id']);
            $table->dropColumn('staff_id');
            
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
        });
    }
};