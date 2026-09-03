<?php
// app/Models/StaffType.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffType extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function staff()
    {
        return $this->hasMany(Staff::class);
    }
}