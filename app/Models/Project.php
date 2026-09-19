<?php
// app/Models/Project.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'project_code', 
        'proposal_id', 
        'client_id', 
        'title', 
        'assignment_type',
        'assignment_lead_id', 
        'assignment_manager_id',
        'client_relationship_partner_id', 
        'project_value',
        'budget_hours', 
        'budget_cost',
        'planned_start', 
        'planned_end', 
        'actual_start', 
        'actual_end', 
        'status',
    ];

    protected $casts = [
        'planned_start' => 'date',
        'planned_end' => 'date',
        'actual_start' => 'date',
        'actual_end' => 'date',
        'budget_hours' => 'decimal:2',
        'budget_cost' => 'decimal:2',
        'project_value' => 'decimal:2',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function assignmentLead()
    {
        return $this->belongsTo(Staff::class, 'assignment_lead_id');
    }

    public function assignmentManager()
    {
        return $this->belongsTo(Staff::class, 'assignment_manager_id');
    }

    public function clientRelationshipPartner()
    {
        return $this->belongsTo(Staff::class, 'client_relationship_partner_id');
    }

    public function team()
    {
        return $this->hasMany(ProjectTeam::class);
    }

    public function members()
    {
        return $this->belongsToMany(Staff::class, 'project_team', 'project_id', 'staff_id')
            ->withPivot(['role', 'planned_hours', 'billable_rate'])
            ->withTimestamps();
    }

    // --- Phase 2 ---

    public function stages()
    {
        return $this->hasMany(ProjectStage::class)->orderBy('order');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function meetings()
    {
        return $this->hasMany(Meeting::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    // --- Phase 3 ---

    public function timeCodes()
    {
        return $this->hasMany(TimeCode::class);
    }

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function weeklyReports()
    {
        return $this->hasMany(WeeklyReport::class);
    }

    public function getActualBillableHoursAttribute(): float
    {
        return (float) $this->timeEntries()
            ->where('billable', true)
            ->where('approved', true)
            ->sum('hours');
    }
}