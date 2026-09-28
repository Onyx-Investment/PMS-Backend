<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_levels', function (Blueprint $table) {
            // Default annual salary for staff on this grade. Distinct from
            // cost_per_hour (which is used for project/timesheet billing) —
            // this is the HR/leave-pay salary basis, and staff.annual_salary
            // overrides it per person.
            $table->decimal('annual_salary', 14, 2)->nullable()->after('cost_per_hour');
        });
    }

    public function down(): void
    {
        Schema::table('grade_levels', function (Blueprint $table) {
            $table->dropColumn('annual_salary');
        });
    }
};