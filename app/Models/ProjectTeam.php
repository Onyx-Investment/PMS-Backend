<?php
// app/Models/ProjectTeam.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTeam extends Model
{
    protected $fillable = [
        'project_id',
        'staff_id',
        'user_id',
        'role',
        'planned_hours',
        'billable_rate',
    ];

    public $table = 'project_team';

    protected $casts = [
        'planned_hours' => 'decimal:2',
        'billable_rate' => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}