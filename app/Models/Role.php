<?php
// app/Models/Role.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    public function staff()
    {
        return $this->belongsToMany(Staff::class, 'staff_role');
    }
}