# Skema Database & Alur Status — ERP Asyrun Syumus

Status: **draft untuk disetujui** · 17 Nov 2026
Stack: Laravel 12 + MySQL 8 (Hostinger) · semua uang disimpan sebagai **integer Rupiah** (EGP dalam piaster/sen) — tidak ada float.

---

## 1. Prinsip yang memperbaiki masalah sistem lama

| # | Prinsip | Akibatnya |
|---|---|---|
| P1 | **Stok = jumlah mutasi.** Tidak ada kolom `stock` yang bisa diketik. Setiap kitab masuk/keluar = 1 baris di `stock_movements`. | Stok tidak bisa "melenceng" diam-diam; setiap angka bisa ditelusuri asalnya. |
| P2 | **Alokasi dipecah per baris order.** `qty_from_stock` + `qty_to_buy` = `qty`. | Pesan 10, stok 7 → 7 dari gudang, 3 dibeli. |
| P3 | **Satu definisi angka keuangan** di satu kelas (`FinanceMetrics`). Dashboard, laporan, export memanggil kelas yang sama. | Tiga layar = satu angka. |
| P4 | **HPP sebenarnya** = harga beli EGP × kurs saat itu + bagian ongkos bagasi (dibagi per berat). | Laba tidak lagi terlalu tinggi. |
| P5 | **Berat wajib > 0** (database `CHECK`). | Kuota bagasi akurat. |
| P6 | **Riwayat status nyata** di `order_status_histories`. | Tahu siapa, kapan, dari → ke. |
| P7 | **Notifikasi per orang** (tabel `notifications` bawaan Laravel). | Dibaca satu orang tidak hilang di orang lain. |
| P8 | **Log audit append-only.** | Perubahan harga/stok/status selalu tercatat. |

---

## 2. Tabel

### Pengguna & keamanan
**users** — `id, name, email (unik), password (argon2id), role [owner|admin|mesir|gudang], is_active, two_factor_secret (terenkripsi), two_factor_confirmed_at, must_change_password, last_login_at, timestamps`

**audit_logs** — `id, user_id, action, subject_type, subject_id, before (json), after (json), ip, user_agent, created_at` · *tidak ada update/delete di level aplikasi*

**settings** — `key, value` (mis. kurs default EGP, batas umur pesanan per tahap, nomor rekening di invoice)

### Master data
**categories** — `id, name`

**maktabahs** — `id, name, area, phone, notes, deleted_at`

**products** — `id, sku (unik), title_ar, title_latin, author, publisher, category_id, weight_grams (NOT NULL, > 0), photo_path, primary_maktabah_id, price_egp (piaster), price_regular, price_reseller, is_active, deleted_at`

**product_maktabah** — `product_id, maktabah_id` → toko cadangan

**customers** — `id, code, name, phone, address, city, type [reguler|reseller], notes, deleted_at`

**imports** — `id, type [products|customers|maktabahs], file_path, status, rows_total, rows_ok, rows_failed, errors (json), user_id, timestamps` → import massal Excel/CSV, dengan pratinjau sebelum disimpan

### Penjualan
**orders** — `id, number (ASY-2611-0001), customer_id, channel [whatsapp|shopee], payment_status [unpaid|trusted|paid], status (lihat §3), subtotal, discount, shipping_fee, total, paid_at, notes, created_by, timestamps, deleted_at`

**order_items** — `id, order_id, product_id, qty, unit_price (dikunci saat order dibuat), line_total, qty_from_stock, qty_to_buy, unit_cost (HPP final, terisi saat lengkap)`

**invoices** — `id, order_id (unik), number, issued_at, due_at` (hanya WhatsApp; total diambil dari order)

**shipping_labels** — `id, order_id, file_path (penyimpanan privat), uploaded_by, timestamps`

**order_status_histories** — `id, order_id, from_status, to_status, user_id, note, created_at`

**cancellations** — `id, order_id, reason, status [pending|approved|rejected], status_before, requested_by, decided_by, decided_at, decision_note, refund_amount, refund_method, refund_proof_path` · **boleh lebih dari satu per order**, tapi hanya satu yang `pending` pada satu waktu

### Pembelian Mesir
**purchase_needs** — kebutuhan belanja per baris order: `id, order_item_id, product_id, qty_needed, qty_fulfilled, status [open|fulfilled|cancelled], created_at` · urutan FIFO = `created_at`

**purchases** — transaksi belanja nyata: `id, product_id, maktabah_id, qty, price_egp, rate (kurs × 10.000), cost_idr (dihitung), purchased_at, user_id, corrected_by, corrected_at`

**purchase_allocations** — `id, purchase_id, purchase_need_id, qty` · kelebihan beli → `stock_movements` tipe `purchase_surplus`

### Bagasi & gudang
**baggage_schedules** — `id, depart_date, carrier_contact, quota_grams (NULL = tanpa batas), cost_idr (ongkos bagasi), status [open|departed|arrived], timestamps`

