# Alur Kerja ERP Maktabah Asyrun Syumus

## Panduan per Peran

Sep 30, 2026 · disusun oleh @Maktabah Asyrun Syumus

## Cara memakai panduan ini

Panduan ini menjawab satu pertanyaan: **apa yang harus saya kerjakan di sistem, dan kapan.** Cari bagian yang sesuai peran Anda, kerjakan urut dari atas.

Empat peran di sistem: **Admin/Sales**, **Tim Operasional Mesir**, **Tim Gudang Indonesia**, dan **Owner**. Setiap orang hanya melihat menu yang menjadi tugasnya — kalau sebuah menu tidak muncul di layar Anda, berarti memang bukan bagian Anda.

Yang tidak dibahas di sini: cara kerja sistem di dalamnya, nama tabel, dan alasan teknis di balik tiap langkah. Itu ada di dokumen `04_BUSINESS_WORKFLOWS.md` di dalam proyek, untuk keperluan pengembangan.

Satu hal sebelum mulai: bagian **Yang perlu diwaspadai sekarang** di bawah memuat beberapa hal yang sedang bermasalah di sistem dan berpengaruh ke pekerjaan harian. Sebaiknya dibaca sekali oleh semua peran.

## Siapa mengerjakan apa

Setiap tahap pesanan dipegang satu peran. Tidak ada tahap yang bisa dikerjakan dua orang sekaligus, dan tidak ada tahap yang bisa dilewati.

| Peran | Tugas utama | Menu yang terlihat |
| --- | --- | --- |
| **Admin/Sales** | Melayani pembeli, membuat pesanan, mengurus invoice dan resi | 5 menu: Produk, Customer, Gudang, Order & Invoice, Packing, Pembatalan |
| **Tim Operasional Mesir** | Membeli kitab di maktabah dan mencatat belanjanya | 2 menu: Maktabah, Transaksi Pembelian Mesir |
| **Tim Gudang Indonesia** | Menerima barang dari Mesir, packing, serahkan ke ekspedisi | 2 menu: Stok Ready, Packing & Pengiriman |
| **Owner** | Semua di atas, ditambah jadwal bagasi, harga, laporan, biaya, pengaturan, dan pengelolaan akun staf | 15 menu — seluruh sistem |

Tiga hal yang **hanya bisa dikerjakan Owner**, dan tidak bisa didelegasikan lewat sistem: menentukan harga, membuat jadwal bagasi, dan menghapus data secara permanen.

Satu hal yang **tidak bisa dikerjakan Owner**: menandai barang sudah diterima dan menyelesaikan packing. Itu hanya Tim Gudang Indonesia, karena merekalah yang memegang barang fisiknya.

## Perjalanan satu pesanan

&#91;embedded content: perjalanan satu pesanan · 5 langkah, 4 peran, 1 jalan pintas\]

Pesanan bergerak hanya kalau pemegang tahap itu bertindak. Kalau menumpuk di satu tahap, di situ hambatannya — bukan di sistem.

Jalan pintas bergaris putus-putus itu berlaku kalau **seluruh** kitab dalam pesanan sudah ada di gudang Indonesia: pesanan melompat langsung ke Tim Gudang, tanpa melewati Mesir dan tanpa jadwal bagasi. Kalau **sebagian saja** yang ada di gudang, pesanan tetap lewat jalur panjang.

Unggah resi oleh Admin/Sales bisa dilakukan kapan saja sebelum packing, jadi tidak digambar sebagai satu tahap.

## Admin/Sales — langkah harian

Anda pintu masuk semua pesanan. Yang Anda isi di sini menentukan seluruh perjalanan pesanan berikutnya, jadi teliti di dua hal: **pilih pembeli yang benar** dan **pilih status pembayaran yang benar**.

### Menerima pesanan baru

