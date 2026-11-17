<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Maktabah;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Import massal Produk / Customer / Maktabah dari .xlsx atau .csv.
 * Alur: parse() → pratinjau (baris valid & error) → apply() menyimpan baris valid saja.
 * Baris dengan kode/SKU/nama yang sudah ada akan DIPERBARUI, bukan digandakan.
 */
class MasterImport
{
    public const MAX_ROWS = 5000;

    /** Kolom template per jenis. Kolom bertanda * wajib. */
    public const COLUMNS = [
        'products' => ['sku', 'judul_latin*', 'judul_arab', 'penulis', 'penerbit', 'kategori', 'berat_gram*', 'maktabah_utama', 'harga_egp', 'harga_reguler', 'harga_reseller', 'stok_awal'],
        'customers' => ['kode', 'nama*', 'telepon', 'alamat', 'kota', 'tipe'],
        'maktabahs' => ['nama*', 'area', 'telepon', 'catatan'],
    ];

    /** Kolom yang hanya diproses bila pengimpor adalah Owner. */
    private const OWNER_ONLY = ['harga_egp', 'harga_reguler', 'harga_reseller', 'stok_awal'];

    /** @return list<array<string,string>> */
    public function readRows(string $path, string $ext): array
    {
        if ($ext === 'csv' || $ext === 'txt') {
            $raw = (string) file_get_contents($path);
            $firstLine = strtok($raw, "\n") ?: '';
            $opt = new CsvOptions;
            $opt->FIELD_DELIMITER = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            $reader = new CsvReader($opt);
        } else {
            $reader = new XlsxReader;
        }

        $reader->open($path);
        $header = null;
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : trim((string) $v), $row->toArray());
                if ($header === null) {
                    $header = array_map(fn ($h) => Str::of($h)->lower()->replace('*', '')->trim()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString(), $cells);
                    continue;
                }
                if (implode('', $cells) === '') {
                    continue;
                }
                $assoc = [];
                foreach ($header as $i => $h) {
                    if ($h !== '') {
                        $assoc[$h] = $cells[$i] ?? '';
                    }
                }
                $rows[] = $assoc;
                if (count($rows) > self::MAX_ROWS) {
                    break 2;
                }
            }
            break; // hanya sheet pertama
        }
        $reader->close();

        return $rows;
    }

    /** Ubah "Rp 150.000" / "150,000" / "150000" → 150000. */
    private static function int(?string $v): ?int
    {
        if ($v === null || trim($v) === '') {
            return null;
        }
        $digits = preg_replace('/[^0-9\-]/', '', explode(',', str_replace('.', '', $v))[0]);

        return $digits === '' || $digits === '-' ? null : (int) $digits;
    }

    /**
     * @return array{rows: list<array{line:int, data:array, errors:list<string>, action:string}>, missing_columns: list<string>}
     */
    public function preview(string $type, array $rows, User $user): array
    {
        $required = array_map(fn ($c) => rtrim($c, '*'), array_filter(self::COLUMNS[$type], fn ($c) => str_ends_with($c, '*')));
        $present = $rows ? array_keys($rows[0]) : [];
        $missing = array_values(array_diff($required, $present));

        $out = [];
        foreach ($rows as $i => $r) {
            $line = $i + 2; // baris 1 = judul kolom
            [$data, $errors, $action] = match ($type) {
                'products' => $this->checkProduct($r, $user),
                'customers' => $this->checkCustomer($r),
                'maktabahs' => $this->checkMaktabah($r),
            };
            $out[] = ['line' => $line, 'data' => $data, 'errors' => $errors, 'action' => $action];
        }

        return ['rows' => $out, 'missing_columns' => $missing];
    }

    private function checkProduct(array $r, User $user): array
    {
        $d = [
            'sku' => $r['sku'] ?? '',
            'title_latin' => $r['judul_latin'] ?? '',
            'title_ar' => $r['judul_arab'] ?? '',
            'author' => $r['penulis'] ?? '',
            'publisher' => $r['penerbit'] ?? '',
            'category' => $r['kategori'] ?? '',
            'weight_grams' => self::int($r['berat_gram'] ?? null),
            'maktabah' => $r['maktabah_utama'] ?? '',
        ];
        if ($user->isOwner()) {
            $egp = $r['harga_egp'] ?? '';
            $d['price_egp'] = $egp === '' ? null : (int) round(((float) str_replace(',', '.', preg_replace('/[^0-9.,]/', '', $egp))) * 100);
            $d['price_regular'] = self::int($r['harga_reguler'] ?? null);
            $d['price_reseller'] = self::int($r['harga_reseller'] ?? null);
            $d['initial_stock'] = self::int($r['stok_awal'] ?? null);
        }

        $v = Validator::make($d, [
            'title_latin' => ['required', 'max:255'],
            'weight_grams' => ['required', 'integer', 'min:1', 'max:50000'],
            'price_regular' => ['nullable', 'integer', 'min:0'],
            'price_reseller' => ['nullable', 'integer', 'min:0'],
            'initial_stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ], [
            'title_latin.required' => 'judul_latin kosong',
            'weight_grams.required' => 'berat_gram kosong (wajib untuk kuota bagasi)',
            'weight_grams.min' => 'berat_gram harus lebih dari 0',
            'weight_grams.integer' => 'berat_gram harus angka',
        ]);
        $errors = $v->errors()->all();

        if ($d['maktabah'] !== '' && ! Maktabah::where('name', $d['maktabah'])->exists()) {
            $errors[] = "maktabah \"{$d['maktabah']}\" belum terdaftar — tambahkan dulu di menu Maktabah";
        }

        $exists = $d['sku'] !== '' && Product::withTrashed()->where('sku', $d['sku'])->exists();

        return [$d, $errors, $exists ? 'perbarui' : 'baru'];
    }

    private function checkCustomer(array $r): array
    {
        $tipe = Str::lower($r['tipe'] ?? '');
        $d = [
            'code' => $r['kode'] ?? '',
            'name' => $r['nama'] ?? '',
            'phone' => $r['telepon'] ?? '',
            'address' => $r['alamat'] ?? '',
            'city' => $r['kota'] ?? '',
            'type' => $tipe === '' ? 'reguler' : $tipe,
        ];
        $errors = Validator::make($d, [
            'name' => ['required', 'max:120'],
            'type' => ['in:reguler,reseller'],
        ], ['name.required' => 'nama kosong', 'type.in' => 'tipe harus "reguler" atau "reseller"'])->errors()->all();

        $exists = $d['code'] !== '' && Customer::where('code', $d['code'])->exists();

        return [$d, $errors, $exists ? 'perbarui' : 'baru'];
    }

    private function checkMaktabah(array $r): array
    {
        $d = ['name' => $r['nama'] ?? '', 'area' => $r['area'] ?? '', 'phone' => $r['telepon'] ?? '', 'notes' => $r['catatan'] ?? ''];
        $errors = Validator::make($d, ['name' => ['required', 'max:120']], ['name.required' => 'nama kosong'])->errors()->all();
        $exists = $d['name'] !== '' && Maktabah::where('name', $d['name'])->exists();

        return [$d, $errors, $exists ? 'perbarui' : 'baru'];
    }

    /** Simpan semua baris valid dalam satu transaksi. @return int jumlah baris tersimpan */
    public function apply(string $type, array $previewRows, User $user): int
    {
        $ok = array_filter($previewRows, fn ($r) => $r['errors'] === []);

        DB::transaction(function () use ($type, $ok, $user) {
            foreach ($ok as $r) {
                $d = $r['data'];
                match ($type) {
                    'products' => $this->saveProduct($d, $user),
                    'customers' => $this->saveCustomer($d),
                    'maktabahs' => Maktabah::updateOrCreate(['name' => $d['name']], array_filter($d, fn ($v) => $v !== '')),
                };
            }
        });

        return count($ok);
    }

    private function saveProduct(array $d, User $user): void
    {
        $values = array_filter([
            'title_latin' => $d['title_latin'],
            'title_ar' => $d['title_ar'],
            'author' => $d['author'],
            'publisher' => $d['publisher'],
            'weight_grams' => $d['weight_grams'],
        ], fn ($v) => $v !== '' && $v !== null);

        if ($d['category'] !== '') {
            $values['category_id'] = Category::firstOrCreate(['name' => $d['category']])->id;
        }
        if ($d['maktabah'] !== '') {
            $values['primary_maktabah_id'] = Maktabah::where('name', $d['maktabah'])->value('id');
        }
        if ($user->isOwner()) {
            foreach (['price_egp', 'price_regular', 'price_reseller'] as $f) {
                if ($d[$f] !== null) {
                    $values[$f] = $d[$f];
                }
            }
        }

        $product = $d['sku'] !== '' ? Product::withTrashed()->firstWhere('sku', $d['sku']) : null;
        if ($product) {
            $product->fill($values);
            $product->deleted_at = null;
            $product->save();
        } else {
            $product = Product::create(['sku' => $d['sku'] ?: null] + $values);
        }

        // Stok awal: hanya sekali per kitab, dan hanya bila kitab belum punya mutasi stok apa pun.
        $initial = $d['initial_stock'] ?? null;
        if ($user->isOwner() && $initial > 0 && ! DB::table('stock_movements')->where('product_id', $product->id)->exists()) {
            app(StockLedger::class)->move($product->id, $initial, 'import_awal', $user->id, note: 'Stok awal dari import');
        }
    }

    private function saveCustomer(array $d): void
    {
        $values = array_filter(['name' => $d['name'], 'phone' => $d['phone'], 'address' => $d['address'], 'city' => $d['city'], 'type' => $d['type']], fn ($v) => $v !== '');
        $c = $d['code'] !== '' ? Customer::firstWhere('code', $d['code']) : null;
        if ($c) {
            $c->update($values);
        } else {
            $c = new Customer($values);
            $c->code = $d['code'] !== '' ? $d['code'] : null; // kosong → kode otomatis
            $c->save();
        }
    }
}
