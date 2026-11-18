# Panduan Pengguna ERP Asyrun Syumus

Panduan singkat untuk setiap peran. Kamu hanya melihat menu yang menjadi tugasmu. Kalau ada menu yang tidak terlihat, berarti itu bukan bagianmu.

Alamat sistem: **https://asyrunsyumus.com**

---

## 1. Masuk ke sistem

1. Buka alamat sistem, lalu isi **email** dan **password**.
2. Kalau ini pertama kali masuk, sistem meminta kamu **mengganti password sementara**. Buat password baru minimal 10 karakter, berisi huruf dan angka.
3. **Owner** juga memasukkan **kode 6 digit** dari aplikasi Google Authenticator atau Microsoft Authenticator di HP.
4. Lupa password? Hubungi Owner. Owner bisa membuatkan password sementara baru.

**Jangan** bagikan password atau kode ke siapa pun.

---

## 2. Hal umum di semua peran

- **Lonceng 🔔** di kanan atas (di HP ada di bilah atas) menunjukkan notifikasi yang belum dibaca. Notifikasi hanya untukmu.
- **Angka di kartu Beranda** menunjukkan tugas yang perlu kamu kerjakan hari ini. Klik kartunya untuk langsung ke halamannya.
- **Warna status pesanan:**
  - Kuning: sedang menunggu sesuatu.
  - Biru: sedang diproses.
  - Hijau: selesai.
  - Abu-abu: dibatalkan.

---

## 3. Admin / Sales

### a. Membuat pesanan baru
1. Buka **Order & Invoice**, lalu tekan **Order baru**.
2. **Pilih pembeli.** Ketik nama atau nomor HP. Kalau belum terdaftar, tambahkan dulu di menu **Customer**. Tipe pembeli (Reguler atau Reseller) menentukan harga.
3. **Tambah kitab.** Ketik judulnya, lalu tekan kitab yang muncul. Atur jumlahnya dengan tombol + dan -.
   - Kalau kitab kuning **"Berat belum ada"**, isi beratnya dalam gram. Setelah disimpan, berat itu tersimpan di data kitab, jadi tidak perlu diisi lagi.
4. **Pilih saluran:** WhatsApp atau Shopee.
5. **Pilih status bayar** (untuk WhatsApp):
   - **Belum bayar:** pesanan ditahan. Kitab belum dibelanjakan.
   - **Belum bayar (dibelikan dulu):** pembeli dipercaya. Kitab langsung dibelanjakan.
   - **Lunas:** uang sudah masuk. Kitab langsung dibelanjakan.
   - Untuk Shopee, status ini tidak ada. Shopee otomatis dianggap lunas, dan tidak ada invoice.
6. Cek **total tagihan** di kanan, lalu tekan **Simpan order**.

### b. Menandai lunas
1. Buka pesanan, lalu tekan **Tandai lunas**.
2. Isi tanggal uang masuk.
3. **Hanya tandai kalau uangnya benar-benar sudah masuk.** Langkah ini tidak bisa dibatalkan.

### c. Mencetak invoice
1. Buka pesanan, lalu tekan **Cetak invoice**.
2. Di halaman invoice, tekan **Cetak / simpan PDF**.

### d. Mengunggah resi
1. Buat resi di luar sistem (misalnya Everpro untuk WhatsApp, atau unduh dari Shopee).
2. Buka pesanan, bagian **Resi pengiriman**, lalu pilih file PDF atau gambar.
3. Tim Gudang bisa langsung membuka dan mencetak resinya.

### e. Membatalkan pesanan
1. Buka pesanan, lalu tekan **Ajukan pembatalan**. Tulis alasannya.
2. Owner atau kamu sendiri bisa menyetujui di menu **Pembatalan**.
3. Kalau disetujui, kitab yang sudah ada kembali ke stok.
4. Kalau ditolak, pesanan kembali berjalan dan boleh diajukan lagi.

### f. Customer dan Produk
- **Customer:** tambah, ubah, atau cari pembeli. Pembeli yang sudah punya pesanan tidak bisa dihapus.
- **Produk (Kitab):** tambah kitab baru, isi judul, penulis, kategori, berat, dan foto. **Harga hanya bisa diubah Owner.**
- **Import:** bisa mengunggah daftar dari Excel atau CSV. Unduh template dulu di halaman Import, lalu isi. Sistem menampilkan pratinjau sebelum menyimpan.

