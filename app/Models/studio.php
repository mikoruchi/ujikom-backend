<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Studio extends Model
{
    use HasFactory;

    protected $fillable = ['studio','capacity','description'];

    public function films()
    {
        return $this->hasMany(Film::class);
    }

    public function jadwals()
    {
        return $this->hasMany(Jadwal::class);
    }

    public function kursis()
    {
        return $this->hasMany(Kursi::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
