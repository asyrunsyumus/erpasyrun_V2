<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Jadwal bagasi (khusus Owner).
 *  - Pesanan "Menunggu bagasi" dipasang otomatis, dari yang PALING LAMA menunggu.
 *  - Berat = kitab yang dibeli di Mesir saja (yang dari gudang tidak ikut bagasi).
 *  - Kuota kosong = tanpa batas. Pesanan yang tidak muat dilewati, pesanan berikutnya yang lebih ringan tetap dicoba.
 */
class BaggageService
{
    public function __construct(private OrderService $orders, private CostCalculator $costs) {}

    public function orderWeight(int $orderId): int
    {
        return (int) DB::table('order_items as i')->join('products as p', 'p.id', '=', 'i.product_id')
            ->where('i.order_id', $orderId)->sum(DB::raw('i.qty_to_buy * p.weight_grams'));
    }

    public function usedGrams(int $scheduleId): int
    {
        return (int) DB::table('baggage_loads')->where('baggage_schedule_id', $scheduleId)->sum('weight_grams');
    }

    public function create(array $d, User $owner): array
    {
        return DB::transaction(function () use ($d, $owner) {
            $id = DB::table('baggage_schedules')->insertGetId([
                'depart_date' => $d['depart_date'],
                'carrier_contact' => $d['carrier_contact'],
                'quota_grams' => $d['quota_grams'],
                'cost_idr' => $d['cost_idr'] ?? 0,
                'status' => 'open',
                'created_by' => $owner->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Audit::log('baggage.create', null, null, ['schedule_id' => $id] + $d);

            return ['id' => $id, 'assigned' => $this->fill($id, $owner)];
        });
    }

    public function update(int $id, array $d, User $owner): array
    {
        return DB::transaction(function () use ($id, $d, $owner) {
            $s = DB::table('baggage_schedules')->where('id', $id)->lockForUpdate()->first();
            abort_unless($s, 404);
            $used = $this->usedGrams($id);
            if ($d['quota_grams'] !== null && $d['quota_grams'] < $used) {
                throw ValidationException::withMessages(['quota_kg' => 'Kuota tidak boleh lebih kecil dari yang sudah terpakai ('.round($used / 1000, 1).' kg). Pindahkan dulu sebagian pesanan.']);
            }
            DB::table('baggage_schedules')->where('id', $id)->update([
                'depart_date' => $d['depart_date'],
                'carrier_contact' => $d['carrier_contact'],
                'quota_grams' => $d['quota_grams'],
                'cost_idr' => $d['cost_idr'] ?? 0,
                'updated_at' => now(),
            ]);
            Audit::log('baggage.update', null, (array) $s, $d + ['schedule_id' => $id]);

            // Kuota bertambah → pesanan yang masih menunggu dicek ulang (PRD).
            $assigned = $this->fill($id, $owner);
            $this->costs->recomputeSchedule($id);

            return ['assigned' => $assigned];
        });
    }

    /** Pasang pesanan yang menunggu ke jadwal ini sampai kuota habis. @return list<string> */
    public function fill(int $scheduleId, ?User $user): array
    {
        $s = DB::table('baggage_schedules')->where('id', $scheduleId)->lockForUpdate()->first();
        if ($s->status !== 'open') {
            return [];
        }
        $left = $s->quota_grams === null ? PHP_INT_MAX : $s->quota_grams - $this->usedGrams($scheduleId);

        $waiting = Order::where('status', OrderStatus::MenungguBagasi)
            ->orderBy('status_changed_at')->orderBy('id')
            ->lockForUpdate()->get();

        $assigned = [];
        foreach ($waiting as $order) {
            $w = $this->orderWeight($order->id);
            if ($w > $left) {
                continue;
            }
            $this->attach($scheduleId, $order, $w, $user);
            $left -= $w;
            $assigned[] = $order->number;
        }

        if ($assigned) {
            $this->costs->recomputeSchedule($scheduleId);
        }

        return $assigned;
    }

    private function attach(int $scheduleId, Order $order, int $grams, ?User $user): void
    {
        DB::table('baggage_loads')->insert(['baggage_schedule_id' => $scheduleId, 'order_id' => $order->id, 'weight_grams' => $grams, 'assigned_at' => now()]);
        $this->addInbound($scheduleId, $order->id, +1);

        $date = DB::table('baggage_schedules')->where('id', $scheduleId)->value('depart_date');
        $order->update(['status' => OrderStatus::DalamBagasi, 'status_changed_at' => now()]);
        $this->orders->history($order, OrderStatus::MenungguBagasi, OrderStatus::DalamBagasi, $user, 'Dipasang ke jadwal bagasi '.\Carbon\Carbon::parse($date)->locale('id')->translatedFormat('j F Y'));
    }

    /** Tambah/kurangi baris "Barang Diterima dari Mesir" (satu baris per kitab per jadwal). */
    private function addInbound(int $scheduleId, int $orderId, int $sign): void
    {
        $lines = DB::table('order_items')->where('order_id', $orderId)->where('qty_to_buy', '>', 0)->get(['product_id', 'qty_to_buy']);
        foreach ($lines as $l) {
            $row = DB::table('inbound_lines')->where('baggage_schedule_id', $scheduleId)->where('product_id', $l->product_id)->lockForUpdate()->first();
            if ($row) {
                $qty = $row->qty + $sign * $l->qty_to_buy;
                $qty > 0
                    ? DB::table('inbound_lines')->where('id', $row->id)->update(['qty' => $qty, 'updated_at' => now()])
                    : DB::table('inbound_lines')->where('id', $row->id)->delete();
            } elseif ($sign > 0) {
                DB::table('inbound_lines')->insert(['baggage_schedule_id' => $scheduleId, 'product_id' => $l->product_id, 'qty' => $l->qty_to_buy, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    private function hasReceived(int $scheduleId): bool
    {
        return DB::table('inbound_lines')->where('baggage_schedule_id', $scheduleId)->whereNotNull('received_at')->exists();
    }

    /** Pindahkan semua pesanan ke jadwal lain (dipakai sebelum menghapus). */
    public function moveAll(int $fromId, int $toId, User $owner): int
    {
        return DB::transaction(function () use ($fromId, $toId, $owner) {
            if ($fromId === $toId) {
                throw ValidationException::withMessages(['target_id' => 'Pilih jadwal lain.']);
            }
            if ($this->hasReceived($fromId)) {
                throw ValidationException::withMessages(['target_id' => 'Sebagian kitab dari jadwal ini sudah diterima gudang, jadi pesanannya tidak bisa dipindah.']);
            }
            $to = DB::table('baggage_schedules')->where('id', $toId)->lockForUpdate()->first();
            if (! $to || $to->status !== 'open') {
                throw ValidationException::withMessages(['target_id' => 'Jadwal tujuan tidak tersedia.']);
            }
            $moving = $this->usedGrams($fromId);
            if ($to->quota_grams !== null && $this->usedGrams($toId) + $moving > $to->quota_grams) {
                throw ValidationException::withMessages(['target_id' => 'Kuota jadwal tujuan tidak cukup ('.round($moving / 1000, 1).' kg perlu dipindah).']);
            }

            $loads = DB::table('baggage_loads')->where('baggage_schedule_id', $fromId)->get();
            $toDate = \Carbon\Carbon::parse($to->depart_date)->locale('id')->translatedFormat('j F Y');
            foreach ($loads as $l) {
                $this->addInbound($fromId, $l->order_id, -1);
                DB::table('baggage_loads')->where('id', $l->id)->update(['baggage_schedule_id' => $toId]);
                $this->addInbound($toId, $l->order_id, +1);
                $order = Order::find($l->order_id);
                $this->orders->history($order, $order->status, $order->status, $owner, "Dipindah ke jadwal bagasi {$toDate}");
            }
            $this->costs->recomputeSchedule($toId);
            Audit::log('baggage.move', null, ['schedule_id' => $fromId], ['schedule_id' => $toId, 'orders' => count($loads)]);

            return count($loads);
        });
    }

    public function delete(int $id, User $owner): void
    {
        DB::transaction(function () use ($id) {
            if (DB::table('baggage_loads')->where('baggage_schedule_id', $id)->exists()) {
                throw ValidationException::withMessages(['delete' => 'Jadwal ini masih dipakai pesanan. Pindahkan dulu pesanannya ke jadwal lain.']);
            }
            DB::table('inbound_lines')->where('baggage_schedule_id', $id)->delete();
            DB::table('baggage_schedules')->where('id', $id)->delete();
        });
        Audit::log('baggage.delete', null, ['schedule_id' => $id]);
    }
}
