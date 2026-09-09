<?php
// app/Models/Department.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['name', 'description', 'head_of_department_id'];

    public function staff()
    {
        return $this->belongsToMany(Staff::class, 'staff_department');
    }

    public function headOfDepartment()
    {
        return $this->belongsTo(Staff::class, 'head_of_department_id');
    }

    // Sub-departments (children)
    public function subDepartments()
    {
        return $this->belongsToMany(
            Department::class,
            'department_parents',
            'parent_department_id',
            'child_department_id'
        );
    }

    // Parent departments
    public function parentDepartments()
    {
        return $this->belongsToMany(
            Department::class,
            'department_parents',
            'child_department_id',
            'parent_department_id'
        );
    }
}