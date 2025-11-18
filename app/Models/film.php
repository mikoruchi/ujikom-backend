<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Film extends Model
{
    use HasFactory;

    protected $table = 'films';

    protected $fillable = [
        'title',
        'genre', 
        'duration',
        'rating',
        'release_date',
        'trailer',
        'poster',
        'synopsis', // TAMBAHKAN
        'status',
        'studio', // Pastikan ini string
    ];

    protected $casts = [
        'release_date' => 'date',
        'rating' => 'float',
        'duration' => 'integer'
    ];

    public function jadwals()
    {
        return $this->hasMany(Jadwal::class);
    }
}