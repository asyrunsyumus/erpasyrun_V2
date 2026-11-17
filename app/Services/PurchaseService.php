<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\PurchaseShortfall;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Pencatatan belanja Tim Mesir.
 *
 *  - Jumlah dibeli dialokasikan ke kebutuhan pesanan PALING DULU masuk (FIFO).
 *  - Kelebihan → stok Indonesia (purchase_surplus) dengan biaya per buah.
 *  - Kekurangan → tetap di daftar + notifikasi ke Owner.
 *  - Harga EGP berbeda → harga di Master Produk diperbarui + dicatat di audit.
 *  - Pesanan yang semua kitabnya terbeli → pindah ke "Menunggu bagasi".
 *
 * Satuan: price_egp dalam piaster (1 EGP = 100), rate = Rp per EGP × 10.000.
 */
class PurchaseService
{
    public function __construct(private StockLedger $stock, private OrderService $orders) {}

    public static function costIdr(int $qty, int $pricePiaster, int $rate): int
    {
        return (int) round($qty * $pricePiaster * $rate / 1_000_000);
    }

    /**
     * @param  array{product_id:int, maktabah_id:int, qty:int, price_egp:int, rate:int, purchased_at:string}  $d
     * @return array{allocated:int, surplus:int, shortfall:int, price_changed: ?array{from:int,to:int}, orders_ready: list<string>}
     */
    public function record(array $d, User $user): array
    {
        return DB::transaction(function () use ($d, $user) {
            $product = Product::lockForUpdate()->findOrFail($d['product_id']);

            $purchaseId = DB::table('purchases')->insertGetId([
                'product_id' => $product->id,
                'maktabah_id' => $d['maktabah_id'],
                'qty' => $d['qty'],
                'price_egp' => $d['price_egp'],
                'rate' => $d['rate'],
                'cost_idr' => self::costIdr($d['qty'], $d['price_egp'], $d['rate']),
                'purchased_at' => $d['purchased_at'],
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $result = $this->apply($purchaseId, $user);

            // Harga beli berubah → perbarui Master Produk (PRD), catat di audit.
            $priceChanged = null;
            if ((int) $product->price_egp !== (int) $d['price_egp']) {
                $priceChanged = ['from' => (int) $product->price_egp, 'to' => (int) $d['price_egp']];
                $product->price_egp = $d['price_egp'];
                $product->save();
                Audit::log('product.price_egp_from_purchase', $product, ['price_egp' => $priceChanged['from']], ['price_egp' => $priceChanged['to'], 'purchase_id' => $purchaseId]);
            }

            if ($result['shortfall'] > 0) {
                $owners = User::where('role', Role::Owner->value)->where('is_active', true)->get();
                Notification::send($owners, new PurchaseShortfall($product->title_latin, $result['shortfall'], $user->name));
            }

            return $result + ['price_changed' => $priceChanged];
        });
    }

    /** Terapkan efek satu transaksi belanja: alokasi FIFO + kelebihan ke stok + status pesanan. */
    private function apply(int $purchaseId, ?User $user): array
    {
        $p = DB::table('purchases')->where('id', $purchaseId)->first();
        $remaining = (int) $p->qty;
        $unitCost = $p->qty > 0 ? (int) round($p->cost_idr / $p->qty) : 0;

        $needs = DB::table('purchase_needs')
            ->where('product_id', $p->product_id)->where('status', 'open')
            ->orderBy('created_at')->orderBy('id')
            ->lockForUpdate()->get();

        $touchedOrders = [];
        $allocated = 0;
        foreach ($needs as $n) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, $n->qty_needed - $n->qty_fulfilled);
            if ($take <= 0) {
                continue;
            }
            DB::table('purchase_allocations')->insert(['purchase_id' => $purchaseId, 'purchase_need_id' => $n->id, 'qty' => $take, 'created_at' => now(), 'updated_at' => now()]);
            $filled = $n->qty_fulfilled + $take;
            DB::table('purchase_needs')->where('id', $n->id)->update([
                'qty_fulfilled' => $filled,
                'status' => $filled >= $n->qty_needed ? 'fulfilled' : 'open',
                'updated_at' => now(),
            ]);
            $touchedOrders[] = (int) DB::table('order_items')->where('id', $n->order_item_id)->value('order_id');
            $remaining -= $take;
            $allocated += $take;
        }

        if ($remaining > 0) {
            $this->stock->move((int) $p->product_id, $remaining, 'purchase_surplus', $user?->id, $unitCost, 'purchase', $purchaseId, 'Kelebihan belanja Mesir');
        }

        $openLeft = (int) DB::table('purchase_needs')->where('product_id', $p->product_id)->where('status', 'open')->sum(DB::raw('qty_needed - qty_fulfilled'));

        return [
            'allocated' => $allocated,
            'surplus' => $remaining,
            'shortfall' => $openLeft,
            'orders_ready' => $this->advanceOrders(array_unique($touchedOrders), $user),
        ];
    }

