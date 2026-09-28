<?php
// app/Models/DeviceAllocation.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceAllocation extends Model
{
    protected $fillable = [
        'device_id', 'staff_id',
        'allocated_at', 'allocated_by', 'condition_at_allocation', 'allocation_notes',
        'retrieved_at', 'retrieved_by', 'condition_at_retrieval', 'retrieval_notes',
        'status',
    ];

    protected $casts = [
        'allocated_at' => 'datetime',
        'retrieved_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function allocatedBy()
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    public function retrievedBy()
    {
        return $this->belongsTo(User::class, 'retrieved_by');
    }
}