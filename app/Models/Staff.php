<?php
// app/Models/Staff.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    protected $fillable = [
        'user_id',
        'employee_no',
        'designation',
        'grade_level_id',
        'step_id',
        'staff_manager_id',
        'staff_type_id',
        'status',
        'joined_date',
        'cost_per_hour',
        'annual_salary',
        'is_active',
        'nin',
        'gender',
        'marital_status',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'passport',
        
    ];

    protected $casts = [
        'joined_date' => 'date',
        'cost_per_hour' => 'decimal:2',
        'annual_salary' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Computed attributes always included in API responses, so the
    // frontend doesn't have to re-derive the grade-level fallback itself.
    protected $appends = [
        'effective_annual_salary',
    ];

    // --- Relations ---

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'staff_department');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'staff_role');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function gradeLevel()
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function step()
    {
        return $this->belongsTo(Step::class);
    }

    public function staffManager()
    {
        return $this->belongsTo(Staff::class, 'staff_manager_id');
    }

    public function staffType()
    {
        return $this->belongsTo(StaffType::class);
    }

    public function documents()
    {
        return $this->hasMany(StaffDocument::class);
    }

    

    public function directReports()
    {
        return $this->hasMany(Staff::class, 'staff_manager_id');
    }

    public function projectMemberships()
    {
        return $this->hasMany(ProjectTeam::class, 'staff_id');
    }

    // Phase 3
    public function timesheets()
    {
        return $this->hasMany(Timesheet::class, 'staff_id');
    }

    public function weeklyReports()
    {
        return $this->hasMany(WeeklyReport::class, 'consultant_id');
    }

    // --- Helpers ---

    public function getFullNameAttribute(): string
    {
        return $this->user?->full_name ?? '—';
    }

    public function getEffectiveCostPerHourAttribute()
    {
        return $this->cost_per_hour ?? $this->gradeLevel?->cost_per_hour ?? 0;
    }

    /**
     * This staff member's annual salary — their own annual_salary if one
     * has been set, otherwise falling back to their grade level's default.
     * Null if neither is configured (no grade level, or the grade level
     * has no default set either).
     */
// app/Models/Staff.php
public function getEffectiveAnnualSalaryAttribute(): ?float
{
    if ($this->annual_salary !== null) {
        return (float) $this->annual_salary;
    }

    // Matches the frontend's own priority: step's own salary, then the
    // step's cost_per_hour annualized, before falling back to the grade
    // level's default (kept only for older records with no step set).
    if ($this->step) {
        if ($this->step->salary !== null) {
            return (float) $this->step->salary;
        }
        if ($this->step->cost_per_hour) {
            return (float) $this->step->cost_per_hour * 2080;
        }
    }

    return $this->gradeLevel?->annual_salary !== null
        ? (float) $this->gradeLevel->annual_salary
        : null;
}

    public function getEmailAttribute()
    {
        return $this->user?->email;
    }

    public function getPhoneAttribute()
    {
        return $this->user?->phone;
    }

    public function getFirstNameAttribute()
    {
        return $this->user?->first_name;
    }

    public function getLastNameAttribute()
    {
        return $this->user?->last_name;
    }

    
}