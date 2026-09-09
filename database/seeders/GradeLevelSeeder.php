<?php
// database/seeders/GradeLevelSeeder.php

namespace Database\Seeders;

use App\Models\GradeLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GradeLevelSeeder extends Seeder
{
    public function run()
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        // Clear existing grade levels
        GradeLevel::truncate();
        
        // Enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        
        $gradeLevels = [
            // Technical (TG1 - TG12)
            [
                'name' => 'General Manager', 
                'level' => 1, 
                'backend_code' => 'TG1', 
                'category' => 'technical', 
                'cost_per_hour' => 15000.00,
                'description' => 'General Manager - Technical'
            ],
            [
                'name' => 'Deputy General Manager', 
                'level' => 2, 
                'backend_code' => 'TG2', 
                'category' => 'technical', 
                'cost_per_hour' => 13000.00,
                'description' => 'Deputy General Manager - Technical'
            ],
            [
                'name' => 'Assistant General Manager', 
                'level' => 3, 
                'backend_code' => 'TG3', 
                'category' => 'technical', 
                'cost_per_hour' => 11000.00,
                'description' => 'Assistant General Manager - Technical'
            ],
            [
                'name' => 'Senior Manager', 
                'level' => 4, 
                'backend_code' => 'TG4', 
                'category' => 'technical', 
                'cost_per_hour' => 9000.00,
                'description' => 'Senior Manager - Technical'
            ],
            [
                'name' => 'Manager II', 
                'level' => 5, 
                'backend_code' => 'TG5', 
                'category' => 'technical', 
                'cost_per_hour' => 7500.00,
                'description' => 'Manager II - Technical'
            ],
            [
                'name' => 'Manager I', 
                'level' => 6, 
                'backend_code' => 'TG6', 
                'category' => 'technical', 
                'cost_per_hour' => 6500.00,
                'description' => 'Manager I - Technical'
            ],
            [
                'name' => 'Senior Associate / Senior Consultant', 
                'level' => 7, 
                'backend_code' => 'TG7', 
                'category' => 'technical', 
                'cost_per_hour' => 5500.00,
                'description' => 'Senior Associate / Senior Consultant - Technical'
            ],
            [
                'name' => 'Associate / Consultant', 
                'level' => 8, 
                'backend_code' => 'TG8', 
                'category' => 'technical', 
                'cost_per_hour' => 4500.00,
                'description' => 'Associate / Consultant - Technical'
            ],
            [
                'name' => 'Senior Analyst II / Senior Project Officer II', 
                'level' => 9, 
                'backend_code' => 'TG9', 
                'category' => 'technical', 
                'cost_per_hour' => 3500.00,
                'description' => 'Senior Analyst II / Senior Project Officer II - Technical'
            ],
            [
                'name' => 'Senior Analyst / Senior Project Officer I', 
                'level' => 10, 
                'backend_code' => 'TG10', 
                'category' => 'technical', 
                'cost_per_hour' => 3000.00,
                'description' => 'Senior Analyst / Senior Project Officer I - Technical'
            ],
            [
                'name' => 'Analyst II / Project Officer II', 
                'level' => 11, 
                'backend_code' => 'TG11', 
                'category' => 'technical', 
                'cost_per_hour' => 2500.00,
                'description' => 'Analyst II / Project Officer II - Technical'
            ],
            [
                'name' => 'Analyst / Project Officer', 
                'level' => 12, 
                'backend_code' => 'TG12', 
                'category' => 'technical', 
                'cost_per_hour' => 2000.00,
                'description' => 'Analyst / Project Officer - Technical'
            ],
            
            // Support (SG3 - SG12)
            [
                'name' => 'Assistant General Manager', 
                'level' => 3, 
                'backend_code' => 'SG3', 
                'category' => 'support', 
                'cost_per_hour' => 11000.00,
                'description' => 'Assistant General Manager - Support'
            ],
            [
                'name' => 'Senior Manager', 
                'level' => 4, 
                'backend_code' => 'SG4', 
                'category' => 'support', 
                'cost_per_hour' => 9000.00,
                'description' => 'Senior Manager - Support'
            ],
            [
                'name' => 'Manager II', 
                'level' => 5, 
                'backend_code' => 'SG5', 
                'category' => 'support', 
                'cost_per_hour' => 7500.00,
                'description' => 'Manager II - Support'
            ],
            [
                'name' => 'Manager I', 
                'level' => 6, 
                'backend_code' => 'SG6', 
                'category' => 'support', 
                'cost_per_hour' => 6500.00,
                'description' => 'Manager I - Support'
            ],
            [
                'name' => 'Assistant Manager II', 
                'level' => 7, 
                'backend_code' => 'SG7', 
                'category' => 'support', 
                'cost_per_hour' => 5500.00,
                'description' => 'Assistant Manager II - Support'
            ],
            [
                'name' => 'Assistant Manager I', 
                'level' => 8, 
                'backend_code' => 'SG8', 
                'category' => 'support', 
                'cost_per_hour' => 4500.00,
                'description' => 'Assistant Manager I - Support'
            ],
            [
                'name' => 'Senior Officer II', 
                'level' => 9, 
                'backend_code' => 'SG9', 
                'category' => 'support', 
                'cost_per_hour' => 3500.00,
                'description' => 'Senior Officer II - Support'
            ],
            [
                'name' => 'Senior Officer I', 
                'level' => 10, 
                'backend_code' => 'SG10', 
                'category' => 'support', 
                'cost_per_hour' => 3000.00,
                'description' => 'Senior Officer I - Support'
            ],
            [
                'name' => 'Officer II', 
                'level' => 11, 
                'backend_code' => 'SG11', 
                'category' => 'support', 
                'cost_per_hour' => 2500.00,
                'description' => 'Officer II - Support'
            ],
            [
                'name' => 'Officer I', 
                'level' => 12, 
                'backend_code' => 'SG12', 
                'category' => 'support', 
                'cost_per_hour' => 2000.00,
                'description' => 'Officer I - Support'
            ],
        ];

        foreach ($gradeLevels as $grade) {
            GradeLevel::create($grade);
        }
        
        $this->command->info('Grade levels seeded successfully!');
        $this->command->info('Total: ' . GradeLevel::count() . ' grade levels created.');
    }
}