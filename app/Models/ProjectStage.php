<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectStage extends Model
{
    protected $fillable = ['project_id', 'name', 'order', 'planned_start', 'planned_end', 'status'];

    protected $casts = [
        'planned_start' => 'date',
        'planned_end' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'stage_id');
    }
}
