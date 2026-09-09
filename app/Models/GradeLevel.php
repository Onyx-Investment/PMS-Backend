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
    ];

    protected $casts = [
        'level' => 'integer',
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