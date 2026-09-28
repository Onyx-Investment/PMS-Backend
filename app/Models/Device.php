<?php
// app/Models/Device.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = [
        'asset_tag', 'category', 'brand', 'model', 'serial_number',
        'purchase_date', 'purchase_cost', 'status', 'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_cost' => 'decimal:2',
    ];

    public function allocations()
    {
        return $this->hasMany(DeviceAllocation::class);
    }

    // The single currently-active allocation, if any — null when the
    // device is available, under repair, or retired.
    public function currentAllocation()
    {
        return $this->hasOne(DeviceAllocation::class)->where('status', 'allocated');
    }
}