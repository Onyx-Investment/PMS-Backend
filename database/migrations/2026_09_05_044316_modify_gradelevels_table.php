<?php
// database/migrations/[timestamp]_update_grade_levels_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('grade_levels', function (Blueprint $table) {
            // Add backend_code column
            $table->string('backend_code')->nullable()->after('level');
            // Add category column
            $table->enum('category', ['technical', 'support'])->default('technical')->after('backend_code');
        });

        // Update existing records with backend codes
        $gradeLevels = [
            ['level' => 1, 'backend_code' => 'TG1', 'category' => 'technical'],
            ['level' => 2, 'backend_code' => 'TG2', 'category' => 'technical'],
            ['level' => 3, 'backend_code' => 'TG3', 'category' => 'technical'],
            ['level' => 4, 'backend_code' => 'TG4', 'category' => 'technical'],
            ['level' => 5, 'backend_code' => 'TG5', 'category' => 'technical'],
            ['level' => 6, 'backend_code' => 'TG6', 'category' => 'technical'],
            ['level' => 7, 'backend_code' => 'TG7', 'category' => 'technical'],
            ['level' => 8, 'backend_code' => 'TG8', 'category' => 'technical'],
            ['level' => 9, 'backend_code' => 'TG9', 'category' => 'technical'],
            ['level' => 10, 'backend_code' => 'TG10', 'category' => 'technical'],
            ['level' => 11, 'backend_code' => 'TG11', 'category' => 'technical'],
            ['level' => 12, 'backend_code' => 'TG12', 'category' => 'technical'],
        ];

        foreach ($gradeLevels as $grade) {
            \App\Models\GradeLevel::where('level', $grade['level'])->update([
                'backend_code' => $grade['backend_code'],
                'category' => $grade['category'],
            ]);
        }
    }

    public function down()
    {
        Schema::table('grade_levels', function (Blueprint $table) {
            $table->dropColumn(['backend_code', 'category']);
        });
    }
};