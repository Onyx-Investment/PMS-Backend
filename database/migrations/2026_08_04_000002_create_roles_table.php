<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // slug used in code / middleware checks, e.g. 'assignment_lead'
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // Seed the core roles from the ONYX operating manual.
        // Slugs are what CheckRole middleware and frontend guards compare against.
        $roles = [
            ['name' => 'CEO', 'slug' => 'ceo'],
            ['name' => 'COO', 'slug' => 'coo'],
            ['name' => 'MD', 'slug' => 'md'],
            ['name' => 'Assignment Lead', 'slug' => 'assignment_lead'],
            ['name' => 'Assignment Manager', 'slug' => 'assignment_manager'],
            ['name' => 'Consultant', 'slug' => 'consultant'],
            ['name' => 'Client Relationship Partner', 'slug' => 'client_relationship_partner'],
            ['name' => 'Staff Manager', 'slug' => 'staff_manager'],
            ['name' => 'Finance', 'slug' => 'finance'],
            ['name' => 'HR', 'slug' => 'hr'],
            ['name' => 'Admin', 'slug' => 'admin'],
        ];

        $now = now();
        foreach ($roles as &$role) {
            $role['created_at'] = $now;
            $role['updated_at'] = $now;
        }

        \DB::table('roles')->insert($roles);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
