<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->enum('pay_basis', ['flat', 'percentage'])->nullable()->after('paid');
            $table->decimal('flat_allowance', 12, 2)->nullable()->after('pay_basis');
            $table->decimal('salary_percentage', 5, 2)->nullable()->after('flat_allowance');
        });
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn(['pay_basis', 'flat_allowance', 'salary_percentage']);
        });
    }
};