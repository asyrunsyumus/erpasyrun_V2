<?php

namespace App\Http\Controllers;

use App\Services\FinanceMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan (hanya Owner). Semua angka ringkasan berasal dari FinanceMetrics,
 * jadi sama persis dengan Dashboard (perbaikan masalah lama "tiga layar, tiga angka").
 */
class ReportController extends Controller
{
    private const TABS = ['penjualan', 'margin', 'hutang', 'stok', 'reseller'];

    private function range(Request $r): array
    {
        $from = $r->date('from') ? CarbonImmutable::parse($r->date('from'))->startOfDay() : CarbonImmutable::now()->startOfMonth();
        $to = $r->date('to') ? CarbonImmutable::parse($r->date('to'))->endOfDay() : CarbonImmutable::now()->endOfDay();
        if ($from > $to) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [$from, $to];
    }

    public function index(Request $request, FinanceMetrics $f): Response
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'penjualan';
        [$from, $to] = $this->range($request);

        return Inertia::render('reports/index', [
            'tab' => $tab,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'summary' => [
                'sales' => $f->sales($from, $to),
                'orders' => $f->orderCount($from, $to),
                'cash' => $f->cashIn($from, $to),
                'cogs' => $f->cogs($from, $to),
                'expenses' => $f->expenses($from, $to),
                'profit' => $f->netProfit($from, $to),
                'receivable' => $f->receivables()['total'],
                'unfinal' => $f->unfinalItems($from, $to),
            ],
            'data' => $this->data($tab, $from, $to),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'penjualan';
        [$from, $to] = $this->range($request);
        $rows = $this->data($tab, $from, $to);
        $rows = $rows['rows'] ?? $rows;

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            if ($rows) {
                fputcsv($out, array_keys((array) $rows[0]));
                foreach ($rows as $r) {
                    // Cegah "formula injection" saat CSV dibuka di Excel (nama diawali = + - @).
                    fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v, (array) $r));
                }
            }
            fclose($out);
        }, "laporan-{$tab}-{$from->toDateString()}-{$to->toDateString()}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function data(string $tab, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return match ($tab) {
            'penjualan' => $this->sales($from, $to),
            'margin' => $this->margin($from, $to),
            'hutang' => $this->debts(),
            'stok' => $this->stock(),
            'reseller' => $this->resellers($from, $to),
        };
    }

    private function sales(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('orders as o')->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->whereNull('o.deleted_at')->where('o.status', '!=', 'dibatalkan')
            ->whereBetween('o.created_at', [$from, $to])
            ->orderBy('o.created_at')
            ->get(['o.number', 'o.created_at', 'c.name', 'c.type', 'o.channel', 'o.subtotal', 'o.discount', 'o.shipping_fee', 'o.total', 'o.payment_status', 'o.status'])
            ->map(fn ($o) => [
                'tanggal' => CarbonImmutable::parse($o->created_at)->toDateString(),
                'order' => $o->number,
                'pembeli' => $o->name,
                'tipe' => $o->type,
                'saluran' => $o->channel === 'shopee' ? 'Shopee' : 'WhatsApp',
                'subtotal' => (int) $o->subtotal,
                'diskon' => (int) $o->discount,
                'penjualan_bersih' => (int) $o->subtotal - (int) $o->discount,
                'ongkir' => (int) $o->shipping_fee,
                'total_tagihan' => (int) $o->total,
                'bayar' => ['paid' => 'Lunas', 'trusted' => 'Dibelikan dulu', 'unpaid' => 'Belum bayar'][$o->payment_status] ?? $o->payment_status,
                'status' => \App\Enums\OrderStatus::from($o->status)->label(),
            ]);

        $daily = $rows->groupBy('tanggal')->map(fn ($g, $d) => ['date' => $d, 'sales' => $g->sum('penjualan_bersih'), 'orders' => $g->count()])->values();

        return ['rows' => $rows->values()->all(), 'daily' => $daily];
    }

    /** Diskon tingkat pesanan dibagi ke tiap baris secara proporsional agar total = penjualan bersih. */
    private function margin(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('order_items as i')
            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->whereNull('o.deleted_at')->where('o.status', '!=', 'dibatalkan')
            ->whereBetween('o.created_at', [$from, $to])
            ->groupBy('p.id', 'p.sku', 'p.title_latin', 'p.title_ar')
            ->get([
                'p.sku', 'p.title_latin', 'p.title_ar',
                DB::raw('SUM(i.qty) as qty'),
                DB::raw('SUM(i.line_total - CASE WHEN o.subtotal > 0 THEN i.line_total * o.discount / o.subtotal ELSE 0 END) as net'),
                DB::raw('SUM(i.qty * COALESCE(i.unit_cost, 0)) as cost'),
                DB::raw('SUM(CASE WHEN i.unit_cost IS NULL THEN i.qty ELSE 0 END) as unfinal'),
            ])
            ->map(function ($r) {
                $net = (int) round($r->net);
                $cost = (int) $r->cost;

                return [
                    'sku' => $r->sku,
                    'kitab' => $r->title_latin,
                    'arab' => $r->title_ar,
                    'terjual' => (int) $r->qty,
                    'penjualan_bersih' => $net,
                    'modal' => $cost,
                    'untung' => $net - $cost,
                    'margin_persen' => $net > 0 ? round(($net - $cost) / $net * 100, 1) : 0,
                    'modal_belum_final' => (int) $r->unfinal,
                ];
            })
            ->sortByDesc('untung')->values();

        return ['rows' => $rows->all()];
    }

    private function debts(): array
    {
        $now = CarbonImmutable::now();
        $rows = DB::table('orders as o')->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->whereNull('o.deleted_at')->where('o.channel', 'whatsapp')
            ->whereIn('o.payment_status', ['unpaid', 'trusted'])->where('o.status', '!=', 'dibatalkan')
            ->orderBy('o.created_at')
            ->get(['o.id', 'o.number', 'o.created_at', 'c.name', 'c.phone', 'c.type', 'o.total', 'o.payment_status', 'o.status'])
            ->map(fn ($o) => [
                'order_id' => $o->id,
                'order' => $o->number,
                'tanggal' => CarbonImmutable::parse($o->created_at)->toDateString(),
                'umur_hari' => (int) CarbonImmutable::parse($o->created_at)->diffInDays($now, true),
                'pembeli' => $o->name,
                'telepon' => $o->phone,
                'tipe' => $o->type,
                'jenis' => $o->payment_status === 'trusted' ? 'dibelikan dulu' : 'belum bayar',
                'tagihan' => (int) $o->total,
            ]);

        return ['rows' => $rows->all()];
    }

    private function stock(): array
    {
        $rows = DB::table('product_stock as s')->join('products as p', 'p.id', '=', 's.product_id')
            ->whereNull('p.deleted_at')->where('s.qty', '!=', 0)
            ->orderByDesc(DB::raw('s.qty * s.avg_cost_idr'))
            ->get(['p.sku', 'p.title_latin', 'p.title_ar', 's.qty', 's.avg_cost_idr', 's.updated_at'])
            ->map(fn ($r) => [
                'sku' => $r->sku,
                'kitab' => $r->title_latin,
                'arab' => $r->title_ar,
                'stok' => (int) $r->qty,
                'modal_rata' => (int) $r->avg_cost_idr,
                'nilai_stok' => (int) $r->qty * (int) $r->avg_cost_idr,
                'terakhir_bergerak' => $r->updated_at ? CarbonImmutable::parse($r->updated_at)->toDateString() : null,
            ]);

        return ['rows' => $rows->all()];
    }

    private function resellers(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('customers as c')
            ->leftJoin('orders as o', fn ($j) => $j->on('o.customer_id', '=', 'c.id')->whereNull('o.deleted_at')->where('o.status', '!=', 'dibatalkan')->whereBetween('o.created_at', [$from, $to]))
            ->where('c.type', 'reseller')->whereNull('c.deleted_at')
            ->groupBy('c.id', 'c.name', 'c.city')
            ->get([
                'c.name', 'c.city',
                DB::raw('COUNT(o.id) as orders'),
                DB::raw('COALESCE(SUM(o.subtotal - o.discount),0) as sales'),
                DB::raw("COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total ELSE 0 END),0) as paid"),
                DB::raw("COALESCE(SUM(CASE WHEN o.payment_status != 'paid' THEN o.total ELSE 0 END),0) as unpaid"),
                DB::raw('MAX(o.created_at) as last_order'),
            ])
            ->map(fn ($r) => [
                'reseller' => $r->name,
                'kota' => $r->city,
                'jumlah_order' => (int) $r->orders,
                'penjualan_bersih' => (int) $r->sales,
                'sudah_bayar' => (int) $r->paid,
                'belum_bayar' => (int) $r->unpaid,
                'order_terakhir' => $r->last_order ? CarbonImmutable::parse($r->last_order)->toDateString() : null,
            ])
            ->sortByDesc('penjualan_bersih')->values();

        return ['rows' => $rows->all()];
    }
}
