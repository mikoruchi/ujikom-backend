<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kasir_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('transaction_date')->useCurrent();
            $table->timestamps(); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kasir_transactions');
    }
};