**baggage_loads** — order yang dipasang ke jadwal: `id, baggage_schedule_id, order_id, weight_grams, assigned_at` · dipasang otomatis FIFO

**inbound_lines** — baris "Barang Diterima dari Mesir": `id, baggage_schedule_id, product_id, qty, received_at, received_by` (satu baris per kitab per jadwal)

**packing_checks** — `id, order_id, order_item_id, checked_by, checked_at` (centang saat packing)

### Stok
**stock_movements** — `id, product_id, qty (+ masuk / − keluar), type [import_awal|purchase_surplus|order_pick|cancel_return|opname], unit_cost_idr, ref_type, ref_id, user_id, note, created_at` · stok tersedia = `SUM(qty)`, di-cache di tabel `product_stock` dan diperbarui dalam transaksi yang sama

**stock_counts / stock_count_lines** — stok opname: Gudang menghitung (`system_qty`, `counted_qty`), Owner menyetujui → mutasi `opname` dengan alasan

### Keuangan
**expenses** — `id, category [operasional|bagasi|lain], amount, spent_at, baggage_schedule_id (opsional), note, attachment_path, user_id`

---

## 3. Alur status pesanan

```
                ┌─────────────── (semua kitab ada di gudang) ───────────────┐
                │                                                            ▼
[MENUNGGU_BAYAR] ──lunas/dipercaya──▶ [BELANJA_MESIR] ──semua terbeli──▶ [MENUNGGU_BAGASI]
                                                                              │ jadwal dibuat (otomatis FIFO)
                                                                              ▼
                     [SELESAI] ◀──packing selesai── [SIAP_PACKING] ◀──diterima gudang── [DALAM_BAGASI]

Dari status mana pun sebelum SELESAI:
  ajukan batal → [MENUNGGU_PEMBATALAN] → setuju → [DIBATALKAN]
                                       → tolak  → kembali ke status sebelumnya (boleh diajukan lagi)
```

| Transisi | Siapa | Efek otomatis |
|---|---|---|
| Buat order | Admin, Owner | Alokasi per baris (P2). Bagian dari stok → mutasi `order_pick` (stok langsung berkurang). Bagian beli → `purchase_needs` **hanya jika** sudah lunas/dipercaya. |
| Tandai lunas | Admin, Owner | Kebutuhan belanja dibuat; pesanan masuk BELANJA_MESIR. |
| Catat belanja | Mesir, Owner | Alokasi FIFO; kelebihan → stok; harga EGP berbeda → harga produk diperbarui + dicatat di audit; kekurangan → notifikasi Owner. |
| Jadwal bagasi | **Owner saja** | Pasang order MENUNGGU_BAGASI FIFO sampai kuota habis; buat `inbound_lines`. |
| Konfirmasi diterima | **Gudang saja** | Order yang semua kitabnya sudah diterima → SIAP_PACKING. |
| Selesai packing | **Gudang saja** | Wajib semua item tercentang + resi ada. HPP final dikunci. |
| Pembatalan disetujui | Admin, Owner | Kitab yang sudah diambil/terbeli → mutasi `cancel_return` (kembali ke stok). Kebutuhan belanja yang belum terbeli ditutup. |

Setiap transisi berjalan di dalam **satu transaksi database** dengan kunci baris (`lockForUpdate`), sehingga dua orang tidak bisa memproses hal yang sama bersamaan.

---

## 4. Definisi angka (FinanceMetrics)

| Angka | Rumus | Kapan dihitung |
|---|---|---|
| **Penjualan** | Σ (subtotal − diskon) order yang tidak dibatalkan | tanggal order |
| **Kas masuk** | Σ total order yang dibayar − refund | tanggal bayar |
| **Piutang** | Σ total order WhatsApp yang belum lunas, tidak dibatalkan | per hari ini, dengan umur |
| **HPP** | Σ qty × unit_cost (beli EGP × kurs + bagian bagasi per gram; dari stok: biaya rata-rata) | tanggal order |
| **Laba kotor** | Penjualan − HPP | |
| **Laba bersih** | Laba kotor − biaya operasional − biaya lain | |
| **Ongkir** | Diteruskan ke pembeli, **tidak** dihitung sebagai penjualan | |

---

## 5. Hak akses (diterapkan di server)

| Data / aksi | Owner | Admin | Mesir | Gudang |
|---|:-:|:-:|:-:|:-:|
| Harga jual | ✓ | lihat | – | – |
| Harga beli EGP, HPP, laba | ✓ | – | input saat belanja | – |
| Data pembeli | ✓ | ✓ | – | nama & alamat untuk packing |
| Buat / ubah order | ✓ | ✓ | – | – |
| Catat belanja | ✓ | – | ✓ | – |
| Koreksi belanja | ✓ | – | – | – |
| Jadwal bagasi | ✓ | – | – | – |
| Terima barang, selesai packing | – | – | – | ✓ |
| Laporan, biaya, pengguna | ✓ | – | – | – |
| Hapus permanen (dengan password ulang) | ✓ | – | – | – |
| Import massal | ✓ | ✓ (produk, customer) | – | – |
