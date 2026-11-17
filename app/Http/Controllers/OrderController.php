<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $payment = $request->query('payment');

        $items = Order::query()
            ->with(['customer:id,name,type', 'label:id,order_id'])
            ->withSum('items', 'qty')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('number', 'like', "%{$q}%")
                ->orWhereHas('customer', fn ($c) => $c->withTrashed()->where('name', 'like', "%{$q}%"))))
            ->when($status && OrderStatus::tryFrom($status), fn ($w) => $w->where('status', $status))
            ->when($status === 'aktif', fn ($w) => $w->whereNotIn('status', ['selesai', 'dibatalkan']))
            ->when($payment === 'belum', fn ($w) => $w->where('payment_status', '!=', 'paid')->where('status', '!=', 'dibatalkan'))
            ->when($payment === 'tanpa_resi', fn ($w) => $w->whereNotIn('status', ['selesai', 'dibatalkan'])->whereDoesntHave('label'))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'customer' => $o->customer?->name,
                'customer_type' => $o->customer?->type,
                'channel' => $o->channel,
                'payment_status' => $o->payment_status,
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'total' => $o->total,
                'qty' => (int) $o->items_sum_qty,
                'has_label' => (bool) $o->label,
                'created_at' => $o->created_at->locale('id')->translatedFormat('j M Y'),
            ]);

        return Inertia::render('orders/index', [
            'items' => $items,
            'filters' => ['q' => $q, 'status' => $status ?? 'aktif', 'payment' => $payment],
            'statuses' => array_map(fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()], OrderStatus::cases()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('orders/create');
    }

    /** Pencarian customer untuk form order. */
    public function lookupCustomers(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        return response()->json(Customer::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")->orWhere('code', $q)))
            ->orderBy('name')->limit(12)
            ->get(['id', 'code', 'name', 'type', 'city', 'phone']));
    }

    /** Pencarian kitab: harga sesuai tipe pembeli + stok gudang. Harga beli tidak pernah dikirim. */
    public function lookupProducts(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $type = $request->query('type') === 'reseller' ? 'reseller' : 'reguler';

        return response()->json(Product::query()
            ->where('is_active', true)
            ->with('stock')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('title_latin', 'like', "%{$q}%")->orWhere('title_ar', 'like', "%{$q}%")->orWhere('sku', $q)))
            ->orderBy('title_latin')->limit(12)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'title_latin' => $p->title_latin,
                'title_ar' => $p->title_ar,
                'has_photo' => (bool) $p->photo_path,
                'weight_grams' => $p->weight_grams,
                'price' => $type === 'reseller' ? $p->price_reseller : $p->price_regular,
                'stock' => max(0, (int) ($p->stock?->qty ?? 0)),
            ]));
    }

    public function store(Request $request, OrderService $orders): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'channel' => ['required', Rule::in(['whatsapp', 'shopee'])],
            'payment_status' => ['required_if:channel,whatsapp', 'nullable', Rule::in(['unpaid', 'trusted', 'paid'])],
            'shipping_fee' => ['nullable', 'integer', 'min:0'],
            'discount' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:10000'],
            // Untuk kitab yang beratnya belum ada di master: diisi di form order, lalu disimpan ke master.
            'items.*.weight_grams' => ['nullable', 'integer', 'min:1', 'max:50000'],
        ], [
            'customer_id.required' => 'Pilih pembeli dulu.',
            'items.required' => 'Tambahkan minimal satu kitab.',
            'payment_status.required_if' => 'Pilih status pembayaran.',
        ]);

        $order = $orders->create([
            ...$data,
            'payment_status' => $data['payment_status'] ?? 'paid',
            'shipping_fee' => $data['shipping_fee'] ?? 0,
            'discount' => $data['discount'] ?? 0,
        ], $request->user());

        return redirect()->route('orders.show', $order)->with('success', "Order {$order->number} tersimpan.");
    }

    public function show(Request $request, Order $order): Response
    {
        $order->load(['customer', 'creator:id,name', 'items.product', 'invoice', 'label.uploader:id,name', 'histories.user:id,name']);
        $isOwner = $request->user()->isOwner();

        $steps = [];
        $current = $order->status;
        $order_ = array_map(fn ($s) => $s->value, OrderStatus::steps());
        $pos = array_search($current->value, $order_, true);
        foreach (OrderStatus::steps() as $i => $s) {
            // Jalan pintas: pesanan dari gudang semua tidak melewati tahap Mesir & bagasi.
            $skipped = $pos !== false && $i < $pos && $order->items->sum('qty_to_buy') === 0 && in_array($s, [OrderStatus::BelanjaMesir, OrderStatus::MenungguBagasi, OrderStatus::DalamBagasi], true);
            $steps[] = ['key' => $s->value, 'label' => $s->label(), 'state' => $pos === false ? 'todo' : ($i < $pos ? ($skipped ? 'skipped' : 'done') : ($i === $pos ? 'now' : 'todo'))];
        }

        $cost = null;
        if ($isOwner && $order->items->every(fn ($i) => $i->unit_cost !== null)) {
            $cogs = $order->items->sum(fn ($i) => $i->qty * $i->unit_cost);
            $net = $order->subtotal - $order->discount;
            $cost = ['cogs' => $cogs, 'profit' => $net - $cogs, 'margin' => $net > 0 ? round(($net - $cogs) / $net * 100) : 0];
        }

        return Inertia::render('orders/show', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'channel' => $order->channel,
                'payment_status' => $order->payment_status,
                'status' => $current->value,
                'status_label' => $current->label(),
                'waiting_for' => $current->waitingFor(),
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'shipping_fee' => $order->shipping_fee,
                'total' => $order->total,
                'notes' => $order->notes,
                'paid_at' => $order->paid_at?->locale('id')->translatedFormat('j F Y'),
                'created_at' => $order->created_at->locale('id')->translatedFormat('j F Y'),
                'created_by' => $order->creator?->name,
                'customer' => $order->customer->only('id', 'code', 'name', 'type', 'phone', 'address', 'city'),
                'invoice' => $order->invoice?->only('number'),
                'label' => $order->label ? ['name' => $order->label->original_name, 'by' => $order->label->uploader?->name, 'at' => $order->label->created_at->locale('id')->translatedFormat('j M Y')] : null,
                'items' => $order->items->map(fn ($i) => [
                    'id' => $i->id,
                    'title_latin' => $i->product->title_latin,
                    'title_ar' => $i->product->title_ar,
                    'product_id' => $i->product_id,
                    'has_photo' => (bool) $i->product->photo_path,
                    'weight_grams' => $i->product->weight_grams,
                    'qty' => $i->qty,
                    'unit_price' => $i->unit_price,
                    'line_total' => $i->line_total,
                    'qty_from_stock' => $i->qty_from_stock,
                    'qty_to_buy' => $i->qty_to_buy,
                ]),
                'histories' => $order->histories->map(fn ($h) => [
                    'at' => $h->created_at->locale('id')->translatedFormat('j M, H.i'),
                    'note' => $h->note ?: OrderStatus::from($h->to_status)->label(),
                    'by' => $h->user?->name,
                ]),
            ],
            'steps' => $steps,
            'cost' => $cost,
            'can' => [
                'mark_paid' => $order->payment_status !== 'paid' && ! in_array($current, [OrderStatus::Dibatalkan, OrderStatus::MenungguPembatalan], true),
                'upload_label' => ! in_array($current, [OrderStatus::Selesai, OrderStatus::Dibatalkan], true),
                'cancel' => ! in_array($current, [OrderStatus::Selesai, OrderStatus::Dibatalkan, OrderStatus::MenungguPembatalan], true),
            ],
        ]);
    }

    public function markPaid(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $data = $request->validate(['paid_at' => ['required', 'date', 'before_or_equal:today']], ['paid_at.before_or_equal' => 'Tanggal bayar tidak boleh di masa depan.']);
        $orders->markPaid($order, $data['paid_at'], $request->user());

        return back()->with('success', 'Pesanan ditandai lunas.');
    }

    public function uploadLabel(Request $request, Order $order): RedirectResponse
    {
        abort_if(in_array($order->status, [OrderStatus::Selesai, OrderStatus::Dibatalkan], true), 409);
        $request->validate(['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120']], ['file.mimes' => 'Resi harus PDF atau gambar (JPG/PNG).']);

        $file = $request->file('file');
        $path = $file->store('shipping-labels', 'local');

        DB::table('shipping_labels')->insert([
            'order_id' => $order->id,
            'file_path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 200),
            'uploaded_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Audit::log('order.label_upload', $order);

        return back()->with('success', 'Resi diunggah. Tim Gudang bisa langsung mencetaknya.');
    }

    /** Resi bisa dibuka Owner, Admin, dan Gudang (untuk dicetak). */
    public function label(Order $order): StreamedResponse
    {
        $label = $order->label;
        abort_unless($label && Storage::disk('local')->exists($label->file_path), 404);

        return Storage::disk('local')->response($label->file_path, $label->original_name, ['Cache-Control' => 'private, no-store']);
    }

    public function invoice(Order $order): Response
    {
        abort_unless($order->invoice, 404);
        $order->load(['customer', 'items.product', 'invoice']);

        return Inertia::render('orders/invoice', [
            'shop' => [
                'name' => 'Maktabah Asyrun Syumus',
                'bank' => DB::table('settings')->where('key', 'invoice_bank')->value('value'),
                'note' => DB::table('settings')->where('key', 'invoice_note')->value('value'),
                'phone' => DB::table('settings')->where('key', 'shop_phone')->value('value'),
            ],
            'invoice' => [
                'number' => $order->invoice->number,
                'issued_at' => $order->invoice->issued_at->locale('id')->translatedFormat('j F Y'),
                'order_number' => $order->number,
                'paid' => $order->payment_status === 'paid',
                'paid_at' => $order->paid_at?->locale('id')->translatedFormat('j F Y'),
                'customer' => $order->customer->only('name', 'phone', 'address', 'city'),
                'items' => $order->items->map(fn ($i) => ['title' => $i->product->title_latin, 'title_ar' => $i->product->title_ar, 'qty' => $i->qty, 'price' => $i->unit_price, 'total' => $i->line_total]),
                'subtotal' => $order->subtotal,
                'discount' => $order->discount,
                'shipping_fee' => $order->shipping_fee,
                'total' => $order->total,
            ],
        ]);
    }
}
