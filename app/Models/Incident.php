<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    use HasFactory;

    protected $fillable = [
        'incident_code',
        'type',
        'severity',
        'description',
        'latitude',
        'longitude',
        'ai_triage',
        'kyc_result',
        'sim_swap_result',
        'qod_session_id',
        'status',
        'primary_responder_id',
        'resolved_at',
        'total_response_time_minutes'
    ];

    protected $casts = [
        'ai_triage'   => 'array',
        'sim_swap_result' => 'array',
        'kyc_result'  => 'array',
        'latitude'    => 'decimal:8',
        'longitude'   => 'decimal:8',
        'resolved_at' => 'datetime',
    ];

    public function alerts()
    {
        return $this->hasMany(EmergencyAlert::class);
    }

    public function primaryResponder()
    {
        return $this->belongsTo(User::class, 'primary_responder_id');
    }
}
