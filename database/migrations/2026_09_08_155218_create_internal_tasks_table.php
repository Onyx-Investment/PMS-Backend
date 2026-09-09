<?php
// database/migrations/xxxx_xx_xx_create_internal_tasks_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInternalTasksTable extends Migration
{
    public function up()
    {
        Schema::create('internal_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('time_code_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['time_code_id', 'name']);
        });

        // Add internal_task_id to time_entries table
        Schema::table('time_entries', function (Blueprint $table) {
            $table->foreignId('internal_task_id')->nullable()->constrained()->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('internal_task_id');
        });

        Schema::dropIfExists('internal_tasks');
    }
}