---

## 4. Tim Operasional Mesir

Tugasmu: belanja kitab sesuai daftar, lalu catat yang benar-benar kamu beli.

### a. Sebelum berangkat
1. Buka **Belanja Mesir**. Daftar sudah dikelompokkan **per maktabah**, jadi satu maktabah bisa dikerjakan sekali jalan.
2. Cocokkan kitab dengan **foto**, bukan hanya judul.
3. Kalau ada tulisan **"Toko cadangan"**, itu toko lain yang menjual kitab yang sama. Dipakai kalau toko utama kehabisan.
4. Tekan **Export PDF** untuk mencetak daftar, supaya bisa dibawa tanpa HP.

### b. Mencatat belanja
1. Di kitab yang sudah dibeli, tekan **Beli**.
2. Isi:
   - **Maktabah:** tempat kamu benar-benar membeli. Kalau beda dari yang tertulis, ganti di sini.
   - **Jumlah dibeli:** yang benar-benar kamu dapat.
   - **Harga satuan (EGP):** harga hari itu.
   - **Kurs:** kurs Rupiah per EGP saat bertransaksi.
   - **Tanggal beli.**
3. Tekan **Simpan**. **Catat satu kitab satu per satu.**
4. **Catatan yang sudah disimpan tidak bisa diubah.** Kalau salah ketik, hubungi Owner.

### c. Restok gudang
- Di bagian **Restok gudang** ada kitab yang Owner minta untuk diisi stoknya. Beli seperti biasa lewat tombol **Beli**.

### d. Riwayat
- Tab **Riwayat** menampilkan catatan belanjamu sendiri.

---

## 5. Tim Gudang Indonesia

Tugasmu: menerima barang dari Mesir, packing, lalu menyerahkan ke ekspedisi. Ada dua tombol yang hanya kamu yang bisa tekan: **centang barang diterima** dan **Selesai** packing.

### a. Menerima barang dari Mesir
1. Buka **Gudang & Packing**, lalu tab **1. Dari Mesir**.
2. Barang dikelompokkan per **jadwal bagasi**.
3. Cocokkan fisik kitab dengan **foto**. Lalu:
   - Centang satu per satu, atau
   - Tekan **Centang semua** di jadwal itu.
4. **Hanya centang yang benar-benar sudah ada di tanganmu.**
5. Ada juga bagian **Restok & kelebihan belanja**. Stok di data produk **bertambah setelah kamu mencentangnya di sini.**

### b. Packing pesanan
1. Buka tab **2. Siap packing**. Pesanan dikelompokkan per pembeli.
2. Packing sambil mencentang setiap kitab di daftar.
3. Tekan **Lihat / cetak resi** untuk mencetak resi.
4. Setelah semua kitab tercentang dan paket tersegel, tekan **Selesai**.
   - Tombol Selesai baru aktif kalau semua kitab sudah tercentang dan resi sudah diunggah Admin.
   - Artinya: paket sudah siap **diserahkan ke ekspedisi**.

### c. Stok Ready
- Menampilkan semua kitab yang ada di gudang. Bisa dicari, dan bisa diunduh sebagai PDF untuk menghitung fisik.
- **Angka stok tidak bisa diubah di sini.**
- Kalau hitungan fisikmu beda dengan sistem:
  1. Tekan **Hitungan beda** di kitab itu.
  2. Isi jumlah fisiknya, lalu tekan **Kirim ke Owner**.
  3. Stok baru berubah setelah Owner menyetujui.

---

## 6. Owner

Kamu bisa melakukan semua yang dilakukan peran lain, ditambah hal-hal berikut.

### a. Beranda
- Kartu **Tugas Anda hari ini** menunjukkan pekerjaan yang menunggu.
- **Keuangan bulan ini:** penjualan, uang masuk, untung bersih, dan piutang. Angkanya sama di Beranda dan Laporan.
- **Posisi pesanan:** jumlah pesanan di setiap tahap. Kotak kuning berarti tertahan terlalu lama.