1. Pastikan pembelinya sudah ada di **Master Customer**. Kalau belum, tambahkan dulu — termasuk **tipe pembeli**: Reguler atau Reseller. Tipe ini menentukan harga yang dipakai.
2. Buka **Order & Invoice** → tombol **Order Baru**.
3. Pilih pembeli. Harga tiap kitab akan muncul otomatis sesuai tipe pembelinya.
4. Tambahkan kitab satu per satu beserta jumlahnya. Isi ongkir dan diskon kalau ada.
5. Untuk pesanan **WhatsApp**, pilih status pembayaran:

| Pilihan | Artinya | Yang terjadi |
| --- | --- | --- |
| **Belum Bayar** | Tunggu pembayaran dulu | Pesanan ditahan, belum masuk daftar belanja Tim Mesir |
| **Belum Bayar (Dibelikan Dulu)** | Dipercaya, dibelikan lebih dulu | Langsung masuk daftar belanja Tim Mesir |
| **Lunas** | Sudah dibayar | Langsung masuk daftar belanja Tim Mesir |

6. Untuk pesanan **Shopee** tidak ada pilihan ini — Shopee otomatis dianggap sudah lunas, dan tidak dibuatkan invoice.
7. Periksa **Total Tagihan** di pratinjau, lalu simpan.

Sistem lalu menentukan sendiri, per kitab: kalau stok Indonesia mencukupi, diambil dari gudang; kalau tidak, masuk daftar belanja Tim Mesir. Anda tidak perlu memilih.

### Menagih dan menandai lunas

1. Buka pesanan, lihat kartu **Invoice**. Nomor tagihan dan totalnya sudah jadi.
2. Untuk mencetak atau mengirim ke pembeli, buka halaman invoice lalu tekan **Print**.
3. Setelah uangnya masuk, buka pesanannya, tekan **Tandai Lunas**, isi tanggal pembayaran.

Begitu ditandai lunas, pesanan yang tadi ditahan langsung masuk daftar belanja Tim Mesir. **Jangan tandai lunas sebelum uangnya benar-benar masuk** — setelahnya tidak bisa dibatalkan, dan Tim Mesir akan mulai belanja.

### Mengunggah resi

1. Buat resi seperti biasa di luar sistem (Everpro untuk WhatsApp, atau unduh dari Shopee).
2. Buka pesanannya → kartu **Resi** → unggah berkasnya.

Setelah itu Tim Gudang bisa membuka dan mencetak resi itu langsung dari sistem. **Tidak perlu lagi mengirim resi lewat WhatsApp.**

### Mengajukan pembatalan

Buka pesanannya → kartu **Pembatalan** → isi alasannya. Lihat bagian Pembatalan & Refund di bawah untuk lanjutannya.

### Yang tidak bisa Anda lakukan

Menghapus pesanan, melihat harga beli dan HPP, membuat jadwal bagasi, membuka Laporan, dan mengubah harga jual di Master Produk. Semua itu Owner. Anda **bisa** mengubah keterangan kitab selain harganya — judul, penulis, penerbit, berat, kategori.

## Tim Operasional Mesir — langkah harian

Tugas Anda satu: **belanja kitab sesuai daftar, lalu catat apa yang benar-benar Anda beli.** Anda tidak melihat harga jual, tidak melihat data pembeli, dan tidak melihat nilai tagihan — memang tidak perlu.

### Sebelum berangkat ke maktabah

1. Buka **Transaksi Pembelian Mesir**. Daftar belanja sudah dikelompokkan **per maktabah**, jadi satu maktabah bisa dikerjakan sekali jalan.
2. Tiap baris menampilkan **foto kitab**, judulnya, dan jumlah yang dibutuhkan. Pakai fotonya untuk mencocokkan kitab fisik — jangan hanya mengandalkan judul.
3. Kalau ada keterangan **"Toko cadangan"** di bawah judul, itu maktabah lain yang menjual kitab sama. Berguna kalau toko utama kehabisan.
4. Tekan **Export PDF** untuk mencetak daftarnya, supaya bisa dibawa tanpa HP.

### Setelah belanja

Catat **tiap kitab satu per satu**, jangan digabung. Tekan tombol **Beli** pada barisnya, lalu isi:

