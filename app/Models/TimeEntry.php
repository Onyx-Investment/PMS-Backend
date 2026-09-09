<?php
// app/Models/TimeEntry.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeEntry extends Model
{
    protected $fillable = [
        'timesheet_id', 
        'project_id', 
        'task_id', 
        'time_code_id',
        'internal_task_id',
        'date', 
        'hours', 
        'description', 
        'billable', 
        'approved',
    ];

    protected $casts = [
        'date' => 'date',
        'hours' => 'decimal:2',
        'billable' => 'boolean',
        'approved' => 'boolean',
    ];

    public function timesheet()
    {
        return $this->belongsTo(Timesheet::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function timeCode()
    {
        return $this->belongsTo(TimeCode::class);
    }

    public function internalTask()
    {
        return $this->belongsTo(InternalTask::class);
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }
}