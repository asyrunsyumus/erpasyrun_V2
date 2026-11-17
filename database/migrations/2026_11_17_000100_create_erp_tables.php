<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Skema inti ERP — lihat docs/01-skema-database.md.
 * Semua uang: integer Rupiah. Harga EGP: integer piaster (1 EGP = 100).
 * Kurs disimpan × 10.000 (mis. 312,5 Rp/EGP → 3125000).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------- Pengaturan & audit ----------
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable();
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 64);
            $t->nullableMorphs('subject');
            $t->json('before')->nullable();
            $t->json('after')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });

        // ---------- Master data ----------
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });

        Schema::create('maktabahs', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('area')->nullable();
            $t->string('phone', 40)->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('sku', 40)->unique();
            $t->string('title_ar')->nullable();
            $t->string('title_latin');
            $t->string('author')->nullable();
            $t->string('publisher')->nullable();
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedInteger('weight_grams');
            $t->string('photo_path')->nullable();
            $t->foreignId('primary_maktabah_id')->nullable()->constrained('maktabahs')->restrictOnDelete();
            $t->unsignedInteger('price_egp')->default(0);
            $t->unsignedInteger('price_regular')->default(0);
            $t->unsignedInteger('price_reseller')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->index('title_latin');
        });

        Schema::create('product_maktabah', function (Blueprint $t) {
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('maktabah_id')->constrained()->restrictOnDelete();
            $t->primary(['product_id', 'maktabah_id']);
        });

        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('name');
            $t->string('phone', 40)->nullable();
            $t->text('address')->nullable();
            $t->string('city')->nullable();
            $t->enum('type', ['reguler', 'reseller'])->default('reguler');
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index('name');
        });

        Schema::create('imports', function (Blueprint $t) {
            $t->id();
            $t->enum('type', ['products', 'customers', 'maktabahs']);
            $t->string('file_path');
            $t->enum('status', ['preview', 'done', 'failed'])->default('preview');
            $t->unsignedInteger('rows_total')->default(0);
            $t->unsignedInteger('rows_ok')->default(0);
            $t->unsignedInteger('rows_failed')->default(0);
            $t->json('errors')->nullable();
            $t->foreignId('user_id')->constrained();
            $t->timestamps();
        });

        // ---------- Bagasi (dibuat duluan karena dirujuk) ----------
        Schema::create('baggage_schedules', function (Blueprint $t) {
            $t->id();
            $t->date('depart_date');
            $t->string('carrier_contact');
            $t->unsignedInteger('quota_grams')->nullable(); // null = tanpa batas
            $t->unsignedBigInteger('cost_idr')->default(0);
            $t->enum('status', ['open', 'departed', 'arrived'])->default('open');
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->index('depart_date');
        });

        // ---------- Penjualan ----------
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('number', 20)->unique();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->enum('channel', ['whatsapp', 'shopee']);
            $t->enum('payment_status', ['unpaid', 'trusted', 'paid']);
            $t->enum('status', [
                'menunggu_bayar', 'belanja_mesir', 'menunggu_bagasi', 'dalam_bagasi',
                'siap_packing', 'selesai', 'menunggu_pembatalan', 'dibatalkan',
            ])->index();
            $t->unsignedBigInteger('subtotal');
            $t->unsignedBigInteger('discount')->default(0);
            $t->unsignedBigInteger('shipping_fee')->default(0);
            $t->unsignedBigInteger('total');
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('status_changed_at')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamps();
            $t->softDeletes();
            $t->index('created_at');
        });

        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('qty');
            $t->unsignedBigInteger('unit_price');
            $t->unsignedBigInteger('line_total');
            $t->unsignedInteger('qty_from_stock')->default(0);
            $t->unsignedInteger('qty_to_buy')->default(0);
            $t->unsignedBigInteger('unit_cost')->nullable(); // HPP final per buah
            $t->timestamps();
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('number', 20)->unique();
            $t->date('issued_at');
            $t->date('due_at')->nullable();
            $t->timestamps();
        });

        Schema::create('shipping_labels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('file_path');
            $t->string('original_name');
            $t->foreignId('uploaded_by')->constrained('users');
            $t->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('from_status', 32)->nullable();
            $t->string('to_status', 32);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('note')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('cancellations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->text('reason');
            $t->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $t->string('status_before', 32);
            $t->foreignId('requested_by')->constrained('users');
            $t->foreignId('decided_by')->nullable()->constrained('users');
            $t->timestamp('decided_at')->nullable();
            $t->text('decision_note')->nullable();
            $t->unsignedBigInteger('refund_amount')->nullable();
            $t->string('refund_method')->nullable();
            $t->string('refund_proof_path')->nullable();
            $t->timestamps();
        });

        // ---------- Pembelian Mesir ----------
        Schema::create('purchase_needs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('qty_needed');
            $t->unsignedInteger('qty_fulfilled')->default(0);
            $t->enum('status', ['open', 'fulfilled', 'cancelled'])->default('open');
            $t->timestamps();
            $t->index(['status', 'product_id', 'created_at']);
        });

        Schema::create('purchases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->foreignId('maktabah_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('qty');
            $t->unsignedInteger('price_egp');
            $t->unsignedBigInteger('rate');
            $t->unsignedBigInteger('cost_idr'); // total qty × harga × kurs
            $t->date('purchased_at');
            $t->foreignId('user_id')->constrained();
            $t->foreignId('corrected_by')->nullable()->constrained('users');
            $t->timestamp('corrected_at')->nullable();
            $t->timestamps();
        });

        Schema::create('purchase_allocations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $t->foreignId('purchase_need_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('qty');
            $t->timestamps();
        });

        // ---------- Bagasi & gudang ----------
        Schema::create('baggage_loads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('baggage_schedule_id')->constrained()->restrictOnDelete();
            $t->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $t->unsignedInteger('weight_grams');
            $t->timestamp('assigned_at')->useCurrent();
        });

        Schema::create('inbound_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('baggage_schedule_id')->constrained()->restrictOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('qty');
            $t->timestamp('received_at')->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users');
            $t->timestamps();
            $t->unique(['baggage_schedule_id', 'product_id']);
        });

        Schema::create('packing_checks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_item_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('checked_by')->constrained('users');
            $t->timestamp('checked_at')->useCurrent();
        });

        // ---------- Stok ----------
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->integer('qty'); // + masuk, − keluar
            $t->enum('type', ['import_awal', 'purchase_surplus', 'order_pick', 'cancel_return', 'opname']);
            $t->unsignedBigInteger('unit_cost_idr')->default(0);
            $t->nullableMorphs('ref');
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('note')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['product_id', 'created_at']);
        });

        Schema::create('product_stock', function (Blueprint $t) {
            $t->foreignId('product_id')->primary()->constrained()->cascadeOnDelete();
            $t->integer('qty')->default(0);
            $t->unsignedBigInteger('avg_cost_idr')->default(0);
            $t->timestamp('updated_at')->nullable();
        });

        Schema::create('stock_counts', function (Blueprint $t) {
            $t->id();
            $t->enum('status', ['submitted', 'approved', 'rejected'])->default('submitted');
            $t->text('note')->nullable();
            $t->foreignId('counted_by')->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
        });

        Schema::create('stock_count_lines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->integer('system_qty');
            $t->integer('counted_qty');
        });

        // ---------- Keuangan ----------
        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->enum('category', ['operasional', 'bagasi', 'lain']);
            $t->unsignedBigInteger('amount');
            $t->date('spent_at')->index();
            $t->foreignId('baggage_schedule_id')->nullable()->constrained()->nullOnDelete();
            $t->string('note')->nullable();
            $t->string('attachment_path')->nullable();
            $t->foreignId('user_id')->constrained();
            $t->timestamps();
        });

        // Notifikasi per orang (bawaan Laravel)
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });

        // Berat wajib > 0 (P5). SQLite (dev/test) tidak mendukung ALTER ADD CONSTRAINT.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE products ADD CONSTRAINT chk_weight_positive CHECK (weight_grams > 0)');
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT chk_item_split CHECK (qty_from_stock + qty_to_buy = qty)');
        }
    }

    public function down(): void
    {
        foreach ([
            'notifications', 'expenses', 'stock_count_lines', 'stock_counts', 'product_stock', 'stock_movements',
            'packing_checks', 'inbound_lines', 'baggage_loads', 'purchase_allocations', 'purchases', 'purchase_needs',
            'cancellations', 'order_status_histories', 'shipping_labels', 'invoices', 'order_items', 'orders',
            'baggage_schedules', 'imports', 'customers', 'product_maktabah', 'products', 'maktabahs', 'categories',
            'audit_logs', 'settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
