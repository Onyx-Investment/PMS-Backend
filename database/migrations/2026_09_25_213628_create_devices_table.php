<?php
// database/migrations/2026_09_25_000001_create_devices_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('asset_tag')->unique(); // e.g. DEV-2026-00001, auto-generated
            $table->string('category'); // laptop, phone, monitor, accessory, other
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable()->unique();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 12, 2)->nullable();
            // Derived/cached from the latest device_allocations row —
            // kept in sync by DeviceAllocationController, not hand-edited
            // except via DeviceController for admin corrections (e.g.
            // marking something lost or retired outright).
            $table->string('status')->default('available'); // available, assigned, under_repair, retired
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};