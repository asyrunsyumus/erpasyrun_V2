<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * SATU-SATUNYA tempat angka keuangan dihitung (prinsip P3).
 * Dashboard, Laporan, dan export wajib memanggil kelas ini — jangan menulis rumus sendiri di tempat lain.
 *
 * Definisi (lihat docs/01-skema-database.md §4):
 *  - Penjualan   = Σ(subtotal − diskon) order tidak dibatalkan, menurut tanggal order. Ongkir tidak termasuk.
 *  - Uang masuk  = Σ total order lunas menurut tanggal bayar − refund yang disetujui.
 *  - HPP         = Σ qty × unit_cost (sudah termasuk bagian ongkos bagasi).
 *  - Untung bersih = Penjualan − HPP − biaya operasional − biaya lain.
 *    (Ongkos bagasi sudah masuk HPP, jadi biaya kategori "bagasi" tidak dikurangkan lagi.)
 */
class FinanceMetrics
{
    public function sales(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) DB::table('orders')
            ->whereNull('deleted_at')
            ->where('status', '!=', 'dibatalkan')
            ->whereBetween('created_at', [$from, $to])
            ->sum(DB::raw('subtotal - discount'));
    }

    public function orderCount(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return DB::table('orders')
            ->whereNull('deleted_at')
            ->where('status', '!=', 'dibatalkan')
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }

    public function cashIn(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $paid = (int) DB::table('orders')
            ->whereNull('deleted_at')
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to])
            ->sum('total');

        $refunds = (int) DB::table('cancellations')
            ->where('status', 'approved')
            ->whereBetween('decided_at', [$from, $to])
            ->sum('refund_amount');

        return $paid - $refunds;
    }

    public function cogs(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->where('orders.status', '!=', 'dibatalkan')
            ->whereBetween('orders.created_at', [$from, $to])
            ->sum(DB::raw('order_items.qty * COALESCE(order_items.unit_cost, 0)'));
    }

    /** Jumlah kitab terjual yang modalnya belum final (belum dibeli / belum masuk jadwal bagasi). */
    public function unfinalItems(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->where('orders.status', '!=', 'dibatalkan')
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereNull('order_items.unit_cost')
            ->sum('order_items.qty');
    }

    public function expenses(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) DB::table('expenses')
            ->whereIn('category', ['operasional', 'lain'])
            ->whereBetween('spent_at', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');
    }

    public function netProfit(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $this->sales($from, $to) - $this->cogs($from, $to) - $this->expenses($from, $to);
    }

    /** Total piutang hari ini, dikelompokkan per umur. */
    public function receivables(): array
    {
        $rows = DB::table('orders')
            ->whereNull('deleted_at')
            ->where('channel', 'whatsapp')
            ->whereIn('payment_status', ['unpaid', 'trusted'])
            ->whereNotIn('status', ['dibatalkan'])
            ->get(['total', 'created_at']);

        $now = CarbonImmutable::now();
        $buckets = ['0_7' => 0, '8_30' => 0, 'over_30' => 0];
        foreach ($rows as $r) {
            $age = CarbonImmutable::parse($r->created_at)->diffInDays($now);
            $key = $age <= 7 ? '0_7' : ($age <= 30 ? '8_30' : 'over_30');
            $buckets[$key] += (int) $r->total;
        }

        return [
            'total' => array_sum($buckets),
            'buckets' => $buckets,
        ];
    }
}