| Kolom | Isi apa |
| --- | --- |
| **Maktabah** | Toko tempat Anda **benar-benar membeli**. Kalau beli di toko lain karena yang utama kehabisan, ganti di sini — jangan biarkan nama lama |
| **Jumlah dibeli** | Yang benar-benar Anda dapat, bukan yang dibutuhkan |
| **Harga EGP** | Harga satuan sesungguhnya hari itu |
| **Kurs** | Kurs EGP ke Rupiah saat Anda bertransaksi |
| **Tanggal beli** | Tanggal transaksinya |

### Yang terjadi setelah Anda simpan

- **Kalau jumlahnya kurang** dari kebutuhan — selisihnya tetap muncul di daftar, dan Owner otomatis diberi tahu. Tidak perlu lapor lewat WhatsApp. Belanja lagi nanti, catat lagi di baris yang sama.
- **Kalau jumlahnya lebih** — kelebihannya otomatis masuk stok Indonesia.
- **Kalau harga EGP berbeda** dari yang tersimpan di sistem, harga di Master Produk **langsung diperbarui otomatis**. Ringkasan setelah simpan akan memberitahu perubahannya. Jadi pastikan angkanya benar sebelum menekan simpan.
- Baris akan **hilang sendiri** dari daftar begitu kebutuhannya terpenuhi penuh.

Kalau satu kitab dibutuhkan beberapa pesanan tapi Anda hanya dapat sebagian, sistem memberikannya ke **pesanan yang paling dulu masuk**. Anda tidak perlu mengatur itu.

### Yang tidak bisa Anda lakukan

**Anda tidak bisa memperbaiki catatan yang sudah Anda simpan.** Salah ketik kurs atau jumlah harus diperbaiki Owner. Karena itu periksa dua kali sebelum menekan simpan.

Anda juga hanya bisa **melihat** daftar Maktabah, tidak bisa menambah atau mengubahnya.

## Tim Gudang Indonesia — langkah harian

Anda pemegang dua tombol yang **tidak bisa ditekan siapa pun selain Anda**, termasuk Owner: menandai barang sudah tiba, dan menandai packing selesai. Selama Anda belum menekannya, pesanan tidak bergerak.

Buka **Packing & Pengiriman**. Ada dua tab, dan urutannya memang dari kiri ke kanan.

### Tab 1 — Barang Diterima dari Mesir

Isinya kitab yang sedang dalam perjalanan dari Mesir.

1. Bongkar kirimannya, cocokkan fisik kitab dengan **foto** di layar.
2. Tiap baris menampilkan judul, jumlah, nama maktabah, dan **tanggal jadwal bagasi**. Tanggal itu penting: kalau kitab yang sama datang dari dua rombongan bagasi berbeda, barisnya juga dua. **Tandai hanya yang barangnya benar-benar sudah di tangan Anda.**
3. Tekan **Konfirmasi Diterima** pada baris yang barangnya sudah lengkap.

Setelah dikonfirmasi, pesanan-pesanan yang memakai kitab itu pindah ke tab kedua.

### Tab 2 — Pesanan per Customer

Isinya pesanan yang barangnya sudah di gudang dan siap dikirim.

1. Pesanan sudah dikelompokkan **per pembeli**, jadi kalau satu orang punya dua pesanan, bisa dikerjakan bersamaan.
2. Tekan **Lihat / cetak resi** untuk membuka resinya, lalu cetak. Resinya sudah ada di sistem — tidak perlu lagi minta dikirim lewat WhatsApp.
3. Packing sambil **mencentang daftar kitabnya satu per satu**. Centang itu untuk memastikan tidak ada yang tertinggal atau kelebihan.
4. Setelah semua tercentang dan paket tersegel, tekan **Selesai**.

Pesanan itu lalu berstatus **Selesai** dan hilang dari daftar Anda. Tekan Selesai hanya kalau paketnya benar-benar sudah siap diserahkan ke ekspedisi.

### Melihat stok

