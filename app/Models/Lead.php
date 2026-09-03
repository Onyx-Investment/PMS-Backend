<?php
// app/Models/Lead.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'client_id', 'title', 'description', 'source',
        'estimated_value', 'probability', 'status', 'owner_id',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
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
}