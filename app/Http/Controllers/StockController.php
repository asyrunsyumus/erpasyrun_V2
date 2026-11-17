<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Services\WarehouseService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Stok Ready: Owner, Admin, Gudang. Angka stok TIDAK bisa diketik di sini.
 * Selisih hitungan fisik dilaporkan Gudang → disetujui Owner → dibukukan sebagai mutasi opname.
 */
class StockController extends Controller
{
    private function query(string $q, bool $withZero)
    {
        return DB::table('products as p')
            ->leftJoin('product_stock as s', 's.product_id', '=', 'p.id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->whereNull('p.deleted_at')
            ->when(! $withZero, fn ($w) => $w->where('s.qty', '>', 0))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('p.title_latin', 'like', "%{$q}%")->orWhere('p.title_ar', 'like', "%{$q}%")->orWhere('p.sku', $q)))
            ->orderBy('p.title_latin');
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $all = $request->boolean('all');

        $items = $this->query($q, $all)
            ->select(['p.id', 'p.sku', 'p.title_latin', 'p.title_ar', 'p.author', 'p.publisher', 'p.weight_grams', 'p.photo_path', 'c.name as category', DB::raw('COALESCE(s.qty,0) as qty')])
            ->paginate(50)->withQueryString()
            ->through(fn ($r) => [
                'id' => $r->id, 'sku' => $r->sku, 'title_latin' => $r->title_latin, 'title_ar' => $r->title_ar,
                'author' => $r->author, 'publisher' => $r->publisher, 'category' => $r->category,
                'weight_grams' => $r->weight_grams, 'has_photo' => (bool) $r->photo_path, 'qty' => (int) $r->qty,
            ]);

        $counts = [];
        if ($user->hasRole(Role::Owner, Role::Gudang)) {
            $counts = DB::table('stock_counts as sc')->join('users as u', 'u.id', '=', 'sc.counted_by')
                ->orderByRaw("CASE WHEN sc.status = 'submitted' THEN 0 ELSE 1 END")->orderByDesc('sc.id')->limit(20)
                ->get(['sc.id', 'sc.status', 'sc.note', 'sc.created_at', 'u.name as by'])
                ->map(fn ($c) => [
                    'id' => $c->id, 'status' => $c->status, 'note' => $c->note, 'by' => $c->by,
                    'at' => CarbonImmutable::parse($c->created_at)->locale('id')->translatedFormat('j M Y, H.i'),
                    'lines' => DB::table('stock_count_lines as l')->join('products as p', 'p.id', '=', 'l.product_id')
                        ->where('l.stock_count_id', $c->id)
                        ->get(['p.title_latin', 'p.title_ar', 'l.system_qty', 'l.counted_qty'])
                        ->map(fn ($l) => ['title_latin' => $l->title_latin, 'title_ar' => $l->title_ar, 'system_qty' => (int) $l->system_qty, 'counted_qty' => (int) $l->counted_qty]),
                ]);
        }

        return Inertia::render('stock/index', [
            'items' => $items,
            'filters' => ['q' => $q, 'all' => $all],
            'counts' => $counts,
            'canReport' => $user->hasRole(Role::Owner, Role::Gudang),
            'canDecide' => $user->isOwner(),
            'totals' => [
                'titles' => (int) DB::table('product_stock')->where('qty', '>', 0)->count(),
                'units' => (int) DB::table('product_stock')->where('qty', '>', 0)->sum('qty'),
            ],
        ]);
    }

    public function print(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $cols = array_values(array_intersect((array) $request->query('cols', ['sku', 'judul', 'arab', 'qty']), ['sku', 'judul', 'arab', 'penulis', 'penerbit', 'kategori', 'berat', 'qty', 'hitung']));

        return Inertia::render('stock/print', [
            'rows' => $this->query($q, false)->limit(5000)
                ->get(['p.sku', 'p.title_latin', 'p.title_ar', 'p.author', 'p.publisher', 'p.weight_grams', 'c.name as category', DB::raw('COALESCE(s.qty,0) as qty')]),
            'cols' => $cols ?: ['sku', 'judul', 'arab', 'qty'],
            'printedAt' => now()->locale('id')->translatedFormat('l, j F Y H.i'),
        ]);
    }

    public function report(Request $request, WarehouseService $svc): RedirectResponse
    {
        $data = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'lines.*.counted_qty' => ['required', 'integer', 'min:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], ['lines.required' => 'Tambahkan minimal satu kitab yang hitungannya berbeda.']);

        $svc->submitCount($data['lines'], $data['note'] ?? null, $request->user());

        return back()->with('success', 'Laporan selisih terkirim ke Owner. Angka stok berubah setelah Owner menyetujui.');
    }

    public function decide(Request $request, int $count, WarehouseService $svc): RedirectResponse
    {
        $approve = $request->boolean('approve');
        $svc->decideCount($count, $approve, $request->user());

        return back()->with('success', $approve ? 'Disetujui. Stok disamakan dengan hitungan fisik.' : 'Laporan ditolak.');
    }
}
