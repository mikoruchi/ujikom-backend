<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jadwal extends Model
{
    use HasFactory;

    protected $fillable = [
        'movie_id',
        'studio_id',
        'date',
        'time',
        'price'
    ];

    public function film()
    {
        return $this->belongsTo(Film::class);
    }
    public function movie()
    {
        return $this->belongsTo(Film::class);
    }

    public function studio()
    {
        return $this->belongsTo(Studio::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}