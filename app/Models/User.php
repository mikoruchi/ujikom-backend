<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'profile',
        'status',
        'shift' 
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    // Relasi tiket user
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    // Relasi kasir_transactions
    public function kasirTransactions()
    {
        return $this->hasMany(KasirTransaction::class);
    }

    // Relasi laporan
    public function laporan()
    {
        return $this->hasMany(Laporan::class, 'generated_by');
    }
}