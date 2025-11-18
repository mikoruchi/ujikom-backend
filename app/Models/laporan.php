<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Laporan extends Model
{
    use HasFactory;

    protected $table = 'laporan';
    protected $fillable = ['generated_by','period_start','period_end','file_path'];

    public function user()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