Menu **Stok Ready** menampilkan semua kitab yang ada stoknya di gudang. Bisa dicari, kolomnya bisa dipilih, dan bisa diunduh sebagai PDF untuk dibawa saat menghitung fisik.

**Anda tidak bisa mengubah angka stok di sini** — tidak ada tombol tambah, tidak ada edit. Kalau angka di sistem tidak cocok dengan hitungan fisik Anda, **laporkan ke Owner**, jangan dibiarkan. Lihat catatan di bagian berikutnya soal ini.

### Yang tidak bisa Anda lakukan

Membuat atau mengubah pesanan, melihat harga beli dari Mesir, melihat data hutang, dan mengubah stok.

## Owner — langkah harian dan wewenang khusus

Anda bisa mengerjakan semua yang dikerjakan Admin/Sales dan Tim Mesir. Bagian ini hanya memuat yang **tidak bisa dikerjakan siapa pun selain Anda**.

### Setiap hari — lihat dashboard

Dashboard Anda memuat empat kartu:

- **Alur Pesanan** — empat tahap dengan jumlah dan daftar pesanan di masing-masing: Diproses → Dikirim dari Mesir → Sampai Gudang → Sudah Terkirim. Kalau ada tumpukan di satu tahap, di situ hambatannya.
- **Alur Keuangan** — penjualan, HPP, biaya operasional, dan biaya lain-lain bulan ini.
- **Jadwal Bagasi Terdekat** — tanggal, kontak, dan berapa pesanan sudah terpasang.
- **Notifikasi** — dua hal yang dikabari sistem: kekurangan belanja dari Mesir, dan pengajuan pembatalan.

Satu catatan soal notifikasi: notifikasi dikirim **per peran, bukan per orang**. Kalau satu orang menandainya sudah dibaca, hilang juga untuk rekan yang perannya sama.

### Jadwal bagasi — hanya Anda

Ini yang paling sering perlu tindakan Anda, karena pesanan **berhenti** di sini kalau tidak ada jadwal.

1. Buka **Jadwal Bagasi**. Kartu **Menunggu Jadwal Bagasi (n)** memberi tahu berapa pesanan sedang menunggu.
2. Buat jadwal baru: tanggal keberangkatan, kontak jasa bagasi, dan kuota kilogram. **Kuota dikosongkan berarti tanpa batas.**
3. Begitu disimpan, pesanan yang menunggu **otomatis dipasangkan**, dari yang paling lama menunggu. Anda tidak perlu memilih satu-satu.
4. Kalau dapat tambahan kuota di tanggal yang sama, tekan **Edit** dan naikkan angkanya — pesanan yang masih menunggu akan dicek ulang otomatis.
5. Bar pemakaian menunjukkan `x kg / y kg` dan sisanya, supaya terlihat kapan jadwal hampir penuh.

Menghapus jadwal yang sudah dipakai pesanan akan menawarkan **pemindahan ke jadwal lain** lebih dulu. Pakai itu, jangan paksa hapus.

### Harga dan HPP — hanya Anda

Hanya Anda yang melihat dan mengubah harga beli EGP, HPP, harga jual reguler, dan harga jual reseller. Di form produk ada kalkulator yang menampilkan HPP, ongkos bagasi, total biaya, dan perkiraan keuntungan per kitab.

### Menghapus — hanya Anda, dan pakai password

Empat tindakan meminta Anda **mengetik ulang password** sebelum jalan: hapus pesanan, hapus produk massal, kosongkan stok, dan hapus akun staf. Itu pengaman, bukan gangguan — jangan dibuat mudah.

Sistem juga akan **menolak** menghapus produk, maktabah, atau customer yang datanya masih terpakai di pesanan, dan akan menyebut alasannya. Itu benar, jangan dipaksa.

### Memperbaiki catatan Tim Mesir

Kalau Tim Mesir salah ketik kurs atau jumlah, hanya Anda yang bisa membetulkannya: **Transaksi Pembelian Mesir** → bagian Riwayat → ikon edit pada barisnya. Perbaikan ini otomatis menghitung ulang stok dan status batch.

