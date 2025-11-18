<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('films', function (Blueprint $table) {
            // Hapus foreign key constraint dulu
            $table->dropForeign(['studio_id']);
            
            // Hapus kolom studio_id
            $table->dropColumn('studio_id');
            
            // Tambah kolom studio sebagai string
            $table->string('studio', 100)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('films', function (Blueprint $table) {
            // Untuk rollback, kembalikan ke keadaan semula
            $table->dropColumn('studio');
            $table->foreignId('studio_id')->constrained('studios');
        });
    }
};