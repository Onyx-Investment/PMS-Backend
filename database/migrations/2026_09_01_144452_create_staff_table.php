<?php
// database/migrations/[timestamp]_create_staff_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            
            // Link to auth user
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Employment details
            $table->string('employee_no')->unique();
            $table->string('grade')->nullable();
            $table->string('designation')->nullable();
            
            // Foreign keys
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grade_level_id')->nullable()->constrained()->nullOnDelete();
            
            // Management
            $table->foreignId('staff_manager_id')->nullable()->constrained('staff')->nullOnDelete();
            
            // Cost
            $table->decimal('cost_per_hour', 10, 2)->nullable();
            
            // Status
            $table->enum('status', ['active', 'inactive', 'on_leave'])->default('active');
            $table->boolean('is_active')->default(true);
            
            // Dates
            $table->date('joined_date')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['department_id', 'role_id']);
            $table->index('status');
            $table->index('employee_no');
        });
    }

    public function down()
    {
        Schema::dropIfExists('staff');
    }
};