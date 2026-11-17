<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Services\FinanceMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** Tahap pesanan & batas wajar (hari) sebelum ditandai "tertahan". */
    private const STAGES = [
        'menunggu_bayar' => ['label' => 'Menunggu bayar', 'limit' => 3],
        'belanja_mesir' => ['label' => 'Belanja di Mesir', 'limit' => 5],
        'menunggu_bagasi' => ['label' => 'Menunggu bagasi', 'limit' => 5],
        'dalam_bagasi' => ['label' => 'Dalam perjalanan', 'limit' => 14],
        'siap_packing' => ['label' => 'Siap packing', 'limit' => 2],
    ];

    public function __invoke(Request $request, FinanceMetrics $finance): Response
    {
        $user = $request->user();

        $props = [
            'today' => now()->locale('id')->translatedFormat('l, j F Y'),
            'tasks' => $this->tasksFor($user->role),
        ];

        if ($user->hasRole(Role::Owner)) {
            $props += $this->ownerData($finance);
        } elseif ($user->hasRole(Role::Admin)) {
            $props['pipeline'] = $this->pipeline();
        }

        return Inertia::render('dashboard', $props);
    }

    /** Antrean tindakan per peran — hanya hal yang memang tugas peran itu. */
    private function tasksFor(Role $role): array
    {
        $tasks = [];

        if ($role === Role::Owner) {
            $waiting = DB::table('orders')->whereNull('deleted_at')->where('status', 'menunggu_bagasi');
            $oldest = (clone $waiting)->min('status_changed_at');
            $tasks[] = [
                'count' => (clone $waiting)->count(),
                'title' => 'Pesanan menunggu jadwal bagasi',
                'detail' => $oldest ? 'Yang paling lama sudah '.(int) CarbonImmutable::parse($oldest)->diffInDays(now()).' hari' : 'Tidak ada yang menunggu',
                'action' => 'Buat jadwal',
                'href' => '/baggage',
                'urgent' => true,
            ];
        }

        if (in_array($role, [Role::Owner, Role::Admin], true)) {
            $tasks[] = [
                'count' => DB::table('cancellations')->where('status', 'pending')->count(),
                'title' => 'Pengajuan pembatalan',
                'detail' => 'Perlu disetujui atau ditolak',
                'action' => 'Periksa',
                'href' => '/cancellations',
            ];
        }

        if ($role === Role::Admin) {
            $tasks[] = [
                'count' => DB::table('orders')->whereNull('deleted_at')->where('payment_status', 'unpaid')->where('channel', 'whatsapp')->whereNot('status', 'dibatalkan')->count(),
                'title' => 'Order belum dibayar',
                'detail' => 'Tagih pembeli, lalu tandai lunas',
                'action' => 'Lihat',
                'href' => '/orders',
            ];
            $tasks[] = [
                'count' => DB::table('orders')->whereNull('deleted_at')->whereNotIn('status', ['selesai', 'dibatalkan'])
                    ->whereNotExists(fn ($q) => $q->from('shipping_labels')->whereColumn('shipping_labels.order_id', 'orders.id'))->count(),
                'title' => 'Order belum ada resi',
                'detail' => 'Unggah resi sebelum packing',
                'action' => 'Unggah',
                'href' => '/orders',
            ];
        }

        if (in_array($role, [Role::Owner, Role::Mesir], true)) {
            $open = DB::table('purchase_needs')->where('status', 'open');
            $tasks[] = [
                'count' => (clone $open)->distinct('product_id')->count('product_id'),
                'title' => $role === Role::Mesir ? 'Kitab yang perlu dibeli' : 'Belanja Mesir belum terpenuhi',
                'detail' => (int) (clone $open)->sum(DB::raw('qty_needed - qty_fulfilled')).' buah total',
                'action' => 'Buka daftar',
                'href' => '/purchases',
            ];
        }

        if ($role === Role::Gudang) {
            $tasks[] = [
                'count' => DB::table('inbound_lines')->whereNull('received_at')->count(),
                'title' => 'Kiriman Mesir belum dikonfirmasi',
                'detail' => 'Cocokkan barang, lalu konfirmasi diterima',
                'action' => 'Terima barang',
                'href' => '/warehouse',
                'urgent' => true,
            ];
            $tasks[] = [
                'count' => DB::table('orders')->whereNull('deleted_at')->where('status', 'siap_packing')->count(),
                'title' => 'Pesanan siap dipacking',
                'detail' => 'Cetak resi, packing, tandai selesai',
                'action' => 'Mulai packing',
                'href' => '/warehouse',
            ];
        }

        if ($role === Role::Owner) {
            $tasks[] = [
                'count' => DB::table('stock_counts')->where('status', 'submitted')->count(),
                'title' => 'Stok tidak cocok',
                'detail' => 'Laporan hitung fisik dari gudang',
                'action' => 'Cocokkan',
                'href' => '/stock',
            ];
        }

        if ($role === Role::Owner) {
            // Barang bagasi yang jadwalnya sudah berangkat tapi belum dikonfirmasi gudang.
            $tasks[] = [
                'count' => DB::table('inbound_lines as l')->join('baggage_schedules as s', 's.id', '=', 'l.baggage_schedule_id')
                    ->whereNull('l.received_at')->where('s.depart_date', '<=', now()->toDateString())->count(),
                'title' => 'Barang dari Mesir belum diterima gudang',
                'detail' => 'Jadwal sudah berangkat, gudang belum mencentang',
                'action' => 'Lihat',
                'href' => '/baggage',
            ];
        }

        return $tasks;
    }

    private function pipeline(): array
    {
        $rows = DB::table('orders')
            ->whereNull('deleted_at')
            ->whereIn('status', array_keys(self::STAGES))
            ->get(['status', 'status_changed_at', 'created_at'])
            ->groupBy('status');

        $now = CarbonImmutable::now();
        $stages = [];
        foreach (self::STAGES as $key => $meta) {
            $items = $rows->get($key, collect());
            $ages = $items->map(fn ($o) => CarbonImmutable::parse($o->status_changed_at ?? $o->created_at)->diffInDays($now, true));
            $avg = $ages->isEmpty() ? 0 : (int) round($ages->avg());
            $stages[] = [
                'key' => $key,
                'label' => $meta['label'],
                'count' => $items->count(),
                'avg_days' => $avg,
                'oldest_days' => (int) ($ages->max() ?? 0),
                'slow' => $items->isNotEmpty() && $avg > $meta['limit'],
            ];
        }

        $monthStart = $now->startOfMonth();
        $done = DB::table('orders')->whereNull('deleted_at')->where('status', 'selesai')->where('status_changed_at', '>=', $monthStart)->get(['created_at', 'status_changed_at']);
        $stages[] = [
            'key' => 'selesai',
            'label' => 'Selesai bulan ini',
            'count' => $done->count(),
            'avg_days' => $done->isEmpty() ? 0 : (int) round($done->avg(fn ($o) => CarbonImmutable::parse($o->created_at)->diffInDays(CarbonImmutable::parse($o->status_changed_at), true))),
            'oldest_days' => 0,
            'slow' => false,
            'done' => true,
        ];

        return $stages;
    }

    private function ownerData(FinanceMetrics $f): array
    {
        $now = CarbonImmutable::now();
        $from = $now->startOfMonth();
        $prevFrom = $from->subMonth();
        // Bandingkan periode yang sama panjang: 1–17 Nov vs 1–17 Okt.
        $prevTo = $prevFrom->addDays($from->diffInDays($now, true))->endOfDay();

        $pct = fn (int $now, int $before) => $before === 0 ? null : (int) round(($now - $before) / abs($before) * 100);

        $sales = $f->sales($from, $now);
        $cash = $f->cashIn($from, $now);
        $profit = $f->netProfit($from, $now);
        $recv = $f->receivables();

        $series = [];
        for ($i = 5; $i >= 0; $i--) {
            $mStart = $now->startOfMonth()->subMonths($i);
            $mEnd = $i === 0 ? $now : $mStart->endOfMonth();
            $series[] = [
                'label' => $mStart->locale('id')->translatedFormat('M'),
                'sales' => $f->sales($mStart, $mEnd),
                'profit' => $f->netProfit($mStart, $mEnd),
            ];
        }

        $next = DB::table('baggage_schedules')->where('status', 'open')->where('depart_date', '>=', $now->toDateString())->orderBy('depart_date')->first();
        $baggage = null;
        if ($next) {
            $used = (int) DB::table('baggage_loads')->where('baggage_schedule_id', $next->id)->sum('weight_grams');
            $baggage = [
                'date' => CarbonImmutable::parse($next->depart_date)->locale('id')->translatedFormat('l, j F'),
                'days_left' => $now->startOfDay()->diffInDays(CarbonImmutable::parse($next->depart_date), true),
                'contact' => $next->carrier_contact,
                'used_grams' => $used,
                'quota_grams' => $next->quota_grams,
                'orders' => DB::table('baggage_loads')->where('baggage_schedule_id', $next->id)->count(),
            ];
        }
        $waitingGrams = (int) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.status', 'menunggu_bagasi')->whereNull('orders.deleted_at')
            ->sum(DB::raw('order_items.qty_to_buy * products.weight_grams'));

        $debtors = DB::table('orders')
            ->join('customers', 'customers.id', '=', 'orders.customer_id')
            ->whereNull('orders.deleted_at')->where('orders.channel', 'whatsapp')
            ->whereIn('orders.payment_status', ['unpaid', 'trusted'])->where('orders.status', '!=', 'dibatalkan')
            ->groupBy('customers.id', 'customers.name', 'customers.type')
            ->orderByDesc(DB::raw('SUM(orders.total)'))
            ->limit(5)
            ->get(['customers.name', 'customers.type', DB::raw('SUM(orders.total) as amount'), DB::raw('MIN(orders.created_at) as oldest')])
            ->map(fn ($r) => [
                'name' => $r->name,
                'type' => $r->type,
                'amount' => (int) $r->amount,
                'days' => CarbonImmutable::parse($r->oldest)->diffInDays($now, true),
            ]);

        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereNull('orders.deleted_at')->where('orders.status', '!=', 'dibatalkan')
            ->where('orders.created_at', '>=', $from)
            ->groupBy('products.id', 'products.title_ar', 'products.title_latin')
            ->orderByDesc(DB::raw('SUM(order_items.line_total - order_items.qty * COALESCE(order_items.unit_cost, 0))'))
            ->limit(5)
            ->get([
                'products.title_ar', 'products.title_latin',
                DB::raw('SUM(order_items.qty) as sold'),
                DB::raw('SUM(order_items.line_total) as revenue'),
                DB::raw('SUM(order_items.line_total - order_items.qty * COALESCE(order_items.unit_cost, 0)) as profit'),
            ])
            ->map(fn ($r) => [
                'title_ar' => $r->title_ar,
                'title_latin' => $r->title_latin,
                'sold' => (int) $r->sold,
                'profit' => (int) $r->profit,
                'margin' => $r->revenue > 0 ? (int) round($r->profit / $r->revenue * 100) : 0,
            ]);

        return [
            'period' => $from->locale('id')->translatedFormat('j').'–'.$now->locale('id')->translatedFormat('j F').' · dibandingkan '.$prevFrom->locale('id')->translatedFormat('F'),
            'kpis' => [
                'sales' => ['value' => $sales, 'change' => $pct($sales, $f->sales($prevFrom, $prevTo)), 'orders' => $f->orderCount($from, $now)],
                'cash' => ['value' => $cash, 'change' => $pct($cash, $f->cashIn($prevFrom, $prevTo))],
                'profit' => ['value' => $profit, 'change' => $pct($profit, $f->netProfit($prevFrom, $prevTo))],
                'receivable' => ['value' => $recv['total'], 'over_30' => $recv['buckets']['over_30'], 'customers' => $debtors->count()],
            ],
            'pipeline' => $this->pipeline(),
            'series' => $series,
            'baggage' => $baggage,
            'waiting_grams' => $waitingGrams,
            'waiting_orders' => DB::table('orders')->whereNull('deleted_at')->where('status', 'menunggu_bagasi')->count(),
            'debtors' => $debtors,
            'top_products' => $topProducts,
        ];
    }
}
