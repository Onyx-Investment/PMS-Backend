<?php
// database/migrations/xxxx_xx_xx_create_department_parents_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDepartmentParentsTable extends Migration
{
    public function up()
    {
        Schema::create('department_parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_department_id')->constrained('departments')->onDelete('cascade');
            $table->foreignId('parent_department_id')->constrained('departments')->onDelete('cascade');
            $table->timestamps();

            // Use a shorter unique key name
            $table->unique(['child_department_id', 'parent_department_id'], 'dept_parents_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('department_parents');
    }
}