### Mengelola akun staf

1. **Manajemen User** → **Tambah User**: isi nama, email, pilih peran.
2. Sistem memberi **password sementara** yang tampil **sekali saja** di layar. Catat dan sampaikan ke stafnya lewat jalur pribadi. Password itu tidak disimpan di mana pun dan tidak bisa dilihat lagi — termasuk oleh Anda.
3. Staf yang berhenti: geser saklar **Status** jadi nonaktif. Itu cukup, tidak perlu dihapus. Menghapus permanen hanya kalau memang perlu, dan riwayat pesanannya tetap tersimpan.

### Laporan dan biaya

**Laporan** punya lima tab: Penjualan, Margin, Hutang, Stok, Performa Reseller — semuanya bisa disaring per rentang tanggal.

**Biaya** untuk mencatat pengeluaran operasional dan lain-lain. Angkanya otomatis masuk ke kartu Alur Keuangan di dashboard dan ke tab Margin.

Baca dulu bagian berikutnya sebelum mengambil keputusan dari angka-angka Laporan.

## Pembatalan & refund

Pembatalan melibatkan dua peran dan **tidak bisa diulang**. Baca sampai habis sebelum mengajukan.

### Langkahnya

1. **Admin/Sales atau Owner** membuka pesanannya → kartu **Pembatalan** → isi alasan → ajukan.
2. Pesanan berubah status jadi **Menunggu Approval Pembatalan**, dan notifikasi dikirim.
3. **Admin/Sales atau Owner** membuka menu **Pembatalan** → pilih **Setujui** atau **Tolak**.

Perhatikan: yang menyetujui **boleh orang yang sama** dengan yang mengajukan. Sistem mengizinkannya, dan itu memang sesuai rencana awal. Kalau Anda ingin selalu ada mata kedua, itu kesepakatan kerja, bukan sesuatu yang dipaksakan sistem.

### Kalau ditolak

Pesanan kembali ke status sebelumnya dan berjalan normal lagi.

> **Penting:** satu pesanan hanya boleh **satu kali** pengajuan, selamanya. Kalau sudah pernah ditolak lalu pembeli tetap ingin membatalkan, **sistem tidak mengizinkan pengajuan kedua**. Satu-satunya jalan tersisa adalah Owner menghapus pesanannya. Jadi jangan tolak pengajuan hanya untuk "dipikir dulu".

### Kalau disetujui

1. Isi **jumlah refund** dan **metode refund**.
2. Unggah **bukti transfer** kalau ada (opsional).
3. Kitab yang sudah dibeli **otomatis kembali masuk stok Indonesia** — baik yang diambil dari gudang, maupun yang sudah dibelanjakan Tim Mesir.
4. Pesanan berstatus **Dibatalkan**.

Satu hal yang tidak ikut dibatalkan: **uang yang sudah dibelanjakan Tim Mesir**. Belanja di maktabah dibayar langsung saat transaksi, jadi uangnya sudah keluar. Yang kembali adalah kitabnya, dalam bentuk stok.

## Yang perlu diwaspadai sekarang

Lima hal berikut adalah temuan audit sistem yang dikerjakan September 2026. **Semuanya belum diperbaiki**, dan semuanya berpengaruh ke pekerjaan harian. Bukan untuk ditakuti — untuk diketahui, supaya tidak salah mengambil keputusan.

### 1. Angka stok bisa lebih besar daripada barang fisik

Kalau satu pesanan berisi campuran — sebagian kitab dari gudang, sebagian dibeli dari Mesir — maka kitab yang diambil dari gudang **tidak dikurangi dari angka stok**, padahal bukunya tetap dikirim. Selisihnya menumpuk setiap kali ada pesanan campuran.

**Untuk Tim Gudang:** kalau hitungan fisik Anda lebih sedikit daripada angka di sistem, kemungkinan besar bukan Anda yang salah hitung. Laporkan angkanya, jangan disesuaikan sendiri.

**Untuk Admin/Sales:** jangan menjanjikan ketersediaan hanya berdasarkan angka Stok Ready.

