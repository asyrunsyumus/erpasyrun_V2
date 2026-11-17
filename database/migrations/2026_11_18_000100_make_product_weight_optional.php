<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tidak semua kitab sudah diketahui beratnya saat didaftarkan.
 * Berat boleh kosong di master; wajib diisi saat kitab itu pertama kali dipesan (lalu tersimpan ke master).
 * CHECK (weight_grams > 0) tetap berlaku untuk nilai yang diisi — NULL lolos CHECK.
 */
return new class extends Migration
{
    public function up(): void
    {
        $mysql = DB::getDriverName() === 'mysql';
        if ($mysql) {
            DB::statement('ALTER TABLE products DROP CHECK chk_weight_positive');
        }
        Schema::table('products', function (Blueprint $t) {
            $t->unsignedInteger('weight_grams')->nullable()->change();
        });
        if ($mysql) {
            DB::statement('ALTER TABLE products ADD CONSTRAINT chk_weight_positive CHECK (weight_grams IS NULL OR weight_grams > 0)');
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->unsignedInteger('weight_grams')->nullable(false)->change();
        });
    }
};
