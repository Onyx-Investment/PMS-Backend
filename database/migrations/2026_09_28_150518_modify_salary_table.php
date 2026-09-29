<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * steps.salary used to hold a MONTHLY amount. It now holds an ANNUAL amount
 * (matching Staff::annual_salary / effective_annual_salary).
 *
 * cost_per_hour is recalculated on the annual basis (annual / 2080), the same
 * formula the Staff page uses, so the two pages agree.
 *
 * RUN ONCE ONLY. Running it twice would multiply salaries by 12 again.
 */
return new class extends Migration
{
    private const ANNUAL_WORK_HOURS = 2080;   // 52 weeks x 40 hrs
    private const MONTHLY_WORK_HOURS = 176;   // 22 days x 8 hrs (old basis)

    public function up(): void
    {
        DB::statement('UPDATE steps SET salary = salary * 12');

        DB::statement(
            'UPDATE steps SET cost_per_hour = ROUND(salary / ' . self::ANNUAL_WORK_HOURS . ', 2)'
        );
    }

    public function down(): void
    {
        DB::statement('UPDATE steps SET salary = salary / 12');

        DB::statement(
            'UPDATE steps SET cost_per_hour = ROUND(salary / ' . self::MONTHLY_WORK_HOURS . ', 2)'
        );
    }
};