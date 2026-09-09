<?php
// database/migrations/[timestamp]_add_fields_to_staff_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->string('nin')->nullable()->after('is_active');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('nin');
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable()->after('gender');
            $table->string('bank_name')->nullable()->after('marital_status');
            $table->string('bank_account_number')->nullable()->after('bank_name');
            $table->string('bank_account_name')->nullable()->after('bank_account_number');
            $table->string('passport')->nullable()->after('bank_account_name');
        });
    }

    public function down()
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropColumn(['nin', 'gender', 'marital_status', 'bank_name', 'bank_account_number', 'bank_account_name', 'passport']);
        });
    }
};