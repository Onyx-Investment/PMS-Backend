<?php
// app/Models/GradeLevel.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GradeLevel extends Model
{
    protected $fillable = [
        'name',
        'level',
        'cost_per_hour',
        'description',
    ];

    protected $casts = [
        'cost_per_hour' => 'decimal:2',
        'level' => 'integer',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}