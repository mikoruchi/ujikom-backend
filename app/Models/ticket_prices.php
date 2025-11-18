<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ticket_prices extends Model
{
 protected $fillable = [
        'studio_id',
        'weekday_price',
        'weekend_price',
        'holiday_price',    
        'status',
    ];

    public function studio()
    {
        return $this->belongsTo(Studio::class);
    }
}
