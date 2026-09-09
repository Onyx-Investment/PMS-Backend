<?php
// app/Models/Step.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Step extends Model
{
    protected $fillable = [
        'grade_level_id',
        'step_number',
        'salary',
        'cost_per_hour',
        'description',
    ];

    protected $casts = [
        'step_number' => 'integer',
        'salary' => 'decimal:2',
        'cost_per_hour' => 'decimal:2',
    ];

    public function gradeLevel()
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function staff()
    {
        return $this->hasMany(Staff::class);
    }
}