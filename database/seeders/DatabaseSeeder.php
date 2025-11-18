<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Studio;
use App\Models\Film;
use App\Models\Jadwal;
use App\Models\Kursi;
use App\Models\Ticket;
use App\Models\Payment;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1️⃣ USERS (Admin, User, Kasir, Owner)
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'role' => 'admin',
            'phone' => '081234567890',
            'status' => 'active',
            'password' => Hash::make('password'),
        ]);

        $user = User::create([
            'name' => 'Ujang',
            'email' => 'user@gmail.com',
            'role' => 'user',
            'phone' => '081234567891',
            'status' => 'active',
            'password' => Hash::make('password'),
        ]);

        $cashier = User::create([
            'name' => 'Kasir Satu',
            'email' => 'kasir@gmail.com',
            'role' => 'cashier',
            'phone' => '081234567892',
            'shift' => 'Pagi',
            'status' => 'active',
            'password' => Hash::make('password'),
        ]);

        $owner = User::create([
            'name' => 'Owner Bioskop',
            'email' => 'owner@gmail.com',
            'role' => 'owner',
            'phone' => '081234567893',
            'status' => 'active',
            'password' => Hash::make('password'),
        ]);

        // 2️⃣ STUDIO
        $studioA = Studio::create([
            'studio' => 'Studio A',
            'capacity' => 120,
            'description' => 'Dolby Atmos Premium',
        ]);

        $studioB = Studio::create([
            'studio' => 'Studio B',
            'capacity' => 100,
            'description' => 'IMAX Experience',
        ]);

        $studioC = Studio::create([
            'studio' => 'Studio C',
            'capacity' => 80,
            'description' => '4DX Experience',
        ]);

        // 3️⃣ FILMS - GUNAKAN KOLOM STUDIO BARU
        $films = [
            [
                'title' => 'The Last Samurai', 
                'genre' => 'Action', 
                'duration' => 150, 
                'rating' => 8.7, 
                'release_date' => '2024-11-20', 
                'status' => 'playing',
                'studio' => 'Warner Bros Pictures', // Ubah dari studio_id menjadi studio
                'poster' => 'https://image.tmdb.org/t/p/w500/rypWkdJN3X2rH7WDRi48H5fDL5t.jpg',
                
            ],
            [
                'title' => 'Comedy of Life', 
                'genre' => 'Comedy', 
                'duration' => 95, 
                'rating' => 7.8, 
                'release_date' => '2024-11-15', 
                'status' => 'playing',
                'studio' => 'Universal Pictures',
                'poster' => 'https://image.tmdb.org/t/p/w500/8Gxv8gSFCU0XGDykEGv7zR1n2ua.jpg',
            ],
            [
                'title' => 'The Silent Lake', 
                'genre' => 'Horror', 
                'duration' => 110, 
                'rating' => 8.2, 
                'release_date' => '2024-12-01', 
                'status' => 'upcoming',
                'studio' => 'Blumhouse Productions',
                'poster' => 'https://image.tmdb.org/t/p/w500/8bcoRX3hQRHufLPSDREdvr3YMXx.jpg',
            ],
            [
                'title' => 'Space Odyssey', 
                'genre' => 'Sci-Fi', 
                'duration' => 140, 
                'rating' => 9.1, 
                'release_date' => '2024-11-25', 
                'status' => 'playing',
                'studio' => 'Marvel Studios',
                'poster' => 'https://image.tmdb.org/t/p/w500/or06FN3Dka5tukK1e9sl16pB3iy.jpg',
            ],
            
            
        ];

        foreach ($films as $filmData) {
            Film::create($filmData);
        }

        // 4️⃣ JADWAL - PERBAIKI KARENA SEKARANG TIDAK ADA STUDIO_ID DI FILMS
        $allFilms = Film::all();
        $studios = Studio::all();
        
        foreach ($allFilms as $film) {
            if ($film->status === 'playing') {
                // Buat 2-3 jadwal untuk film yang sedang tayang
                $showTimes = ['14:00:00', '17:00:00', '20:00:00'];
                for ($i = 0; $i < 2; $i++) {
                    // Pilih studio secara acak dari daftar studios
                    $randomStudio = $studios->random();
                    
                    Jadwal::create([
                        'film_id' => $film->id,
                        'studio_id' => $randomStudio->id,
                        'show_date' => now()->addDays($i)->format('Y-m-d'),
                        'show_time' => $showTimes[$i],
                        'price' => rand(45000, 85000),
                    ]);
                }
            }
        }

        // 5️⃣ KURSI untuk setiap studio
        $studios = Studio::all();
        foreach ($studios as $studio) {
            // Generate kursi untuk studio
            $rows = ceil($studio->capacity / 20);
            $rowLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L'];
            
            for ($i = 0; $i < $rows; $i++) {
                $currentRowSeats = ($i === $rows - 1) ? ($studio->capacity - ($i * 20)) : 20;
                
                for ($j = 1; $j <= $currentRowSeats; $j++) {
                    Kursi::create([
                        'studio_id' => $studio->id,
                        'kursi_no' => $rowLetters[$i] . $j,
                        'kursi_type' => ($i < 2) ? 'VIP' : 'Regular',
                        'status' => 'available',
                    ]);
                }
            }
        }

        echo "Semangat ya sayang ngoding nya 😘\n";
    }
}