### b. Jadwal Bagasi
1. Tekan **Jadwal baru**. Isi tanggal berangkat, kontak jasa bagasi, kuota kg (kosongkan kalau tanpa batas), dan ongkos bagasi.
2. Pesanan yang menunggu langsung dipasang otomatis, dari yang paling lama dan jadwal terdekat.
3. Kalau pesanan belum terpasang, tekan **Cocokkan ke jadwal terdekat**.
4. Untuk memindahkan semua pesanan dari satu jadwal ke jadwal lain, tekan **Pindah**. Jadwal yang sudah kosong bisa dihapus.

### c. Harga dan kalkulator untung
- Di **Produk**, buka kitab lalu tekan ubah. Isi harga beli EGP, harga reguler, dan harga reseller.
- Kalkulator di bawahnya menampilkan modal dan untung per kitab. Isi kurs dan ongkos bagasi per kg untuk melihat perkiraannya.

### d. Restok gudang
- Di **Belanja Mesir**, tekan **Tambah restok**, pilih kitab, dan isi jumlahnya.
- Restok yang sudah dibelanjakan sebagian tidak bisa dibatalkan.

### e. Koreksi catatan belanja Mesir
- Di tab **Riwayat**, tekan ikon pensil. Stok dan status pesanan dihitung ulang otomatis.
- Koreksi ditolak kalau kitabnya sudah ikut jadwal bagasi atau sudah dicentang gudang.

### f. Pembatalan
- Di menu **Pembatalan**, setujui atau tolak pengajuan.
- Saat menyetujui, isi jumlah refund, metode, dan bukti transfer kalau ada.

### g. Laporan
- Pilih periode: Bulan ini, Bulan lalu, 3 bulan, Tahun ini, atau tanggal bebas.
- Lima tab: Penjualan, Margin per kitab, Hutang pembeli, Nilai stok, dan Performa reseller.
- Tekan **Unduh Excel (CSV)** untuk menyimpan tab yang sedang dibuka.

### h. Biaya
- Catat pengeluaran operasional dan lain-lain per bulan. Angkanya mengurangi untung bersih.
- **Ongkos bagasi tidak dicatat di sini.** Isi di Jadwal Bagasi.

### i. Pengguna
- **Tambah pengguna:** isi nama, email, dan peran. Sistem membuat **password sementara** yang hanya tampil **sekali**. Sampaikan lewat jalur pribadi.
- **Reset password:** buat password sementara baru.
- **Nonaktifkan:** staf langsung keluar dari sistem. Ini cara yang dianjurkan kalau ada yang berhenti.
- **Hapus permanen:** harus mengetik ulang password kamu. Riwayat pekerjaannya tetap tersimpan.

### j. Log Aktivitas
- Mencatat siapa mengubah apa dan kapan, lengkap dengan nilai sebelum dan sesudahnya. Tidak bisa diubah dari aplikasi.

### k. Toko & Invoice
- Isi nomor WhatsApp toko, rekening pembayaran, dan catatan di bawah invoice. Data ini tampil di setiap invoice.
- **Cadangan data:** ketik ulang password, lalu unduh file `.sql`. Simpan di luar server, misalnya Google Drive, dan sebaiknya seminggu sekali.

---

## 7. Istilah yang sering muncul

| Istilah | Artinya |
|---|---|
| **HPP / modal** | Harga beli di Mesir ditambah ongkos bagasi per kitab. Hanya Owner yang melihat. |
| **Untung bersih** | Penjualan dikurangi modal dan biaya. |
| **Kedatangan** | Barang dari Mesir yang menunggu dicentang gudang. |
| **Restok** | Kebutuhan stok gudang yang tidak berasal dari pesanan. |
| **Kelebihan belanja** | Jumlah yang dibeli lebih dari yang dibutuhkan pesanan. Masuk ke kedatangan. |
| **Stok Ready** | Stok yang sudah ada di gudang Indonesia dan sudah dicentang. |
| **Selesai** | Paket sudah dipacking dan diserahkan ke ekspedisi. |

---

## 8. Kalau ada masalah

- **Tidak bisa masuk:** hubungi Owner dan sebutkan gejalanya. Jangan terus mencoba, dan jangan mengganti password sendiri.
- **Halaman error (500):** tulis apa yang kamu lakukan, lalu kirim screenshot ke Owner.
- **Angka tidak sesuai:** laporkan ke Owner, jangan diubah sendiri.
