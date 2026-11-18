<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Services\MasterImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    private const LABELS = ['products' => 'Produk (Kitab)', 'customers' => 'Customer', 'maktabahs' => 'Maktabah'];

    /** Admin boleh import produk & customer; maktabah hanya Owner. */
    private function allowedTypes(Request $request): array
    {
        return $request->user()->hasRole(Role::Owner) ? ['products', 'customers', 'maktabahs'] : ['products', 'customers'];
    }

    private function guardType(Request $request, string $type): void
    {
        abort_unless(in_array($type, $this->allowedTypes($request), true), 403);
    }

    public function template(Request $request, string $type): StreamedResponse
    {
        $this->guardType($request, $type);
        $cols = MasterImport::COLUMNS[$type];
        if (! $request->user()->isOwner()) {
            $cols = array_values(array_filter($cols, fn ($c) => ! str_starts_with($c, 'harga') && $c !== 'stok_awal'));
        }
        // Contoh isi per nama kolom (tanpa tanda *), agar tetap benar walau urutan kolom berubah.
        $samples = [
            'products' => ['sku' => '', 'judul_latin' => 'Fathul Qarib', 'judul_arab' => 'فتح القريب المجيب', 'penulis' => 'Ibnu Qasim', 'penerbit' => 'Dar Ibn Hazm', 'pentahqiq' => 'Syaikh Fulan', 'kategori' => 'Fiqih', 'berat_gram' => '420', 'maktabah_utama' => 'Dar al-Salam', 'harga_egp' => '85', 'harga_reguler' => '150000', 'harga_reseller' => '135000', 'stok_awal' => '0'],
            'customers' => ['kode' => '', 'nama' => 'Ahmad Fauzi', 'telepon' => '081234567890', 'alamat' => 'Jl. KH. Hasyim Asyari 12', 'kota' => 'Jombang', 'kelurahan' => 'Denanyar', 'kecamatan' => 'Jombang', 'kode_pos' => '61419', 'tipe' => 'reseller'],
            'maktabahs' => ['nama' => 'Dar al-Salam', 'area' => 'Al-Azhar', 'telepon' => '+20 2 2345 6789', 'whatsapp' => '+20 10 0000 0000', 'link_gmaps' => 'https://maps.app.goo.gl/contoh', 'catatan' => ''],
        ][$type];
        $example = array_map(fn ($c) => $samples[rtrim($c, '*')] ?? '', $cols);

        return response()->streamDownload(function () use ($cols, $example) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8 (huruf Arab)
            fputcsv($out, $cols);
            fputcsv($out, $example);
            fclose($out);
        }, "template-{$type}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create(Request $request): Response
    {
        $types = $this->allowedTypes($request);

        return Inertia::render('master/import', [
            'types' => array_map(fn ($t) => ['key' => $t, 'label' => self::LABELS[$t], 'columns' => MasterImport::COLUMNS[$t]], $types),
            'selected' => in_array($request->query('type'), $types, true) ? $request->query('type') : $types[0],
            'isOwner' => $request->user()->isOwner(),
        ]);
    }

    public function upload(Request $request, MasterImport $importer): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in($this->allowedTypes($request))],
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,csv,txt'],
        ], ['file.mimes' => 'File harus berformat .xlsx atau .csv', 'file.max' => 'Ukuran file maksimal 10 MB']);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $path = $file->store('imports', 'local');

        try {
            $rows = $importer->readRows(Storage::disk('local')->path($path), $ext);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['file' => 'File tidak bisa dibaca. Pastikan formatnya .xlsx atau .csv dan tidak dikunci password.']);
        }

        if (count($rows) === 0) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['file' => 'File kosong — tidak ada baris data di bawah judul kolom.']);
        }
        if (count($rows) > MasterImport::MAX_ROWS) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['file' => 'Maksimal '.MasterImport::MAX_ROWS.' baris per file. Pecah file menjadi beberapa bagian.']);
        }

        $preview = $importer->preview($data['type'], $rows, $request->user());
        $failed = count(array_filter($preview['rows'], fn ($r) => $r['errors'] !== []));

        $id = DB::table('imports')->insertGetId([
            'type' => $data['type'],
            'file_path' => $path,
            'status' => 'preview',
            'rows_total' => count($rows),
            'rows_ok' => count($rows) - $failed,
            'rows_failed' => $failed,
            'errors' => json_encode(['missing_columns' => $preview['missing_columns']]),
            'user_id' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('imports.show', $id);
    }

    public function show(Request $request, int $import, MasterImport $importer): Response
    {
        $row = $this->own($request, $import);
        $preview = $importer->preview($row->type, $importer->readRows(Storage::disk('local')->path($row->file_path), pathinfo($row->file_path, PATHINFO_EXTENSION)), $request->user());

        return Inertia::render('master/import-preview', [
            'import' => ['id' => $row->id, 'type' => $row->type, 'label' => self::LABELS[$row->type], 'status' => $row->status, 'rows_ok' => $row->rows_ok, 'rows_failed' => $row->rows_failed],
            'missing' => $preview['missing_columns'],
            'rows' => array_slice($preview['rows'], 0, 500),
            'truncated' => count($preview['rows']) > 500,
        ]);
    }

    public function confirm(Request $request, int $import, MasterImport $importer): RedirectResponse
    {
        $row = $this->own($request, $import);
        abort_unless($row->status === 'preview', 409, 'Import ini sudah diproses.');

        $preview = $importer->preview($row->type, $importer->readRows(Storage::disk('local')->path($row->file_path), pathinfo($row->file_path, PATHINFO_EXTENSION)), $request->user());
        if ($preview['missing_columns']) {
            return back()->withErrors(['import' => 'Kolom wajib tidak ada: '.implode(', ', $preview['missing_columns'])]);
        }

        $saved = $importer->apply($row->type, $preview['rows'], $request->user());

        DB::table('imports')->where('id', $row->id)->update(['status' => 'done', 'rows_ok' => $saved, 'updated_at' => now()]);
        Storage::disk('local')->delete($row->file_path);

        $back = ['products' => 'products.index', 'customers' => 'customers.index', 'maktabahs' => 'maktabahs.index'][$row->type];

        return redirect()->route($back)->with('success', "{$saved} baris berhasil diimpor".($row->rows_failed ? ", {$row->rows_failed} baris dilewati karena bermasalah." : '.'));
    }

    private function own(Request $request, int $id): object
    {
        $row = DB::table('imports')->where('id', $id)->first();
        abort_unless($row && ($row->user_id === $request->user()->id), 404);
        $this->guardType($request, $row->type);

        return $row;
    }
}
