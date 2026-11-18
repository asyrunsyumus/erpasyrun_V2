<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tim Gudang Indonesia — pemegang dua tombol yang tidak bisa ditekan siapa pun selain mereka:
 * "Konfirmasi Diterima" dan "Selesai" packing.
 */
class WarehouseService
{
    public function __construct(private OrderService $orders, private StockLedger $stock) {}

    /** @return list<string> nomor pesanan yang kini siap packing */
    public function receive(int $lineId, User $user): array
    {
        return DB::transaction(function () use ($lineId, $user) {
            $line = DB::table('inbound_lines')->where('id', $lineId)->lockForUpdate()->first();
            abort_unless($line, 404);
            if ($line->received_at) {
                throw ValidationException::withMessages(['line' => 'Baris ini sudah dikonfirmasi.']);
            }
            DB::table('inbound_lines')->where('id', $lineId)->update(['received_at' => now(), 'received_by' => $user->id, 'updated_at' => now()]);
            Audit::log('warehouse.receive', null, null, ['inbound_line_id' => $lineId, 'product_id' => $line->product_id, 'qty' => $line->qty]);

            // Pesanan di jadwal ini yang semua kitab Mesir-nya sudah diterima → siap packing.
            $ready = [];
            $orderIds = DB::table('baggage_loads')->where('baggage_schedule_id', $line->baggage_schedule_id)->pluck('order_id');
            foreach ($orderIds as $oid) {
                $order = Order::lockForUpdate()->find($oid);
                if (! $order || $order->status !== OrderStatus::DalamBagasi) {
                    continue;
                }
                $pending = DB::table('order_items as i')
                    ->join('inbound_lines as l', fn ($j) => $j->on('l.product_id', '=', 'i.product_id')->where('l.baggage_schedule_id', $line->baggage_schedule_id))
                    ->where('i.order_id', $oid)->where('i.qty_to_buy', '>', 0)->whereNull('l.received_at')
                    ->exists();
                if (! $pending) {
                    $order->update(['status' => OrderStatus::SiapPacking, 'status_changed_at' => now()]);
                    $this->orders->history($order, OrderStatus::DalamBagasi, OrderStatus::SiapPacking, $user, 'Barang diterima di gudang');
                    $ready[] = $order->number;
                }
            }

            return $ready;
        });
    }

    /** Centang satu kedatangan belanja Mesir (restok / kelebihan): stok bertambah sekarang. */
    public function receiveArrival(int $arrivalId, User $user): void
    {
        DB::transaction(function () use ($arrivalId, $user) {
            $a = DB::table('purchase_arrivals')->where('id', $arrivalId)->lockForUpdate()->first();
            abort_unless($a, 404);
            if ($a->received_at) {
                throw ValidationException::withMessages(['arrival' => 'Barang ini sudah dicentang.']);
            }
            DB::table('purchase_arrivals')->where('id', $arrivalId)->update(['received_at' => now(), 'received_by' => $user->id, 'updated_at' => now()]);
            $this->stock->move((int) $a->product_id, (int) $a->qty, 'purchase_surplus', $user->id, (int) $a->unit_cost_idr, 'purchase_arrival', $arrivalId, 'Diterima gudang dari belanja Mesir');
            Audit::log('warehouse.receive_arrival', null, null, ['arrival_id' => $arrivalId, 'product_id' => $a->product_id, 'qty' => $a->qty]);
        });
    }

    /** Centang semua kedatangan yang belum diterima. @return int */
    public function receiveAllArrivals(User $user): int
    {
        $ids = DB::table('purchase_arrivals')->whereNull('received_at')->pluck('id');
        foreach ($ids as $id) {
            $this->receiveArrival((int) $id, $user);
        }

        return $ids->count();
    }

    /** Konfirmasi semua baris yang belum diterima di satu jadwal. @return int jumlah baris yang baru dikonfirmasi */
    public function receiveSchedule(int $scheduleId, User $user): int
    {
        $lines = DB::table('inbound_lines')->where('baggage_schedule_id', $scheduleId)->whereNull('received_at')->pluck('id');
        foreach ($lines as $id) {
            $this->receive((int) $id, $user);
        }

        return $lines->count();
    }

