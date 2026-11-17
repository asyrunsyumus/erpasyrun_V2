<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Services\BaggageService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Hanya Owner (route). */
class BaggageController extends Controller
{
    public function index(BaggageService $svc): Response
    {
        $today = CarbonImmutable::today();

        $waiting = DB::table('orders as o')->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->where('o.status', OrderStatus::MenungguBagasi->value)->whereNull('o.deleted_at')
            ->orderBy('o.status_changed_at')
            ->get(['o.id', 'o.number', 'c.name as customer', 'o.status_changed_at'])
            ->map(fn ($o) => [
                'id' => $o->id,
                'number' => $o->number,
                'customer' => $o->customer,
                'grams' => $svc->orderWeight($o->id),
                'days' => $o->status_changed_at ? (int) CarbonImmutable::parse($o->status_changed_at)->diffInDays($today, true) : 0,
            ]);

        $schedules = DB::table('baggage_schedules')->orderByDesc('depart_date')->limit(60)->get()
            ->map(function ($s) use ($svc, $today) {
                $orders = DB::table('baggage_loads as l')->join('orders as o', 'o.id', '=', 'l.order_id')->join('customers as c', 'c.id', '=', 'o.customer_id')
                    ->where('l.baggage_schedule_id', $s->id)->orderBy('l.assigned_at')
                    ->get(['o.id', 'o.number', 'o.status', 'c.name as customer', 'l.weight_grams']);
                $lines = DB::table('inbound_lines')->where('baggage_schedule_id', $s->id);
                $date = CarbonImmutable::parse($s->depart_date);

                return [
                    'id' => $s->id,
                    'depart_date' => $s->depart_date,
                    'date_label' => $date->locale('id')->translatedFormat('l, j F Y'),
                    'days_left' => (int) $today->diffInDays($date, false),
                    'carrier_contact' => $s->carrier_contact,
                    'quota_grams' => $s->quota_grams,
                    'cost_idr' => (int) $s->cost_idr,
                    'used_grams' => $svc->usedGrams($s->id),
                    'orders' => $orders->map(fn ($o) => ['id' => $o->id, 'number' => $o->number, 'customer' => $o->customer, 'grams' => (int) $o->weight_grams, 'status' => $o->status]),
                    'lines_total' => (clone $lines)->count(),
                    'lines_received' => (clone $lines)->whereNotNull('received_at')->count(),
                ];
            });

        return Inertia::render('baggage/index', [
            'waiting' => $waiting,
            'waitingGrams' => $waiting->sum('grams'),
            'schedules' => $schedules,
            'today' => $today->toDateString(),
        ]);
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'depart_date' => ['required', 'date'],
            'carrier_contact' => ['required', 'string', 'max:160'],
            'quota_kg' => ['nullable', 'numeric', 'min:0.1', 'max:10000'],
            'cost_idr' => ['nullable', 'integer', 'min:0'],
        ], [
            'depart_date.required' => 'Isi tanggal keberangkatan.',
            'carrier_contact.required' => 'Isi kontak jasa bagasi.',
            'quota_kg.min' => 'Kuota minimal 0,1 kg, atau kosongkan untuk tanpa batas.',
        ]);

        return [
            'depart_date' => $d['depart_date'],
            'carrier_contact' => $d['carrier_contact'],
            'quota_grams' => $d['quota_kg'] === null || $d['quota_kg'] === '' ? null : (int) round($d['quota_kg'] * 1000),
            'cost_idr' => (int) ($d['cost_idr'] ?? 0),
        ];
    }

    public function store(Request $request, BaggageService $svc): RedirectResponse
    {
        $r = $svc->create($this->validated($request), $request->user());

        return back()->with('success', $r['assigned']
            ? 'Jadwal dibuat. '.count($r['assigned']).' pesanan otomatis dipasang: '.implode(', ', $r['assigned']).'.'
            : 'Jadwal dibuat. Belum ada pesanan yang menunggu atau muat di kuota ini.');
    }

    public function update(Request $request, int $schedule, BaggageService $svc): RedirectResponse
    {
        $r = $svc->update($schedule, $this->validated($request), $request->user());

        return back()->with('success', $r['assigned']
            ? 'Perubahan disimpan. '.count($r['assigned']).' pesanan tambahan dipasang: '.implode(', ', $r['assigned']).'.'
            : 'Perubahan disimpan.');
    }

    /** Cocokkan ulang semua pesanan yang menunggu ke jadwal terdekat (tombol manual). */
    public function match(Request $request, BaggageService $svc): RedirectResponse
    {
        $assigned = $svc->matchWaiting($request->user());

        return back()->with('success', $assigned
            ? count($assigned).' pesanan dicocokkan ke jadwal: '.implode(', ', $assigned).'.'
            : 'Tidak ada pesanan yang bisa dicocokkan. Cek kuota jadwal yang akan datang atau buat jadwal baru.');
    }

    public function move(Request $request, int $schedule, BaggageService $svc): RedirectResponse
    {
        $d = $request->validate(['target_id' => ['required', 'integer']], ['target_id.required' => 'Pilih jadwal tujuan.']);
        $n = $svc->moveAll($schedule, (int) $d['target_id'], $request->user());

        return back()->with('success', "{$n} pesanan dipindah. Jadwal lama sekarang kosong dan bisa dihapus.");
    }

    public function destroy(Request $request, int $schedule, BaggageService $svc): RedirectResponse
    {
        $svc->delete($schedule, $request->user());

        return back()->with('success', 'Jadwal dihapus.');
    }
}
