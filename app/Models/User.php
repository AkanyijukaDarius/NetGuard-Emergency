<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\EmergencyAlert;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable; // Added HasApiTokens here

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'given_name',
        'family_name',
        'id_document',
        'role',
        'is_kyc_verified'
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_kyc_verified' => 'boolean',
        ];
    }

    public function emergencies()
{
    return $this->hasMany(EmergencyAlert::class, 'user_id');
}
}