    public function toggleCheck(int $itemId, bool $checked, User $user): void
    {
        $orderId = (int) DB::table('order_items')->where('id', $itemId)->value('order_id');
        $status = DB::table('orders')->where('id', $orderId)->value('status');
        abort_unless($status === OrderStatus::SiapPacking->value, 409, 'Pesanan ini tidak sedang dipacking.');

        // Centang gudang permanen: sekali dicentang tidak bisa dibatalkan (sesuai kebutuhan audit gudang).
        if (! $checked) {
            throw ValidationException::withMessages(['line' => 'Centang gudang tidak bisa dibatalkan.']);
        }
        DB::table('packing_checks')->insertOrIgnore(['order_item_id' => $itemId, 'checked_by' => $user->id, 'checked_at' => now()]);
    }

    public function finish(int $orderId, User $user): void
    {
        DB::transaction(function () use ($orderId, $user) {
            $order = Order::lockForUpdate()->findOrFail($orderId);
            if ($order->status !== OrderStatus::SiapPacking) {
                throw ValidationException::withMessages(['finish' => 'Pesanan ini belum siap atau sudah selesai.']);
            }
            $items = DB::table('order_items')->where('order_id', $orderId)->pluck('id');
            $checked = DB::table('packing_checks')->whereIn('order_item_id', $items)->count();
            if ($checked < $items->count()) {
                throw ValidationException::withMessages(['finish' => 'Centang semua kitab dulu sebelum menekan Selesai.']);
            }
            if (! DB::table('shipping_labels')->where('order_id', $orderId)->exists()) {
                throw ValidationException::withMessages(['finish' => 'Resi belum diunggah Admin. Minta Admin mengunggah resi dulu.']);
            }
            $order->update(['status' => OrderStatus::Selesai, 'status_changed_at' => now()]);
            $this->orders->history($order, OrderStatus::SiapPacking, OrderStatus::Selesai, $user, 'Packing selesai & diserahkan ke ekspedisi');
            Audit::log('warehouse.finish', $order, null, ['number' => $order->number]);
        });
    }

    // ---------------- Stok opname ----------------

    /** @param list<array{product_id:int, counted_qty:int}> $lines */
    public function submitCount(array $lines, ?string $note, User $user): int
    {
        return DB::transaction(function () use ($lines, $note, $user) {
            $id = DB::table('stock_counts')->insertGetId(['status' => 'submitted', 'note' => $note, 'counted_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($lines as $l) {
                DB::table('stock_count_lines')->insert([
                    'stock_count_id' => $id,
                    'product_id' => $l['product_id'],
                    'system_qty' => (int) DB::table('product_stock')->where('product_id', $l['product_id'])->value('qty'),
                    'counted_qty' => $l['counted_qty'],
                ]);
            }

            return $id;
        });
    }

    /** Owner menyetujui → selisih dibukukan sebagai mutasi "opname" (stok disamakan dengan hitungan fisik). */
    public function decideCount(int $countId, bool $approve, User $owner): void
    {
        DB::transaction(function () use ($countId, $approve, $owner) {
            $c = DB::table('stock_counts')->where('id', $countId)->lockForUpdate()->first();
            abort_unless($c, 404);
            if ($c->status !== 'submitted') {
                throw ValidationException::withMessages(['count' => 'Laporan ini sudah diputuskan.']);
            }
            if ($approve) {
                foreach (DB::table('stock_count_lines')->where('stock_count_id', $countId)->get() as $l) {
                    $current = (int) DB::table('product_stock')->where('product_id', $l->product_id)->value('qty');
                    $diff = $l->counted_qty - $current;
                    $this->stock->move($l->product_id, $diff, 'opname', $owner->id, 0, 'stock_count', $countId, 'Stok opname disetujui Owner');
                }
            }
            DB::table('stock_counts')->where('id', $countId)->update([
                'status' => $approve ? 'approved' : 'rejected',
                'approved_by' => $owner->id,
                'approved_at' => now(),
                'updated_at' => now(),
            ]);
            Audit::log($approve ? 'stock.opname_approve' : 'stock.opname_reject', null, null, ['stock_count_id' => $countId]);
        });
    }
}
