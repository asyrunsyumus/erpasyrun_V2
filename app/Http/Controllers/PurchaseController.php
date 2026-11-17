<?php

namespace App\Http\Controllers;

use App\Models\Maktabah;
use App\Services\PurchaseService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tim Mesir hanya menerima: kitab, foto, jumlah dibutuhkan, maktabah, harga beli EGP.
 * TIDAK pernah menerima harga jual, nama pembeli, atau nilai tagihan.
 */
class PurchaseController extends Controller
{
    private function defaultRate(): int
    {
        return (int) (DB::table('settings')->where('key', 'default_rate')->value('value') ?? 0);
    }

    /** Daftar belanja dikelompokkan per maktabah utama. */
    private function shoppingList(): Collection
    {
        $rows = DB::table('purchase_needs as n')
            ->join('products as p', 'p.id', '=', 'n.product_id')
            ->leftJoin('maktabahs as m', 'm.id', '=', 'p.primary_maktabah_id')
            ->where('n.status', 'open')
            ->groupBy('p.id', 'p.sku', 'p.title_latin', 'p.title_ar', 'p.author', 'p.photo_path', 'p.price_egp', 'p.weight_grams', 'p.primary_maktabah_id', 'm.name')
            ->orderBy('m.name')->orderBy('p.title_latin')
            ->get([
                'p.id', 'p.sku', 'p.title_latin', 'p.title_ar', 'p.author', 'p.photo_path', 'p.price_egp', 'p.weight_grams', 'p.primary_maktabah_id', 'm.name as maktabah',
                DB::raw('SUM(n.qty_needed - n.qty_fulfilled) as qty'),
                DB::raw('COUNT(DISTINCT n.order_item_id) as orders'),
                DB::raw('MIN(n.created_at) as since'),
            ]);

        $alts = DB::table('product_maktabah as pm')->join('maktabahs as m', 'm.id', '=', 'pm.maktabah_id')
            ->whereIn('pm.product_id', $rows->pluck('id'))->whereNull('m.deleted_at')
            ->get(['pm.product_id', 'm.name'])->groupBy('product_id');

        return $rows->map(fn ($r) => [
            'product_id' => $r->id,
            'sku' => $r->sku,
            'title_latin' => $r->title_latin,
            'title_ar' => $r->title_ar,
            'author' => $r->author,
            'has_photo' => (bool) $r->photo_path,
            'price_egp' => (int) $r->price_egp,
            'weight_grams' => (int) $r->weight_grams,
            'maktabah_id' => $r->primary_maktabah_id,
            'maktabah' => $r->maktabah,
            'alternates' => ($alts[$r->id] ?? collect())->pluck('name')->values(),
            'qty' => (int) $r->qty,
            'orders' => (int) $r->orders,
            'waiting_days' => (int) now()->diffInDays($r->since, true),
        ])->groupBy(fn ($r) => $r['maktabah'] ?? '— Belum ada maktabah —')
            ->map(fn ($items, $name) => ['maktabah' => $name, 'items' => $items->values(), 'total_qty' => $items->sum('qty')])
            ->values();
    }

    public function index(Request $request): Response
    {
        $isOwner = $request->user()->isOwner();

        $history = DB::table('purchases as pu')
            ->join('products as p', 'p.id', '=', 'pu.product_id')
            ->join('maktabahs as m', 'm.id', '=', 'pu.maktabah_id')
            ->join('users as u', 'u.id', '=', 'pu.user_id')
            ->when(! $isOwner, fn ($q) => $q->where('pu.user_id', $request->user()->id))
            ->orderByDesc('pu.id')->limit(100)
            ->get(['pu.*', 'p.title_latin', 'p.title_ar', 'm.name as maktabah', 'u.name as by'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'product_id' => $r->product_id,
                'title_latin' => $r->title_latin,
                'title_ar' => $r->title_ar,
                'maktabah_id' => $r->maktabah_id,
                'maktabah' => $r->maktabah,
                'qty' => $r->qty,
                'price_egp' => $r->price_egp,
                'rate' => $r->rate,
                'cost_idr' => $isOwner ? $r->cost_idr : null,
                'purchased_at' => $r->purchased_at,
                'by' => $r->by,
                'corrected' => (bool) $r->corrected_at,
            ]);

        $restocks = DB::table('restock_needs as n')->join('products as p', 'p.id', '=', 'n.product_id')
            ->leftJoin('maktabahs as m', 'm.id', '=', 'p.primary_maktabah_id')
            ->where('n.status', 'open')->orderBy('n.created_at')
            ->get(['n.id', 'n.qty_needed', 'n.qty_fulfilled', 'p.id as product_id', 'p.sku', 'p.title_latin', 'p.title_ar', 'p.author', 'p.photo_path', 'p.price_egp', 'p.weight_grams', 'p.primary_maktabah_id', 'm.name as maktabah'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'product_id' => $r->product_id,
                'sku' => $r->sku,
                'title_latin' => $r->title_latin,
                'title_ar' => $r->title_ar,
                'author' => $r->author,
                'has_photo' => (bool) $r->photo_path,
                'price_egp' => (int) $r->price_egp,
                'weight_grams' => (int) $r->weight_grams,
                'maktabah_id' => $r->primary_maktabah_id,
                'maktabah' => $r->maktabah,
                'qty' => (int) $r->qty_needed - (int) $r->qty_fulfilled,
            ]);

        return Inertia::render('purchases/index', [
            'restocks' => $restocks->values(),
            'products' => DB::table('products')->whereNull('deleted_at')->where('is_active', true)->orderBy('title_latin')->limit(1000)->get(['id', 'sku', 'title_latin', 'title_ar']),
            'groups' => $this->shoppingList(),
            'history' => $history,
            'maktabahs' => Maktabah::orderBy('name')->get(['id', 'name']),
            'defaultRate' => $this->defaultRate(),
            'isOwner' => $isOwner,
            'today' => now()->toDateString(),
        ]);
    }

