<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'project_id', 'stage_id', 'parent_task_id', 'title', 'description',
        // assigned_to / planned_hours are LEGACY — see task_assignees pivot
        // migration for why they're kept but no longer the source of truth.
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

    // total_planned_hours is always included in API responses, so the
    // frontend never has to sum the assignees pivot itself.
    protected $appends = [
        'total_planned_hours',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function stage()
    {
        return $this->belongsTo(ProjectStage::class, 'stage_id');
    }

    /**
     * LEGACY single assignee. Kept in sync (best-effort, first assignee) by
     * TaskController for anything not yet updated to read assignees(), but
     * is no longer how a task's assignment is set or displayed.
     */
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Everyone assigned to this task, each with their own planned_hours on
     * the pivot. This is the source of truth for task assignment — a task
     * can have zero, one, or several assignees, none of them "primary".
     */
    public function assignees()
    {
        return $this->belongsToMany(User::class, 'task_assignees')
            ->withPivot('planned_hours')
            ->withTimestamps();
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
     * Sum of every assignee's planned_hours. Falls back to the legacy
     * planned_hours column for any task that (for whatever reason) still
     * has no rows in task_assignees, so older/unmigrated tasks don't show
     * as zero hours.
     *
     * Use this everywhere a "how many hours is this task" figure is
     * needed — never read the assigned_to/planned_hours columns directly.
     */
    public function getTotalPlannedHoursAttribute(): float
    {
        if ($this->relationLoaded('assignees')) {
            $sum = $this->assignees->sum(
                fn ($user) => (float) ($user->pivot->planned_hours ?? 0)
            );

            if ($sum > 0 || $this->assignees->isNotEmpty()) {
                return $sum;
            }
        } elseif ($this->exists) {
            $sum = (float) $this->assignees()->sum('task_assignees.planned_hours');

            if ($sum > 0 || $this->assignees()->exists()) {
                return $sum;
            }
        }

        return (float) ($this->planned_hours ?? 0);
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
     *
     * NOTE: actual_hours remains a single task-level figure (wall-clock
     * time from start to done) — it is not split per assignee.
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