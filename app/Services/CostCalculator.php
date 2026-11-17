<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Modal (HPP) per kitab dalam pesanan — prinsip P4.
 *
 *   modal baris = (bagian dari stok × biaya rata-rata saat diambil)
 *               + (bagian dibeli × harga beli EGP × kurs, sesuai alokasi belanja)
 *               + (bagian dibeli × berat × ongkos bagasi per gram jadwalnya)
 *   unit_cost   = modal baris ÷ jumlah
 *
 * unit_cost baru diisi bila semua komponen sudah diketahui; selain itu null (belum final).
 */
class CostCalculator
{
    public function recomputeOrder(int $orderId): void
    {
        $perGram = $this->bagasiPerGram($orderId);

        $items = DB::table('order_items as i')->join('products as p', 'p.id', '=', 'i.product_id')
            ->where('i.order_id', $orderId)->get(['i.*', 'p.weight_grams']);

        foreach ($items as $i) {
            $cost = 0.0;
            $known = true;

            if ($i->qty_from_stock > 0) {
                $cost += (float) DB::table('stock_movements')->where('type', 'order_pick')->where('ref_type', 'order_item')->where('ref_id', $i->id)
                    ->sum(DB::raw('-qty * unit_cost_idr'));
            }

            if ($i->qty_to_buy > 0) {
                $alloc = DB::table('purchase_allocations as a')
                    ->join('purchase_needs as n', 'n.id', '=', 'a.purchase_need_id')
                    ->join('purchases as pu', 'pu.id', '=', 'a.purchase_id')
                    ->where('n.order_item_id', $i->id)
                    ->selectRaw('COALESCE(SUM(a.qty),0) as q, COALESCE(SUM(a.qty * pu.cost_idr / pu.qty),0) as c')
                    ->first();
                if ((int) $alloc->q < $i->qty_to_buy || $perGram === null) {
                    $known = false;
                } else {
                    $cost += (float) $alloc->c + $i->qty_to_buy * $i->weight_grams * $perGram;
                }
            }

            DB::table('order_items')->where('id', $i->id)->update([
                'unit_cost' => $known ? (int) round($cost / $i->qty) : null,
                'updated_at' => now(),
            ]);
        }
    }

    /** Ongkos bagasi per gram untuk jadwal pesanan ini; null bila belum terpasang jadwal. */
    private function bagasiPerGram(int $orderId): ?float
    {
        $sid = DB::table('baggage_loads')->where('order_id', $orderId)->value('baggage_schedule_id');
        if (! $sid) {
            return null;
        }
        $cost = (int) DB::table('baggage_schedules')->where('id', $sid)->value('cost_idr');
        $grams = (int) DB::table('baggage_loads')->where('baggage_schedule_id', $sid)->sum('weight_grams');

        return $grams > 0 ? $cost / $grams : 0.0;
    }

    public function recomputeSchedule(int $scheduleId): void
    {
        foreach (DB::table('baggage_loads')->where('baggage_schedule_id', $scheduleId)->pluck('order_id') as $oid) {
            $this->recomputeOrder((int) $oid);
        }
    }
}
