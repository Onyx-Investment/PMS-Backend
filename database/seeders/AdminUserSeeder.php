<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Staff;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure the "admin" role exists (create it if missing).
        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            [
                'name'        => 'Administrator',
                'description' => 'Full system access',
            ]
        );

        // 2. Create (or update) the admin user account.
        $user = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name'           => 'System',
                'last_name'            => 'Administrator',
                'phone'                => '+1234567890',
                'password'             => 'Admin@12345', // hashed by setPasswordAttribute mutator
                'is_active'            => true,
                'must_change_password' => false,
            ]
        );

        // 3. Create the linked staff record if it doesn't exist yet.
        $staff = Staff::updateOrCreate(
            ['user_id' => $user->id],
            [
                'employee_no'   => 'EMP-0001',
                'designation'   => 'System Administrator',
                'status'        => 'active',
                'is_active'     => true,
                'joined_date'   => now()->toDateString(),
            ]
        );

        // 4. Attach the admin role via the staff_role pivot (idempotent).
        $staff->roles()->syncWithoutDetaching([$adminRole->id]);

        $this->command->info('✅ Admin user created:');
        $this->command->line("   Email:    admin@example.com");
        $this->command->line("   Password: Admin@12345");
        $this->command->line("   Role:     admin");
    }
}