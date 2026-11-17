<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Log Aktivitas (hanya Owner, hanya-baca). */
class AuditController extends Controller
{
    private const LABELS = [
        'product.price' => 'Mengubah harga kitab',
        'product.price_egp_from_purchase' => 'Harga EGP diperbarui dari belanja Mesir',
        'product.delete' => 'Menghapus kitab',
        'product.weight_from_order' => 'Berat kitab diisi dari form order',
        'customer.update' => 'Mengubah tipe customer',
        'customer.delete' => 'Menghapus customer',
        'maktabah.create' => 'Menambah maktabah',
        'maktabah.delete' => 'Menghapus maktabah',
        'order.label_upload' => 'Mengunggah resi',
        'order.cancel' => 'Membatalkan pesanan',
        'purchase.correct' => 'Mengoreksi catatan belanja Mesir',
        'settings.default_rate' => 'Mengubah kurs default',
        'settings.shop' => 'Mengubah pengaturan toko',
        'baggage.create' => 'Membuat jadwal bagasi',
        'baggage.update' => 'Mengubah jadwal bagasi',
        'baggage.move' => 'Memindah pesanan antar jadwal bagasi',
        'baggage.delete' => 'Menghapus jadwal bagasi',
        'warehouse.receive' => 'Konfirmasi barang diterima',
        'stock.opname_approve' => 'Menyetujui stok opname',
        'stock.opname_reject' => 'Menolak stok opname',
        'expense.create' => 'Mencatat biaya',
        'expense.update' => 'Mengubah biaya',
        'expense.delete' => 'Menghapus biaya',
        'user.create' => 'Membuat akun',
        'user.update' => 'Mengubah akun',
        'user.activate' => 'Mengaktifkan akun',
        'user.deactivate' => 'Menonaktifkan akun',
        'user.reset_password' => 'Mereset password',
        'user.delete' => 'Menghapus akun',
    ];

    public function index(Request $request): Response
    {
        $action = $request->query('action');
        $userId = $request->integer('user') ?: null;

        $items = DB::table('audit_logs as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->when($action, fn ($q) => $q->where('a.action', $action))
            ->when($userId, fn ($q) => $q->where('a.user_id', $userId))
            ->orderByDesc('a.id')
            ->paginate(50)->withQueryString()
            ->through(fn ($r) => [
                'id' => $r->id,
                'at' => CarbonImmutable::parse($r->created_at)->locale('id')->translatedFormat('j M Y, H.i'),
                'user' => $r->name ?? 'Sistem',
                'action' => $r->action,
                'label' => self::LABELS[$r->action] ?? $r->action,
                'subject' => $r->subject_type ? class_basename($r->subject_type).' #'.$r->subject_id : null,
                'before' => $r->before ? json_decode($r->before, true) : null,
                'after' => $r->after ? json_decode($r->after, true) : null,
                'ip' => $r->ip,
            ]);

        return Inertia::render('audit/index', [
            'items' => $items,
            'actions' => collect(self::LABELS)->map(fn ($l, $k) => ['value' => $k, 'label' => $l])->values(),
            'users' => DB::table('users')->orderBy('name')->get(['id', 'name']),
            'filters' => ['action' => $action, 'user' => $userId],
        ]);
    }
}
