<?php
// database/migrations/[timestamp]_create_staff_department_and_staff_role_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Staff Department pivot table
        Schema::create('staff_department', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->onDelete('cascade');
            $table->foreignId('department_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['staff_id', 'department_id']);
        });

        // Staff Role pivot table
        Schema::create('staff_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['staff_id', 'role_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('staff_role');
        Schema::dropIfExists('staff_department');
    }
};