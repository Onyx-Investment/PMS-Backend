<?php
// database/migrations/[timestamp]_create_steps_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_level_id')->constrained()->onDelete('cascade');
            $table->integer('step_number');
            $table->decimal('cost_per_hour', 10, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->unique(['grade_level_id', 'step_number']);
        });

        // Add step_id to staff table
        Schema::table('staff', function (Blueprint $table) {
            $table->foreignId('step_id')->nullable()->after('grade_level_id')->constrained()->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropForeign(['step_id']);
            $table->dropColumn('step_id');
        });
        
        Schema::dropIfExists('steps');
    }
};