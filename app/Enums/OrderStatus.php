<?php

namespace App\Enums;

enum OrderStatus: string
{
    case MenungguBayar = 'menunggu_bayar';
    case BelanjaMesir = 'belanja_mesir';
    case MenungguBagasi = 'menunggu_bagasi';
    case DalamBagasi = 'dalam_bagasi';
    case SiapPacking = 'siap_packing';
    case Selesai = 'selesai';
    case MenungguPembatalan = 'menunggu_pembatalan';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::MenungguBayar => 'Menunggu bayar',
            self::BelanjaMesir => 'Belanja di Mesir',
            self::MenungguBagasi => 'Menunggu bagasi',
            self::DalamBagasi => 'Dalam perjalanan',
            self::SiapPacking => 'Siap packing',
            self::Selesai => 'Selesai',
            self::MenungguPembatalan => 'Menunggu persetujuan batal',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    /** Penjelasan singkat untuk kotak info di halaman detail: apa yang ditunggu & oleh siapa. */
    public function waitingFor(): string
    {
        return match ($this) {
            self::MenungguBayar => 'Menunggu pembeli membayar. Setelah uang masuk, tekan Tandai lunas — kitab yang perlu dibeli langsung masuk daftar belanja Tim Mesir.',
            self::BelanjaMesir => 'Tim Mesir sedang membelikan kitab. Tidak ada yang perlu dilakukan Admin.',
            self::MenungguBagasi => 'Semua kitab sudah terbeli. Menunggu Owner membuat jadwal bagasi.',
            self::DalamBagasi => 'Kitab sedang dalam perjalanan dari Mesir. Menunggu Tim Gudang mengonfirmasi barang diterima.',
            self::SiapPacking => 'Semua kitab sudah di gudang. Menunggu Tim Gudang packing. Pastikan resi sudah diunggah.',
            self::Selesai => 'Pesanan sudah dipacking dan diserahkan ke ekspedisi.',
            self::MenungguPembatalan => 'Pengajuan pembatalan sedang menunggu keputusan.',
            self::Dibatalkan => 'Pesanan dibatalkan.',
        };
    }

    /** Urutan langkah untuk indikator di halaman detail. */
    public static function steps(): array
    {
        return [self::MenungguBayar, self::BelanjaMesir, self::MenungguBagasi, self::DalamBagasi, self::SiapPacking, self::Selesai];
    }
}
