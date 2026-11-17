<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseNeed;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Semua perubahan pesanan lewat kelas ini, masing-masing dalam satu transaksi database.
 */
class OrderService
{
    public function __construct(private StockLedger $stock) {}

    /**
     * @param  array{customer_id:int, channel:string, payment_status:string, shipping_fee:int, discount:int, notes:?string, items: list<array{product_id:int, qty:int}>}  $data
     */
    public function create(array $data, User $user): Order
    {
        return DB::transaction(function () use ($data, $user) {
            $customer = Customer::findOrFail($data['customer_id']);
            $isShopee = $data['channel'] === 'shopee';
            $payment = $isShopee ? 'paid' : $data['payment_status'];

            // Gabungkan baris kitab yang sama.
            $lines = [];
            $weights = [];
            foreach ($data['items'] as $it) {
                $lines[$it['product_id']] = ($lines[$it['product_id']] ?? 0) + (int) $it['qty'];
                if (! empty($it['weight_grams'])) {
                    $weights[$it['product_id']] = (int) $it['weight_grams'];
                }
            }

            $products = Product::whereIn('id', array_keys($lines))->where('is_active', true)->lockForUpdate()->get()->keyBy('id');
            if ($products->count() !== count($lines)) {
                throw ValidationException::withMessages(['items' => 'Ada kitab yang tidak ditemukan atau sudah nonaktif.']);
            }

            // Berat belum ada di master → wajib diisi di form order, lalu tersimpan ke master produk.
            foreach ($products as $p) {
                if ($p->weight_grams === null) {
                    if (empty($weights[$p->id])) {
                        throw ValidationException::withMessages(['items' => "Isi berat \"{$p->title_latin}\" dulu — dipakai untuk kuota bagasi."]);
                    }
                    $p->weight_grams = $weights[$p->id];
                    $p->save();
                    Audit::log('product.weight_from_order', $p, ['weight_grams' => null], ['weight_grams' => $p->weight_grams]);
                }
            }

            $subtotal = 0;
            $prepared = [];
            foreach ($lines as $pid => $qty) {
                $p = $products[$pid];
                // Harga dikunci saat order dibuat, sesuai tipe pembeli.
                $price = $customer->type === 'reseller' ? $p->price_reseller : $p->price_regular;
                if ($price <= 0) {
                    throw ValidationException::withMessages(['items' => "Harga {$customer->type} untuk \"{$p->title_latin}\" belum diisi Owner."]);
                }
                $subtotal += $price * $qty;
                $prepared[] = compact('p', 'qty', 'price');
            }

            $discount = min((int) $data['discount'], $subtotal);
            $shipping = (int) $data['shipping_fee'];

            $order = Order::create([
                'number' => $this->nextNumber('orders', 'number', 'ASY'),
                'customer_id' => $customer->id,
                'channel' => $data['channel'],
                'payment_status' => $payment,
                'status' => OrderStatus::MenungguBayar, // ditentukan ulang di bawah
                'subtotal' => $subtotal,
                'discount' => $discount,
                'shipping_fee' => $shipping,
                'total' => $subtotal - $discount + $shipping,
                'paid_at' => $payment === 'paid' ? now() : null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
                'status_changed_at' => now(),
            ]);

            // Alokasi per baris (P2): ambil dari stok sebanyak yang ada, sisanya dibeli di Mesir.
            $fromStock = 0;
            $toBuy = 0;
            foreach ($prepared as ['p' => $p, 'qty' => $qty, 'price' => $price]) {
                $available = (int) DB::table('product_stock')->where('product_id', $p->id)->lockForUpdate()->value('qty');
                $take = max(0, min($qty, $available));

                $item = $order->items()->create([
                    'product_id' => $p->id,
                    'qty' => $qty,
                    'unit_price' => $price,
                    'line_total' => $price * $qty,
                    'qty_from_stock' => $take,
                    'qty_to_buy' => $qty - $take,
                ]);

                if ($take > 0) {
                    $avg = (int) DB::table('product_stock')->where('product_id', $p->id)->value('avg_cost_idr');
                    $this->stock->move($p->id, -$take, 'order_pick', $user->id, $avg, 'order_item', $item->id, "Order {$order->number}");
                }
                $fromStock += $take;
                $toBuy += $qty - $take;
            }

            if (! $isShopee) {
                Invoice::create([
                    'order_id' => $order->id,
                    'number' => $this->nextNumber('invoices', 'number', 'INV'),
                    'issued_at' => now()->toDateString(),
                ]);
            }

            $status = $payment === 'unpaid' ? OrderStatus::MenungguBayar : $this->statusAfterPayment($order);
            if ($payment !== 'unpaid') {
                $this->openNeeds($order);
            }

            $note = "Order dibuat — {$fromStock} kitab dari gudang".($toBuy ? ", {$toBuy} masuk daftar belanja Mesir" : '');
            $order->status = $status;
            $order->save();
            $this->history($order, null, $status, $user, $note);

            // Bagian dari stok sudah diketahui modalnya; pesanan full-gudang langsung punya HPP final.
            app(CostCalculator::class)->recomputeOrder($order->id);

            return $order;
        });
    }

    public function markPaid(Order $order, string $paidAt, User $user): void
    {
        DB::transaction(function () use ($order, $paidAt, $user) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            if ($order->payment_status === 'paid') {
                throw ValidationException::withMessages(['paid_at' => 'Pesanan ini sudah lunas.']);
            }
            if (in_array($order->status, [OrderStatus::Dibatalkan, OrderStatus::MenungguPembatalan], true)) {
                throw ValidationException::withMessages(['paid_at' => 'Pesanan sedang/sudah dibatalkan.']);
            }

            $wasUnpaid = $order->payment_status === 'unpaid';
            $order->payment_status = 'paid';
            $order->paid_at = $paidAt;

            if ($wasUnpaid) {
                // Baru sekarang kitab yang kurang masuk daftar belanja Tim Mesir.
                $this->openNeeds($order);
                $from = $order->status;
                $order->status = $this->statusAfterPayment($order);
                $order->status_changed_at = now();
                $order->save();
                $this->history($order, $from, $order->status, $user, 'Ditandai lunas');
            } else {
                $order->save();
                $this->history($order, $order->status, $order->status, $user, 'Ditandai lunas (sebelumnya dibelikan dulu)');
            }
        });
    }

    /** Pesanan yang semua kitabnya dari gudang langsung siap packing (jalan pintas di PRD). */
    private function statusAfterPayment(Order $order): OrderStatus
    {
        return $order->items()->sum('qty_to_buy') > 0 ? OrderStatus::BelanjaMesir : OrderStatus::SiapPacking;
    }

    private function openNeeds(Order $order): void
    {
        foreach ($order->items()->where('qty_to_buy', '>', 0)->get() as $item) {
            PurchaseNeed::firstOrCreate(
                ['order_item_id' => $item->id],
                ['product_id' => $item->product_id, 'qty_needed' => $item->qty_to_buy, 'status' => 'open'],
            );
        }
    }

    public function history(Order $order, ?OrderStatus $from, OrderStatus $to, ?User $user, ?string $note = null): void
    {
        DB::table('order_status_histories')->insert([
            'order_id' => $order->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'user_id' => $user?->id,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    /** ASY-2611-0001: prefix-tahun-bulan-urutan, urutan mulai lagi tiap bulan. */
    private function nextNumber(string $table, string $column, string $prefix): string
    {
        $stem = $prefix.'-'.now()->format('ym').'-';
        $last = DB::table($table)->where($column, 'like', $stem.'%')->lockForUpdate()->orderByDesc($column)->value($column);
        $n = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $stem.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
