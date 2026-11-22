<?php

namespace App\Console\Commands;

use App\Enums\Role;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Mengosongkan semua data uji coba: transaksi, master (kitab, maktabah, customer), stok, log,
 * dan semua akun selain Owner. Pengaturan toko dan akun Owner tetap.
 * Hanya dijalankan lewat terminal dengan --confirm. Jalankan backup dulu.
 */
class KosongkanData extends Command
{
    protected $signature = 'erp:kosongkan-data {--confirm : Wajib. Tanpa flag ini perintah tidak menghapus apa pun}';

    protected $description = 'Kosongkan semua data kecuali akun Owner dan pengaturan toko';

    /** Tabel yang dikosongkan seluruhnya. */
    private const TABLES = [
        'order_status_histories', 'invoices', 'shipping_labels', 'packing_checks', 'order_items', 'orders',
        'cancellations', 'purchase_allocations', 'purchase_arrivals', 'restock_needs', 'purchase_needs', 'purchases',
        'baggage_loads', 'inbound_lines', 'baggage_schedules',
        'stock_count_lines', 'stock_counts', 'stock_movements', 'product_stock',
        'expenses', 'notifications', 'audit_logs', 'imports',
        'product_maktabah', 'products', 'categories', 'customers', 'maktabahs',
        'sessions', 'password_reset_tokens',
    ];

    public function handle(): int
    {
        if (! $this->option('confirm')) {
            $this->error('Belum dijalankan. Tambahkan --confirm untuk benar-benar menghapus. Pastikan backup sudah diunduh.');

            return self::FAILURE;
        }

        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () {
                foreach (self::TABLES as $t) {
                    if (Schema::hasTable($t)) {
                        DB::table($t)->delete();
                    }
                }
                // Akun selain Owner dihapus; Owner tetap.
                DB::table('users')->where('role', '!=', Role::Owner->value)->delete();
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // Foto kitab dan file import yang tersimpan privat.
        foreach (['product-photos', 'imports'] as $dir) {
            if (Storage::disk('local')->exists($dir)) {
                Storage::disk('local')->deleteDirectory($dir);
            }
        }

        $this->info('Data dikosongkan. Akun Owner dan pengaturan toko tetap.');

        return self::SUCCESS;
    }
}
