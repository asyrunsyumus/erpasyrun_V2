<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restok gudang (kebutuhan stok tanpa pesanan) dan kedatangan belanja Mesir.
 * Stok baru bertambah setelah Tim Gudang mencentang barang diterima (bukan saat dibelanjakan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restock_needs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('qty_needed');
            $t->unsignedInteger('qty_fulfilled')->default(0);
            $t->enum('status', ['open', 'fulfilled', 'cancelled'])->default('open');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->index(['status', 'product_id', 'created_at']);
        });

        Schema::create('purchase_arrivals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('qty');
            $t->unsignedBigInteger('unit_cost_idr')->default(0);
            $t->enum('source', ['restok', 'lebih']);
            $t->foreignId('restock_need_id')->nullable()->constrained('restock_needs')->nullOnDelete();
            $t->foreignId('baggage_schedule_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('received_at')->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users');
            $t->timestamps();
            $t->index(['received_at', 'baggage_schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_arrivals');
        Schema::dropIfExists('restock_needs');
    }
};
