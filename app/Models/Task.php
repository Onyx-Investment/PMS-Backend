<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'project_id', 'stage_id', 'parent_task_id', 'title', 'description',
        'assigned_to', 'planned_hours', 'actual_hours', 'planned_start',
        'planned_end', 'priority', 'status', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'planned_start' => 'date',
        'planned_end' => 'date',
        'planned_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
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

    /**
     * Apply a new status, stamping started_at / completed_at and computing
     * actual_hours from elapsed time between them when the task finishes.
     *
     * started_at is only set the first time a task enters in_progress —
     * bouncing back and forth between review/in_progress won't reset the
     * clock. completed_at (and the hours derived from it) is recomputed
     * every time the task is marked done, and cleared if it's reopened so
     * a redo is timed cleanly.
     */
    public function applyStatus(string $newStatus): void
    {
        if ($newStatus === 'in_progress' && !$this->started_at) {
            $this->started_at = now();
        }

        if ($newStatus === 'done') {
            $this->completed_at = now();

            if ($this->started_at) {
                $this->actual_hours = round(
                    $this->started_at->diffInMinutes($this->completed_at) / 60,
                    2
                );
            }
        }

        if ($newStatus !== 'done' && $this->status === 'done') {
            $this->completed_at = null;
            $this->actual_hours = null;
        }

        $this->status = $newStatus;
    }
}