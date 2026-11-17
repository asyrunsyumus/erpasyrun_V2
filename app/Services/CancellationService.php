<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use App\Notifications\CancellationRequested;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Pembatalan & refund (Admin/Sales atau Owner).
 *  - Setelah ditolak, BOLEH diajukan lagi (keputusan Owner, beda dari sistem lama).
 *  - Disetujui: kitab yang sudah diambil dari gudang / sudah dibeli di Mesir kembali ke stok Indonesia.
 *    Uang belanja Mesir tidak kembali — yang kembali adalah kitabnya.
 */
class CancellationService
{
    public function __construct(private OrderService $orders, private StockLedger $stock, private CostCalculator $costs) {}

    public function request(Order $order, string $reason, User $user): void
    {
        DB::transaction(function () use ($order, $reason, $user) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            if (in_array($order->status, [OrderStatus::Selesai, OrderStatus::Dibatalkan, OrderStatus::MenungguPembatalan], true)) {
                throw ValidationException::withMessages(['reason' => match ($order->status) {
                    OrderStatus::Selesai => 'Pesanan sudah selesai dikirim, tidak bisa dibatalkan.',
                    OrderStatus::Dibatalkan => 'Pesanan sudah dibatalkan.',
                    default => 'Sudah ada pengajuan yang menunggu keputusan.',
                }]);
            }

            DB::table('cancellations')->insert([
                'order_id' => $order->id,
                'reason' => $reason,
                'status' => 'pending',
                'status_before' => $order->status->value,
                'requested_by' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $from = $order->status;
            $order->update(['status' => OrderStatus::MenungguPembatalan, 'status_changed_at' => now()]);
            $this->orders->history($order, $from, OrderStatus::MenungguPembatalan, $user, "Pengajuan pembatalan: {$reason}");

            $to = User::whereIn('role', [Role::Owner->value, Role::Admin->value])->where('is_active', true)->where('id', '!=', $user->id)->get();
            Notification::send($to, new CancellationRequested($order->number, $order->id, $user->name));
        });
    }

    public function reject(int $cancellationId, ?string $note, User $user): void
    {
        DB::transaction(function () use ($cancellationId, $note, $user) {
            $c = $this->pending($cancellationId);
            $order = Order::lockForUpdate()->findOrFail($c->order_id);
            $back = OrderStatus::from($c->status_before);

            DB::table('cancellations')->where('id', $c->id)->update(['status' => 'rejected', 'decided_by' => $user->id, 'decided_at' => now(), 'decision_note' => $note, 'updated_at' => now()]);
            $order->update(['status' => $back, 'status_changed_at' => now()]);
            $this->orders->history($order, OrderStatus::MenungguPembatalan, $back, $user, 'Pembatalan ditolak'.($note ? ": {$note}" : ''));
        });
    }

    public function approve(int $cancellationId, int $refund, ?string $method, ?string $proofPath, User $user): void
    {
        DB::transaction(function () use ($cancellationId, $refund, $method, $proofPath, $user) {
            $c = $this->pending($cancellationId);
            $order = Order::lockForUpdate()->findOrFail($c->order_id);

            if ($refund > $order->total) {
                throw ValidationException::withMessages(['refund_amount' => 'Refund tidak boleh melebihi total tagihan.']);
            }

            $returned = 0;
            foreach (DB::table('order_items')->where('order_id', $order->id)->get() as $item) {
                // 1. Bagian yang dulu diambil dari gudang → kembali.
                if ($item->qty_from_stock > 0) {
                    $cost = (int) DB::table('stock_movements')->where('type', 'order_pick')->where('ref_type', 'order_item')->where('ref_id', $item->id)->value('unit_cost_idr');
                    $this->stock->move($item->product_id, $item->qty_from_stock, 'cancel_return', $user->id, $cost, 'order_item', $item->id, "Batal {$order->number}");
                    $returned += $item->qty_from_stock;
                }

                // 2. Bagian yang sudah dibelikan Tim Mesir → kembali sebagai stok; sisanya tidak perlu dibeli lagi.
                $need = DB::table('purchase_needs')->where('order_item_id', $item->id)->lockForUpdate()->first();
                if ($need) {
                    if ($need->qty_fulfilled > 0) {
                        $cost = (int) round((float) DB::table('purchase_allocations as a')->join('purchases as p', 'p.id', '=', 'a.purchase_id')
                            ->where('a.purchase_need_id', $need->id)->avg(DB::raw('p.cost_idr / p.qty')));
                        $this->stock->move($item->product_id, $need->qty_fulfilled, 'cancel_return', $user->id, $cost, 'order_item', $item->id, "Batal {$order->number} (sudah dibeli di Mesir)");
                        $returned += $need->qty_fulfilled;
                    }
                    DB::table('purchase_needs')->where('id', $need->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
                }
            }

            DB::table('cancellations')->where('id', $c->id)->update([
                'status' => 'approved',
                'decided_by' => $user->id,
                'decided_at' => now(),
                'refund_amount' => $refund,
                'refund_method' => $method,
                'refund_proof_path' => $proofPath,
                'updated_at' => now(),
            ]);
            $order->update(['status' => OrderStatus::Dibatalkan, 'status_changed_at' => now()]);
            $this->orders->history($order, OrderStatus::MenungguPembatalan, OrderStatus::Dibatalkan, $user, "Pembatalan disetujui — {$returned} kitab kembali ke stok".($refund ? ', refund Rp '.number_format($refund, 0, ',', '.') : ''));
            Audit::log('order.cancel', $order, null, ['refund' => $refund, 'method' => $method, 'returned' => $returned]);
        });
    }

    private function pending(int $id): object
    {
        $c = DB::table('cancellations')->where('id', $id)->lockForUpdate()->first();
        abort_unless($c, 404);
        if ($c->status !== 'pending') {
            throw ValidationException::withMessages(['decision' => 'Pengajuan ini sudah diputuskan.']);
        }

        return $c;
    }
}
