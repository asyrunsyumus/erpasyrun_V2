<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;

/**
 * Satu sumber kebenaran untuk menu & siapa yang boleh membuka modul.
 * Route di routes/web.php memakai Navigation::roles('kunci') agar menu dan izin server selalu sama.
 */
class Navigation
{
    /**
     * @return array<string, array{group: string, label: string, icon: string, path: string, roles: list<Role>}>
     */
    public static function modules(): array
    {
        $all = [Role::Owner, Role::Admin, Role::Mesir, Role::Gudang];

        return [
            'dashboard' => ['group' => '', 'label' => 'Beranda', 'icon' => 'home', 'path' => '/dashboard', 'roles' => $all],

            'orders' => ['group' => 'Penjualan', 'label' => 'Order & Invoice', 'icon' => 'cart', 'path' => '/orders', 'roles' => [Role::Owner, Role::Admin]],
            'customers' => ['group' => 'Penjualan', 'label' => 'Customer', 'icon' => 'users', 'path' => '/customers', 'roles' => [Role::Owner, Role::Admin]],
            'cancellations' => ['group' => 'Penjualan', 'label' => 'Pembatalan', 'icon' => 'x', 'path' => '/cancellations', 'roles' => [Role::Owner, Role::Admin]],

            'products' => ['group' => 'Barang', 'label' => 'Produk (Kitab)', 'icon' => 'book', 'path' => '/products', 'roles' => [Role::Owner, Role::Admin]],
            'maktabahs' => ['group' => 'Barang', 'label' => 'Maktabah', 'icon' => 'store', 'path' => '/maktabahs', 'roles' => [Role::Owner, Role::Mesir]],
            'purchases' => ['group' => 'Barang', 'label' => 'Belanja Mesir', 'icon' => 'bag', 'path' => '/purchases', 'roles' => [Role::Owner, Role::Mesir]],
            'baggage' => ['group' => 'Barang', 'label' => 'Jadwal Bagasi', 'icon' => 'plane', 'path' => '/baggage', 'roles' => [Role::Owner]],
            'warehouse' => ['group' => 'Barang', 'label' => 'Gudang & Packing', 'icon' => 'box', 'path' => '/warehouse', 'roles' => [Role::Gudang]],
            'stock' => ['group' => 'Barang', 'label' => 'Stok Ready', 'icon' => 'layers', 'path' => '/stock', 'roles' => [Role::Owner, Role::Admin, Role::Gudang]],

            'reports' => ['group' => 'Keuangan', 'label' => 'Laporan', 'icon' => 'chart', 'path' => '/reports', 'roles' => [Role::Owner]],
            'expenses' => ['group' => 'Keuangan', 'label' => 'Biaya', 'icon' => 'money', 'path' => '/expenses', 'roles' => [Role::Owner]],

            'users' => ['group' => 'Pengaturan', 'label' => 'Pengguna', 'icon' => 'shield', 'path' => '/users', 'roles' => [Role::Owner]],
            'shop' => ['group' => 'Pengaturan', 'label' => 'Toko & Invoice', 'icon' => 'store', 'path' => '/shop-settings', 'roles' => [Role::Owner]],
            'audit' => ['group' => 'Pengaturan', 'label' => 'Log Aktivitas', 'icon' => 'history', 'path' => '/audit', 'roles' => [Role::Owner]],
        ];
    }

    /** String untuk middleware, mis. "role:owner,admin". */
    public static function roles(string $key): string
    {
        return 'role:'.implode(',', array_map(fn (Role $r) => $r->value, self::modules()[$key]['roles']));
    }

    /**
     * @return list<array{key: string, group: string, label: string, icon: string, path: string}>
     */
    public static function for(User $user): array
    {
        $items = [];
        foreach (self::modules() as $key => $m) {
            if ($user->hasRole(...$m['roles'])) {
                $items[] = ['key' => $key, 'group' => $m['group'], 'label' => $m['label'], 'icon' => $m['icon'], 'path' => $m['path']];
            }
        }

        return $items;
    }
}
