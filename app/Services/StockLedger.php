<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * SATU-SATUNYA cara mengubah stok (prinsip P1).
 * Setiap perubahan = 1 baris stock_movements + pembaruan cache product_stock dalam transaksi yang sama.
 */
class StockLedger
{
    public function move(int $productId, int $qty, string $type, ?int $userId, int $unitCost = 0, ?string $refType = null, ?int $refId = null, ?string $note = null): void
    {
        if ($qty === 0) {
            return;
        }

        DB::transaction(function () use ($productId, $qty, $type, $userId, $unitCost, $refType, $refId, $note) {
            DB::table('product_stock')->insertOrIgnore(['product_id' => $productId, 'qty' => 0, 'avg_cost_idr' => 0, 'updated_at' => now()]);
            $row = DB::table('product_stock')->where('product_id', $productId)->lockForUpdate()->first();

            $newQty = $row->qty + $qty;

            // Biaya rata-rata bergerak: hanya berubah saat barang MASUK dengan biaya diketahui.
            $avg = $row->avg_cost_idr;
            if ($qty > 0 && $unitCost > 0) {
                $base = max(0, $row->qty);
                $avg = (int) round(($base * $row->avg_cost_idr + $qty * $unitCost) / ($base + $qty));
            }

            DB::table('stock_movements')->insert([
                'product_id' => $productId,
                'qty' => $qty,
                'type' => $type,
                'unit_cost_idr' => $unitCost,
                'ref_type' => $refType,
                'ref_id' => $refId,
                'user_id' => $userId,
                'note' => $note,
                'created_at' => now(),
            ]);

            DB::table('product_stock')->where('product_id', $productId)->update([
                'qty' => $newQty,
                'avg_cost_idr' => $avg,
                'updated_at' => now(),
            ]);
        });
    }
}
