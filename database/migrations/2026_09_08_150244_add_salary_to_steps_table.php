<?php
// database/migrations/xxxx_xx_xx_add_salary_to_steps_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSalaryToStepsTable extends Migration
{
    public function up()
    {
        Schema::table('steps', function (Blueprint $table) {
            $table->decimal('salary', 15, 2)->after('step_number');
            // cost_per_hour should already exist
            $table->decimal('cost_per_hour', 15, 2)->after('salary')->change();
        });
    }

    public function down()
    {
        Schema::table('steps', function (Blueprint $table) {
            $table->dropColumn('salary');
        });
    }
}