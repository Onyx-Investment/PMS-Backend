<?php
// database/migrations/[timestamp]_create_call_report_attendees_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('call_report_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_report_id')->constrained()->onDelete('cascade');
            
            // Staff attendee (nullable for non-staff)
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            
            // Non-staff attendee fields
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('organization')->nullable();
            
            // Type and role
            $table->boolean('is_staff')->default(false);
            $table->string('role')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('call_report_id');
            $table->index('staff_id');
            $table->index('is_staff');
        });
    }

    public function down()
    {
        Schema::dropIfExists('call_report_attendees');
    }
};