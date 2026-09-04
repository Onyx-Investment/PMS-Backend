<?php
// app/Models/Timesheet.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Timesheet extends Model
{
    protected $fillable = [
        'user_id', 
        'week_start', 
        'week_end', 
        'submitted_at',
        'approved_by', 
        'approved_at', 
        'status',
        'review_notes',
    ];

    protected $casts = [
        'week_start' => 'date',
        'week_end' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected $appends = ['total_hours'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function entries()
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function getTotalHoursAttribute()
    {
        if ($this->relationLoaded('entries')) {
            return (float) $this->entries->sum('hours');
        }

        return (float) $this->entries()->sum('hours');
    }
}