<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveEntitlement extends Model
{
    protected $fillable = [
        'leave_type_id', 'grade_level_id', 'annual_days',
        'flat_allowance', 'salary_percentage',
    ];

    protected $casts = [
        'annual_days' => 'decimal:2',
        'flat_allowance' => 'decimal:2',
        'salary_percentage' => 'decimal:2',
    ];

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function gradeLevel()
    {
        return $this->belongsTo(GradeLevel::class);
    }
}