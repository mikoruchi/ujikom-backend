<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kursi extends Model
{
    use HasFactory;

    protected $fillable = [
        'studio_id',
        'kursi_no',
        'kursi_type',  // regular, vip
        'status'       // available, maintenance
    ];

    protected $casts = [
        'kursi_type' => 'string',
        'status' => 'string'
    ];

    public function studio()
    {
        return $this->belongsTo(Studio::class);
    }
}