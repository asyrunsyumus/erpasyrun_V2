<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Pantauan Owner atas kerja Tim Gudang. Hanya membaca; tidak ada harga beli atau HPP. */
class WarehouseProgressController extends Controller
{
    private const ACTIONS = [
        'warehouse.receive' => 'Centang kitab dari Mesir',
        'warehouse.receive_arrival' => 'Centang restok / kelebihan belanja',
        'warehouse.finish' => 'Pesanan selesai dipacking',
    ];

    public function index(): Response
    {
        // Kiriman per jadwal bagasi (10 jadwal terakhir).
        $schedules = DB::table('inbound_lines as l')
            ->join('baggage_schedules as s', 's.id', '=', 'l.baggage_schedule_id')
            ->groupBy('s.id', 's.depart_date', 's.carrier_contact')
            ->orderByDesc('s.depart_date')->limit(10)
            ->get([
                's.id', 's.depart_date', 's.carrier_contact',
                DB::raw('COUNT(*) as total'),
                DB::raw('COUNT(l.received_at) as received'),
            ])
            ->map(fn ($r) => [
                'id' => $r->id,
                'schedule' => CarbonImmutable::parse($r->depart_date)->locale('id')->translatedFormat('j F Y'),
                'carrier' => $r->carrier_contact,
                'total' => (int) $r->total,
                'received' => (int) $r->received,
            ]);

        $arrivals = DB::table('purchase_arrivals')->whereNull('received_at')
            ->selectRaw('COUNT(*) as lines, COALESCE(SUM(qty),0) as qty')->first();

        // Pesanan yang sedang dipacking, dengan progres centang.
        $packingRows = DB::table('orders as o')->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->where('o.status', OrderStatus::SiapPacking->value)->whereNull('o.deleted_at')
            ->orderBy('o.status_changed_at')
            ->get(['o.id', 'o.number', 'c.name as customer', 'o.status_changed_at']);

        $progress = DB::table('order_items as i')
            ->leftJoin('packing_checks as k', 'k.order_item_id', '=', 'i.id')
            ->whereIn('i.order_id', $packingRows->pluck('id'))
            ->groupBy('i.order_id')
            ->get(['i.order_id', DB::raw('COUNT(*) as total'), DB::raw('COUNT(k.id) as checked')])
            ->keyBy('order_id');

        $labels = DB::table('shipping_labels')->whereIn('order_id', $packingRows->pluck('id'))->pluck('order_id')->flip();

        $packing = $packingRows->map(fn ($o) => [
            'id' => $o->id,
            'number' => $o->number,
            'customer' => $o->customer,
            'total' => (int) ($progress[$o->id]->total ?? 0),
            'checked' => (int) ($progress[$o->id]->checked ?? 0),
            'has_label' => isset($labels[$o->id]),
        ])->values();

        $doneToday = DB::table('orders')->where('status', OrderStatus::Selesai->value)
            ->where('status_changed_at', '>=', now()->startOfDay())->count();

        $activity = DB::table('audit_logs as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->where('a.action', 'like', 'warehouse.%')->orderByDesc('a.id')->limit(30)
            ->get(['a.action', 'a.created_at', 'u.name as user'])
            ->map(fn ($r) => [
                'what' => self::ACTIONS[$r->action] ?? $r->action,
                'user' => $r->user ?? '—',
                'at' => CarbonImmutable::parse($r->created_at)->locale('id')->translatedFormat('j M, H:i'),
            ]);

        return Inertia::render('warehouse/progress', [
            'schedules' => $schedules,
            'arrivals' => ['lines' => (int) $arrivals->lines, 'qty' => (int) $arrivals->qty],
            'packing' => $packing,
            'doneToday' => $doneToday,
            'activity' => $activity,
        ]);
    }
}
