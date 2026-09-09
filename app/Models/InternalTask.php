<?php
// app/Models/InternalTask.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalTask extends Model
{
    protected $fillable = [
        'time_code_id',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function timeCode()
    {
        return $this->belongsTo(TimeCode::class);
    }

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTimeCode($query, $timeCodeId)
    {
        return $query->where('time_code_id', $timeCodeId);
    }
}