### 2. Stok yang cukup sebagian tidak dipecah

Pesan 10 buah sementara stok 7 → kesepuluhnya dibeli dari Mesir, dan 7 yang ada di gudang dibiarkan. Seharusnya 7 diambil dari gudang dan 3 dibeli. Jadi belanja ke Mesir bisa lebih banyak daripada yang sebenarnya perlu.

### 3. Laporan penjualan belum dikurangi diskon

Angka **Penjualan** di dashboard dan **Total Jual** di Laporan dihitung dari harga dikali jumlah, **tanpa mengurangi diskon**. Padahal tagihan yang dibayar pembeli sudah dikurangi diskon.

Artinya: kalau sebulan Anda memberi diskon total Rp 3 juta, laporan menyebut penjualan Anda Rp 3 juta lebih besar daripada uang yang benar-benar masuk. Laba bersih ikut terlalu tinggi.

Lebih dari itu, **tiga layar memberi tiga angka berbeda** untuk bulan yang sama, karena masing-masing menyaring pesanan dengan cara berlainan. Untuk sekarang, angka yang paling bisa dipercaya adalah **jumlah invoice yang benar-benar terbayar**, bukan angka di layar ringkasan.

### 4. Ongkos bagasi tidak masuk perhitungan laba

Ongkos mengangkut kitab dari Mesir ke Indonesia tidak ikut dihitung di Laporan Margin. Kalau belum Anda catat manual di menu **Biaya**, laba yang terlihat lebih besar daripada kenyataan.

### 5. Kitab tanpa berat dihitung nol kilogram

Kitab yang kolom beratnya kosong tidak menambah pemakaian kuota bagasi sama sekali. Bagasi bisa kelebihan muatan tanpa peringatan dari sistem.

**Untuk Admin/Sales dan Owner:** pastikan kolom berat terisi saat menambah kitab baru.

### Dua hal kecil yang bisa membingungkan

- Di layar detail pesanan, baris riwayat menyebut status **"Sudah Dibeli"** padahal status yang sebenarnya tersimpan **"Barang Diterima"**. Yang benar adalah lencana status di atas, bukan tulisan di riwayat.
- Dua baris riwayat — "Barang diterima di gudang" dan "Packing selesai & dikirim" — **selalu bertanggal sama**, karena sistem tidak menyimpan riwayat perubahan status yang sesungguhnya.

## Kalau sistem tidak bisa dibuka

Gejala yang paling sering: **halaman login terbuka, tapi login gagal terus** — atau seluruh halaman menampilkan tulisan bahasa Inggris "Application error".

Dalam banyak kasus **aplikasinya tidak rusak.** Database sistem berada di paket gratis, dan **tertidur sendiri** kalau beberapa hari tidak dipakai. Selama tertidur, tidak ada yang bisa login.

Sudah terjadi dua kali: **15 Agustus** dan **23 September 2026**.

### Yang harus dilakukan

Hanya Owner yang bisa, dan hanya butuh satu tombol:

1. Buka dashboard Supabase, masuk dengan akun Owner.
2. Pilih proyeknya. Akan muncul keterangan bahwa proyek di-pause.
3. Tekan **Restore project**, lalu tunggu **2–5 menit**.
4. Coba login lagi ke sistem.

### Untuk staf

Kalau tidak bisa login, **kabari Owner** dan sebutkan gejalanya. Jangan ulang-ulang mencoba dan jangan ganti password — kemungkinan besar bukan akun Anda masalahnya.

### Satu gejala lain yang mirip

Kalau akun Anda **dinonaktifkan** Owner, yang Anda lihat bukan pesan "akun nonaktif", melainkan galat peramban soal **terlalu banyak pengalihan**, dan halaman login tidak bisa dibuka sama sekali. Itu bukan kerusakan sistem — akses Anda memang sudah ditutup. Hubungi Owner.

### Pencegahan

Selama database masih di paket gratis, ini akan terulang. Menaikkan ke paket berbayar menghentikannya.
