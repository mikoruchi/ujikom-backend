<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ticket_prices', function (Blueprint $table) {
            $table->id();

            // Relasi studio
            $table->foreignId('studio_id')->constrained('studios')->onDelete('cascade');

            // Harga berdasarkan jenis hari
            $table->decimal('weekday_price', 10, 2);
            $table->decimal('weekend_price', 10, 2);
            $table->decimal('holiday_price', 10, 2);

            // Status aktif / tidak dipakai
            $table->enum('status', ['Active', 'Inactive'])->default('Active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_prices');
    }
};
