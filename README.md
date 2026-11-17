# ERP Maktabah Asyrun Syumus

Sistem order, belanja Mesir, bagasi, gudang, dan keuangan untuk Maktabah Asyrun Syumus.

- Alur kerja per peran: [docs/00-alur-kerja-per-peran.md](docs/00-alur-kerja-per-peran.md)
- Skema database & aturan bisnis: [docs/01-skema-database.md](docs/01-skema-database.md)

## Stack

Laravel 12 · MySQL · Inertia + React + Tailwind · di-hosting di Hostinger.

## Menjalankan di komputer

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

Buat akun Owner pertama (password sementara ditampilkan sekali, wajib diganti saat login pertama):

```bash
php artisan erp:create-owner "Nama Owner" email@domain.com
```

## Tes

```bash
php artisan test
```

## Catatan penting

- `public/build` **ikut di-commit**, karena hosting tidak menjalankan Node. Jalankan `npm run build` sebelum commit setiap kali ada perubahan tampilan.
- Angka keuangan hanya boleh dihitung di `app/Services/FinanceMetrics.php`.
- Menu dan izin akses per peran ditentukan di satu tempat: `app/Support/Navigation.php`.