    /** Batalkan efek satu transaksi (dipakai saat koreksi). */
    private function revert(int $purchaseId, ?User $user): void
    {
        $allocs = DB::table('purchase_allocations')->where('purchase_id', $purchaseId)->get();
        $touched = [];
        foreach ($allocs as $a) {
            $n = DB::table('purchase_needs')->where('id', $a->purchase_need_id)->lockForUpdate()->first();
            DB::table('purchase_needs')->where('id', $n->id)->update([
                'qty_fulfilled' => max(0, $n->qty_fulfilled - $a->qty),
                'status' => $n->status === 'cancelled' ? 'cancelled' : 'open',
                'updated_at' => now(),
            ]);
            $touched[] = (int) DB::table('order_items')->where('id', $n->order_item_id)->value('order_id');
        }
        DB::table('purchase_allocations')->where('purchase_id', $purchaseId)->delete();

        // Kembalikan kelebihan yang dulu masuk stok.
        $surplus = (int) DB::table('stock_movements')->where('ref_type', 'purchase')->where('ref_id', $purchaseId)->sum('qty');
        if ($surplus !== 0) {
            $p = DB::table('purchases')->where('id', $purchaseId)->first();
            $this->stock->move((int) $p->product_id, -$surplus, 'purchase_surplus', $user?->id, 0, 'purchase', $purchaseId, 'Koreksi belanja (dibatalkan)');
        }

        // Pesanan yang tadinya lengkap bisa kembali ke "Belanja di Mesir".
        foreach (array_unique($touched) as $orderId) {
            $order = Order::lockForUpdate()->find($orderId);
            if ($order && $order->status === OrderStatus::MenungguBagasi && $this->hasOpenNeeds($orderId)) {
                $order->update(['status' => OrderStatus::BelanjaMesir, 'status_changed_at' => now()]);
                $this->orders->history($order, OrderStatus::MenungguBagasi, OrderStatus::BelanjaMesir, $user, 'Kembali ke belanja karena koreksi catatan Tim Mesir');
            }
        }
    }

    /**
     * Owner membetulkan salah ketik. Efek lama dibatalkan lalu diterapkan ulang dengan angka baru.
     * Ditolak bila kitab dari transaksi ini sudah ikut jadwal bagasi.
     */
    public function correct(int $purchaseId, array $d, User $owner): void
    {
        DB::transaction(function () use ($purchaseId, $d, $owner) {
            $p = DB::table('purchases')->where('id', $purchaseId)->lockForUpdate()->first();
            abort_unless($p, 404);

            $locked = DB::table('purchase_allocations as a')
                ->join('purchase_needs as n', 'n.id', '=', 'a.purchase_need_id')
                ->join('order_items as i', 'i.id', '=', 'n.order_item_id')
                ->join('orders as o', 'o.id', '=', 'i.order_id')
                ->where('a.purchase_id', $purchaseId)
                ->whereIn('o.status', ['dalam_bagasi', 'siap_packing', 'selesai'])
                ->exists();
            if ($locked) {
                throw ValidationException::withMessages(['qty' => 'Kitab dari catatan ini sudah ikut jadwal bagasi, jadi tidak bisa dikoreksi lagi.']);
            }

            $before = (array) $p;
            $this->revert($purchaseId, $owner);

            DB::table('purchases')->where('id', $purchaseId)->update([
                'maktabah_id' => $d['maktabah_id'],
                'qty' => $d['qty'],
                'price_egp' => $d['price_egp'],
                'rate' => $d['rate'],
                'cost_idr' => self::costIdr($d['qty'], $d['price_egp'], $d['rate']),
                'purchased_at' => $d['purchased_at'],
                'corrected_by' => $owner->id,
                'corrected_at' => now(),
                'updated_at' => now(),
            ]);

            $this->apply($purchaseId, $owner);

            $after = (array) DB::table('purchases')->where('id', $purchaseId)->first();
            $keys = ['maktabah_id', 'qty', 'price_egp', 'rate', 'purchased_at'];
            Audit::log('purchase.correct', null, array_intersect_key($before, array_flip($keys)) + ['purchase_id' => $purchaseId], array_intersect_key($after, array_flip($keys)));
        });
    }

    private function hasOpenNeeds(int $orderId): bool
    {
        return DB::table('purchase_needs as n')
            ->join('order_items as i', 'i.id', '=', 'n.order_item_id')
            ->where('i.order_id', $orderId)->where('n.status', 'open')->exists();
    }

    /** @return list<string> nomor pesanan yang kini lengkap */
    private function advanceOrders(array $orderIds, ?User $user): array
    {
        $ready = [];
        foreach ($orderIds as $id) {
            $order = Order::lockForUpdate()->find($id);
            if ($order && $order->status === OrderStatus::BelanjaMesir && ! $this->hasOpenNeeds($id)) {
                $order->update(['status' => OrderStatus::MenungguBagasi, 'status_changed_at' => now()]);
                $this->orders->history($order, OrderStatus::BelanjaMesir, OrderStatus::MenungguBagasi, $user, 'Semua kitab sudah dibelikan di Mesir');
                $ready[] = $order->number;
            }
        }

        return $ready;
    }
}
