<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('film_id')->constrained()->onDelete('cascade');  // siapa yang bayar
            $table->foreignId('user_id')->constrained()->onDelete('cascade');  // siapa yang bayar
            $table->foreignId('jadwal_id')->constrained('jadwals')->onDelete('cascade'); // jadwal film
            $table->json('kursi');            // array kursi ["E10","E11","E12"]
            $table->integer('ticket_count');  // jumlah tiket
            $table->decimal('subtotal', 10,2); 
            $table->decimal('total_amount', 10,2); 
            $table->string('method',50);      // metode pembayaran: cash, gopay, dll
            $table->enum('status',['pending','success','failed'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
