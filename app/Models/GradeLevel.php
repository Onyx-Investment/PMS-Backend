<?php
// app/Models/GradeLevel.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeLevel extends Model
{
    protected $fillable = [
        'name',
        'level',
        'backend_code',
        'category',
        'description',
        // Default annual salary for staff on this grade — overridden per
        // person by Staff::annual_salary (see Staff::effective_annual_salary).
        'annual_salary',
    ];

    protected $casts = [
        'level' => 'integer',
        'annual_salary' => 'decimal:2',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function staff()
    {
        return $this->hasMany(Staff::class);
    }

    public function steps()
    {
        return $this->hasMany(Step::class)->orderBy('step_number');
    }
}