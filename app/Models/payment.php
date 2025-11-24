<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    // Field yang bisa diisi massal
    protected $fillable = [
        'film_id', 
        'user_id', 
        'jadwal_id',     // relasi ke jadwal
        'kursi',         // JSON array kursi ["E10","E11","E12"]
        'ticket_count',  // jumlah tiket
        'subtotal',      // total sebelum admin fee
        'total_amount',  // total yang dibayar
        'method', 
        'status',
    ];

    // Casting untuk tipe data
    protected $casts = [
        'kursi' => 'array',        // cast JSON ke array
        'subtotal' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Relasi ke user (siapa yang membayar)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi ke jadwal (film, studio, tanggal & jam)
     */
    public function jadwal()
    {
        return $this->belongsTo(Jadwal::class);
    }

    /**
     * Helper untuk menampilkan ringkasan kursi
     */
    public function kursiList()
    {
        return implode(', ', $this->kursi ?? []);
    }

    public function film()
{
    return $this->belongsTo(Film::class, 'film_id'); // pastikan ada kolom film_id di payments
}
}
