<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('films', function (Blueprint $table) {
            $table->id();

            // Relasi ke tabel studios
            $table->foreignId('studio_id')
                  ->constrained('studios')
                  ->onUpdate('cascade')
                  ->onDelete('cascade');

            $table->string('title', 200);
            $table->string('genre', 100);
            $table->integer('duration');
            $table->float('rating')->default(0);
            $table->date('release_date')->nullable();
            $table->string('trailer')->nullable();
            $table->text('synopsis')->nullable();
            $table->string('poster')->nullable();
            $table->string('status', 50)->default('upcoming');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('films');
    }
};
