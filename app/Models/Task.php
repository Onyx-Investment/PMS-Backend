<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'project_id', 'stage_id', 'parent_task_id', 'title', 'description',
        'assigned_to', 'planned_hours', 'actual_hours', 'planned_start',
        'planned_end', 'priority', 'status',
    ];

    protected $casts = [
        'planned_start' => 'date',
        'planned_end' => 'date',
        'planned_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function stage()
    {
        return $this->belongsTo(ProjectStage::class, 'stage_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function parentTask()
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function subtasks()
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }
}
