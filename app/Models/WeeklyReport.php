<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyReport extends Model
{
    protected $fillable = [
        'project_id', 'consultant_id', 'week', 'activities', 'outputs',
        'client_interaction', 'issues', 'next_week', 'submitted_at', 'reviewed_by',
    ];

    protected $casts = [
        'week' => 'date',
        'submitted_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function consultant()
    {
        return $this->belongsTo(User::class, 'consultant_id');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