    public function print(): Response
    {
        return Inertia::render('purchases/print', [
            'groups' => $this->shoppingList(),
            'printedAt' => now()->locale('id')->translatedFormat('l, j F Y H.i'),
        ]);
    }

    private function rules(): array
    {
        return [
            'maktabah_id' => ['required', 'integer', Rule::exists('maktabahs', 'id')->whereNull('deleted_at')],
            'qty' => ['required', 'integer', 'min:1', 'max:10000'],
            'price_egp' => ['required', 'integer', 'min:1', 'max:100000000'],
            'rate' => ['required', 'integer', 'min:10000', 'max:100000000'],
            'purchased_at' => ['required', 'date', 'before_or_equal:today', 'after:-60 days'],
        ];
    }

    private function messages(): array
    {
        return [
            'maktabah_id.required' => 'Pilih maktabah tempat membeli.',
            'qty.min' => 'Jumlah minimal 1.',
            'price_egp.min' => 'Isi harga EGP.',
            'rate.min' => 'Isi kurs EGP ke Rupiah.',
            'purchased_at.before_or_equal' => 'Tanggal beli tidak boleh di masa depan.',
            'purchased_at.after' => 'Tanggal beli terlalu lama. Hubungi Owner.',
        ];
    }

    public function store(Request $request, PurchaseService $service): RedirectResponse
    {
        $data = $request->validate($this->rules() + ['product_id' => ['required', 'integer', Rule::exists('products', 'id')]], $this->messages());

        $r = $service->record($data, $request->user());

        $msg = "Tersimpan: {$data['qty']} buah.";
        if ($r['surplus'] > 0) {
            $msg .= " Kelebihan {$r['surplus']} buah masuk daftar terima gudang (stok bertambah setelah dicentang).";
        }
        if ($r['shortfall'] > 0) {
            $msg .= " Masih kurang {$r['shortfall']} buah — tetap di daftar, Owner sudah diberi tahu.";
        }
        if ($r['price_changed']) {
            $msg .= ' Harga di Master Produk diperbarui: EGP '.number_format($r['price_changed']['from'] / 100, 2, ',', '.').' → EGP '.number_format($r['price_changed']['to'] / 100, 2, ',', '.').'.';
        }
        if ($r['orders_ready']) {
            $msg .= ' Pesanan lengkap: '.implode(', ', $r['orders_ready']).'.';
        }

        return back()->with('success', $msg);
    }

    /** Hanya Owner (route). */
    public function update(Request $request, int $purchase, PurchaseService $service): RedirectResponse
    {
        $data = $request->validate(array_merge($this->rules(), ['purchased_at' => ['required', 'date', 'before_or_equal:today']]), $this->messages());
        $service->correct($purchase, $data, $request->user());

        return back()->with('success', 'Catatan belanja dikoreksi. Stok dan status pesanan dihitung ulang.');
    }

    /** Hanya Owner: tambah kebutuhan restok gudang (stok yang ingin diisi, tanpa pesanan). */
    public function restockStore(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'qty' => ['required', 'integer', 'min:1', 'max:100000'],
        ], ['qty.min' => 'Jumlah minimal 1.']);

        DB::table('restock_needs')->insert(['product_id' => $d['product_id'], 'qty_needed' => $d['qty'], 'qty_fulfilled' => 0, 'status' => 'open', 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
        Audit::log('restock.create', null, null, $d);

        return back()->with('success', 'Restok ditambahkan ke daftar belanja Tim Mesir.');
    }

    /** Hanya Owner: batalkan restok yang belum dibelanjakan sama sekali. */
    public function restockCancel(int $restock): RedirectResponse
    {
        $n = DB::table('restock_needs')->where('id', $restock)->first();
        abort_unless($n, 404);
        if ($n->qty_fulfilled > 0) {
            return back()->withErrors(['restock' => 'Restok ini sudah mulai dibelanjakan, tidak bisa dibatalkan.']);
        }
        DB::table('restock_needs')->where('id', $restock)->update(['status' => 'cancelled', 'updated_at' => now()]);
        Audit::log('restock.cancel', null, (array) $n);

        return back()->with('success', 'Restok dibatalkan.');
    }

    /** Hanya Owner: kurs default yang otomatis terisi di form Tim Mesir. */
    public function setRate(Request $request): RedirectResponse
    {
        $data = $request->validate(['rate' => ['required', 'integer', 'min:10000', 'max:100000000']]);
        $old = $this->defaultRate();
        DB::table('settings')->updateOrInsert(['key' => 'default_rate'], ['value' => (string) $data['rate'], 'updated_at' => now(), 'created_at' => now()]);
        Audit::log('settings.default_rate', null, ['rate' => $old], ['rate' => $data['rate']]);

        return back()->with('success', 'Kurs default diperbarui.');
    }
}
