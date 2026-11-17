<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Maktabah;
use App\Models\Product;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $isOwner = $request->user()->isOwner();
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status', 'aktif');

        $items = Product::query()
            ->with(['category:id,name', 'primaryMaktabah:id,name', 'altMaktabahs:id', 'stock'])
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('title_latin', 'like', "%{$q}%")
                ->orWhere('title_ar', 'like', "%{$q}%")->orWhere('sku', $q)->orWhere('author', 'like', "%{$q}%")))
            ->when($status === 'aktif', fn ($w) => $w->where('is_active', true))
            ->when($status === 'nonaktif', fn ($w) => $w->where('is_active', false))
            ->orderBy('title_latin')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Product $p) => $this->present($p, $isOwner));

        return Inertia::render('master/products', [
            'items' => $items,
            'filters' => ['q' => $q, 'status' => $status],
            'isOwner' => $isOwner,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'maktabahs' => Maktabah::orderBy('name')->get(['id', 'name']),
            'defaultRate' => (int) (DB::table('settings')->where('key', 'default_rate')->value('value') ?? 0),
        ]);
    }

    /** Data yang dikirim ke browser. Harga beli EGP hanya untuk Owner — tidak sekadar disembunyikan di layar. */
    private function present(Product $p, bool $isOwner): array
    {
        $row = [
            'id' => $p->id,
            'sku' => $p->sku,
            'title_ar' => $p->title_ar,
            'title_latin' => $p->title_latin,
            'author' => $p->author,
            'publisher' => $p->publisher,
            'category_id' => $p->category_id,
            'category' => $p->category?->name,
            'weight_grams' => $p->weight_grams,
            'primary_maktabah_id' => $p->primary_maktabah_id,
            'primary_maktabah' => $p->primaryMaktabah?->name,
            'alt_maktabah_ids' => $p->altMaktabahs->pluck('id'),
            'is_active' => $p->is_active,
            'has_photo' => (bool) $p->photo_path,
            'stock' => (int) ($p->stock?->qty ?? 0),
            'price_regular' => $p->price_regular,
            'price_reseller' => $p->price_reseller,
        ];
        if ($isOwner) {
            $row['price_egp'] = $p->price_egp;
        }

        return $row;
    }

    private function rules(Request $request, ?Product $product = null): array
    {
        $rules = [
            'title_ar' => ['nullable', 'string', 'max:255'],
            'title_latin' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:80'],
            'weight_grams' => ['nullable', 'integer', 'min:1', 'max:50000'],
            'primary_maktabah_id' => ['nullable', 'integer', Rule::exists('maktabahs', 'id')->whereNull('deleted_at')],
            'alt_maktabah_ids' => ['array'],
            'alt_maktabah_ids.*' => ['integer', Rule::exists('maktabahs', 'id')->whereNull('deleted_at')],
            'is_active' => ['boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ];
        if ($request->user()->isOwner()) {
            $rules += [
                'price_egp' => ['required', 'integer', 'min:0'],
                'price_regular' => ['required', 'integer', 'min:0'],
                'price_reseller' => ['required', 'integer', 'min:0'],
            ];
        }

        return $rules;
    }

    private function save(Request $request, Product $product): Product
    {
        $data = $request->validate($this->rules($request, $product), [
            'weight_grams.min' => 'Berat harus lebih dari 0 gram, atau kosongkan bila belum tahu.',
        ]);

        $allowed = $request->user()->isOwner() ? [...Product::INFO_FIELDS, ...Product::PRICE_FIELDS] : Product::INFO_FIELDS;
        $values = array_intersect_key($data, array_flip($allowed));
        $values['category_id'] = ! empty($data['category'])
            ? Category::firstOrCreate(['name' => trim($data['category'])])->id
            : null;

        DB::transaction(function () use ($product, $values, $data, $request) {
            $product->fill($values)->save();
            $product->altMaktabahs()->sync(array_diff($data['alt_maktabah_ids'] ?? [], [$product->primary_maktabah_id]));

            if ($request->hasFile('photo')) {
                if ($product->photo_path) {
                    Storage::disk('local')->delete($product->photo_path);
                }
                $product->photo_path = $request->file('photo')->store('product-photos', 'local');
                $product->save();
            }
        });

        Audit::changes('product.price', $product, Product::PRICE_FIELDS);

        return $product;
    }

    public function store(Request $request): RedirectResponse
    {
        $p = $this->save($request, new Product);
        if (! $request->user()->isOwner()) {
            // Admin boleh menambah kitab, tapi harganya diisi Owner.
            return back()->with('success', "Kitab {$p->title_latin} ditambahkan. Harga jualnya akan diisi oleh Owner.");
        }

        return back()->with('success', "Kitab {$p->title_latin} ditambahkan.");
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->save($request, $product);

        return back()->with('success', 'Perubahan disimpan.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($why = $product->deletionBlocker()) {
            return back()->withErrors(['delete' => $why]);
        }
        $product->delete();
        Audit::log('product.delete', $product, $product->only('sku', 'title_latin'));

        return back()->with('success', "Kitab {$product->title_latin} dihapus.");
    }

    /** Foto disimpan privat & hanya bisa dilihat pengguna yang login. */
    public function photo(Product $product): StreamedResponse
    {
        abort_unless($product->photo_path && Storage::disk('local')->exists($product->photo_path), 404);

        return Storage::disk('local')->response($product->photo_path, null, ['Cache-Control' => 'private, max-age=86400']);
    }
}
