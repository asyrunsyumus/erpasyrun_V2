<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Mesir = 'mesir';
    case Gudang = 'gudang';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin / Sales',
            self::Mesir => 'Tim Operasional Mesir',
            self::Gudang => 'Tim Gudang Indonesia',
        };
    }

    /** Halaman pertama setelah login. */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Owner, self::Admin => 'dashboard',
            self::Mesir => 'dashboard',
            self::Gudang => 'dashboard',
        };
    }
}
