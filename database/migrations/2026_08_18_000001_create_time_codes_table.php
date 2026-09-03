<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            // Nullable: a project-billable code points at a project;
            // internal codes (leave, business dev, admin) don't.
            $table->foreignId('project_id')->nullable()
                ->constrained('projects')->cascadeOnDelete();
            $table->enum('category', ['billable', 'non_billable', 'internal'])
                ->default('billable');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Seed the internal categories from the ONYX manual's
        // "internal work categories" list — these have no project_id.
        $codes = [
            ['code' => 'INT-RESEARCH', 'category' => 'internal', 'description' => 'Research'],
            ['code' => 'INT-MARKETING', 'category' => 'internal', 'description' => 'Marketing'],
            ['code' => 'INT-TRAINING', 'category' => 'internal', 'description' => 'Training'],
            ['code' => 'INT-IT', 'category' => 'internal', 'description' => 'IT'],
            ['code' => 'INT-RECRUITMENT', 'category' => 'internal', 'description' => 'Recruitment'],
            ['code' => 'INT-PROPOSAL', 'category' => 'internal', 'description' => 'Proposal writing'],
            ['code' => 'INT-WEBSITE', 'category' => 'internal', 'description' => 'Website'],
            ['code' => 'INT-LEAVE', 'category' => 'internal', 'description' => 'Leave'],
        ];

        $now = now();
        foreach ($codes as &$code) {
            $code['created_at'] = $now;
            $code['updated_at'] = $now;
        }

        \DB::table('time_codes')->insert($codes);
    }

    public function down(): void
    {
        Schema::dropIfExists('time_codes');
    }
};
