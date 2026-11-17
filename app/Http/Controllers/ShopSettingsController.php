<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Pengaturan toko (hanya Owner): data yang tampil di invoice. */
class ShopSettingsController extends Controller
{
    private const KEYS = ['shop_phone', 'invoice_bank', 'invoice_note'];

    public function edit(): Response
    {
        return Inertia::render('shop-settings', [
            'values' => collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => DB::table('settings')->where('key', $k)->value('value') ?? '']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'shop_phone' => ['nullable', 'string', 'max:40'],
            'invoice_bank' => ['nullable', 'string', 'max:500'],
            'invoice_note' => ['nullable', 'string', 'max:1000'],
        ]);
        foreach (self::KEYS as $k) {
            DB::table('settings')->updateOrInsert(['key' => $k], ['value' => $d[$k] ?? null, 'updated_at' => now(), 'created_at' => now()]);
        }
        Audit::log('settings.shop', null, null, $d);

        return back()->with('success', 'Pengaturan disimpan. Invoice berikutnya memakai data ini.');
    }
}
