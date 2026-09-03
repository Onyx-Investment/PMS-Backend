<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'client_code', 'name', 'sector', 'address',
        'website', 'email', 'phone', 'status',
    ];

    public function contacts()
    {
        return $this->hasMany(ClientContact::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    // --- Phase 4 ---

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }
}