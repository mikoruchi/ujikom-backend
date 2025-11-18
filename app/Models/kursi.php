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
        'kursi_type',
        'status'
    ];

    public function studio()
    {
        return $this->belongsTo(Studio::class);
    }
}