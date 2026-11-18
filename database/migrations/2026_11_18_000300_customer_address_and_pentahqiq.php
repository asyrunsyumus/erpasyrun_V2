<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kelurahan, kecamatan, kode pos pelanggan; link Google Maps maktabah; kolom pentahqiq pada kitab. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->string('kelurahan', 120)->nullable()->after('city');
            $t->string('kecamatan', 120)->nullable()->after('kelurahan');
            $t->string('kode_pos', 10)->nullable()->after('kecamatan');
        });

        Schema::table('maktabahs', function (Blueprint $t) {
            $t->string('gmaps_url', 500)->nullable()->after('area');
        });

        Schema::table('products', function (Blueprint $t) {
            $t->string('pentahqiq', 255)->nullable()->after('publisher');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('pentahqiq'));
        Schema::table('maktabahs', fn (Blueprint $t) => $t->dropColumn('gmaps_url'));
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn(['kelurahan', 'kecamatan', 'kode_pos']));
    }
};
