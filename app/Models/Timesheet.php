<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Timesheet extends Model
{
    protected $fillable = [
        'user_id', 'week_start', 'week_end', 'submitted_at',
        'approved_by', 'approved_at', 'status',
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
        // Uses already-loaded entries when available to avoid an extra
        // query on every timesheet in a list; falls back to a query
        // when entries weren't eager-loaded.
        if ($this->relationLoaded('entries')) {
            return (float) $this->entries->sum('hours');
        }

        return (float) $this->entries()->sum('hours');
    }
}
