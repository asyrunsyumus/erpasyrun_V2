<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Services\WarehouseService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Hanya Tim Gudang (route). Tidak ada harga beli, HPP, atau data hutang di sini. */
class WarehouseController extends Controller
{
    public function index(): Response
    {
        $inbound = DB::table('inbound_lines as l')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->join('baggage_schedules as s', 's.id', '=', 'l.baggage_schedule_id')
            ->leftJoin('maktabahs as m', 'm.id', '=', 'p.primary_maktabah_id')
            ->whereNull('l.received_at')
            ->orderBy('s.depart_date')->orderBy('p.title_latin')
            ->get(['l.id', 'l.qty', 'p.id as product_id', 'p.title_latin', 'p.title_ar', 'p.photo_path', 'm.name as maktabah', 's.id as schedule_id', 's.depart_date', 's.carrier_contact'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'schedule_id' => $r->schedule_id,
                'qty' => $r->qty,
                'product_id' => $r->product_id,
                'title_latin' => $r->title_latin,
                'title_ar' => $r->title_ar,
                'has_photo' => (bool) $r->photo_path,
                'maktabah' => $r->maktabah,
                'schedule' => CarbonImmutable::parse($r->depart_date)->locale('id')->translatedFormat('j F Y'),
                'carrier' => $r->carrier_contact,
            ]);

        $orders = DB::table('orders as o')->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->where('o.status', OrderStatus::SiapPacking->value)->whereNull('o.deleted_at')
            ->orderBy('c.name')->orderBy('o.status_changed_at')
            ->get(['o.id', 'o.number', 'o.channel', 'c.id as customer_id', 'c.name as customer', 'c.city', 'o.status_changed_at']);

        $items = DB::table('order_items as i')->join('products as p', 'p.id', '=', 'i.product_id')
            ->leftJoin('packing_checks as k', 'k.order_item_id', '=', 'i.id')
            ->whereIn('i.order_id', $orders->pluck('id'))
            ->get(['i.id', 'i.order_id', 'i.qty', 'p.id as product_id', 'p.title_latin', 'p.title_ar', 'p.photo_path', 'k.id as checked'])
            ->groupBy('order_id');
        $labels = DB::table('shipping_labels')->whereIn('order_id', $orders->pluck('id'))->pluck('order_id')->flip();

        $groups = $orders->groupBy('customer_id')->map(fn ($os) => [
            'customer' => $os->first()->customer,
            'city' => $os->first()->city,
            'orders' => $os->map(fn ($o) => [
                'id' => $o->id,
                'number' => $o->number,
                'channel' => $o->channel,
                'has_label' => isset($labels[$o->id]),
                'items' => ($items[$o->id] ?? collect())->map(fn ($i) => [
                    'id' => $i->id,
                    'qty' => $i->qty,
                    'product_id' => $i->product_id,
                    'title_latin' => $i->title_latin,
                    'title_ar' => $i->title_ar,
                    'has_photo' => (bool) $i->photo_path,
                    'checked' => (bool) $i->checked,
                ])->values(),
            ])->values(),
        ])->values();

        return Inertia::render('warehouse/index', [
            'inbound' => $inbound,
            'groups' => $groups,
            'packCount' => $orders->count(),
        ]);
    }

    public function receive(Request $request, int $line, WarehouseService $svc): RedirectResponse
    {
        $ready = $svc->receive($line, $request->user());

        return back()->with('success', 'Barang dikonfirmasi diterima.'.($ready ? ' Siap dipacking: '.implode(', ', $ready).'.' : ''));
    }

    /** Tandai semua kitab di satu jadwal bagasi sebagai diterima (checklist "centang semua"). */
    public function receiveAll(Request $request, int $schedule, WarehouseService $svc): RedirectResponse
    {
        $n = $svc->receiveSchedule($schedule, $request->user());

        return back()->with('success', $n ? "{$n} judul ditandai diterima." : 'Semua kitab di jadwal ini sudah dikonfirmasi.');
    }

    public function check(Request $request, int $item, WarehouseService $svc): RedirectResponse
    {
        $svc->toggleCheck($item, $request->boolean('checked'), $request->user());

        return back();
    }

    public function finish(Request $request, int $order, WarehouseService $svc): RedirectResponse
    {
        $svc->finish($order, $request->user());

        return back()->with('success', 'Pesanan selesai dipacking.');
    }
}
