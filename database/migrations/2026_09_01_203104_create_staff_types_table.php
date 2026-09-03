<?php
// database/migrations/[timestamp]_create_staff_types_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('staff_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Add staff_type_id to staff table
        Schema::table('staff', function (Blueprint $table) {
            $table->foreignId('staff_type_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('staff', function (Blueprint $table) {
            $table->dropForeign(['staff_type_id']);
            $table->dropColumn('staff_type_id');
        });
        
        Schema::dropIfExists('staff_types');
    }
};