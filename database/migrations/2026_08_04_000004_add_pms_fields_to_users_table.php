<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_no')->unique()->nullable()->after('id');
            $table->string('phone')->nullable()->after('email');
            $table->string('grade')->nullable();
            $table->string('designation')->nullable();

            $table->foreignId('department_id')->nullable()
                ->constrained('departments')->nullOnDelete();

            $table->foreignId('role_id')->nullable()
                ->constrained('roles')->nullOnDelete();

            $table->foreignId('staff_manager_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->enum('status', ['active', 'inactive', 'on_leave'])
                ->default('active');

            $table->date('joined_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('role_id');
            $table->dropConstrainedForeignId('staff_manager_id');
            $table->dropColumn([
                'employee_no', 'phone', 'grade', 'designation',
                'status', 'joined_date',
            ]);
        });
    }
};
