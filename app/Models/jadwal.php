<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jadwal extends Model
{
    use HasFactory;

    protected $fillable = [
        'film_id',
        'studio_id',
        'show_date',
        'show_time',
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

    
}