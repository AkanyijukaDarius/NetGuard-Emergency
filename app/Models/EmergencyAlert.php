<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class EmergencyAlert extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'incident_id',
        'user_id',
        'phone',
        'latitude',
        'longitude',
        'network_location',
        'reachability_status',
        'connectivity_type',
        'symptoms',
        'status',
        'responder_id',
        'response_time_minutes',
        'session_token',
        'is_anonymous',
        'idDocument',
        'givenName',
        'familyName',
        'cancelled_at',
        'cancelled_by',
        'dispatched_at',
        'resolved_at',
        'kyc_result',
        'kyc_verified',
        'sim_swap_flagged',



    ];

    protected $casts = [
        'network_location' => 'array',
        'latitude'         => 'decimal:8',
        'longitude'        => 'decimal:8',
        'cancelled_at'    => 'datetime',
        'deleted_at'      => 'datetime',
        'dispatched_at'   => 'datetime',
        'resolved_at'     => 'datetime',
        'kyc_result'      => 'array',
        'kyc_verified'    => 'boolean',
        'sim_swap_flagged' => 'boolean',
    ];

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }

    public function responder()
    {
        return $this->belongsTo(User::class, 'responder_id');
    }
    public function user()
{
    return $this->belongsTo(User::class);
}

public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'dispatched', 'in_progress'])
                     ->whereNull('deleted_at');
    }

    // Check if emergency can be cancelled
    public function canBeCancelled()
    {
        return in_array($this->status, ['pending', 'dispatched', 'resolved']) && is_null($this->deleted_at);
    }

}
