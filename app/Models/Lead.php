<?php
// app/Models/Lead.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'lead_code',
        'client_id', 
        'title', 
        'description', 
        'source',
        'estimated_value', 
        'probability', 
        'status', 
        'owner_id',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'probability' => 'integer',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function callReports()
    {
        return $this->hasMany(CallReport::class);
    }

    public function proposals()
    {
        return $this->hasMany(Proposal::class);
    }

    // Scope for searching by lead code
    public function scopeSearch($query, $search)
    {
        return $query->where('lead_code', 'like', "%{$search}%")
            ->orWhere('title', 'like', "%{$search}%");
    }
}