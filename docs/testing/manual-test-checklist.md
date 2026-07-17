# Manual Browser Test Checklist — AlbaSambosa V1

> **Tanggal:** 12 Juli 2026
> **Browser:** Chrome / Firefox
> **URL:** `http://albasambosa.test` (atau `http://localhost:8000`)
>
> **Cara pakai:** Centang ☑ jika sesuai ekspektasi. Tulis bug di [Catatan Bug](#catatan-bug).

---

## 1. Katalog Produk (`/menu`)

| # | Langkah | Ekspektasi | OK |
|---|---------|------------|----|
| 1.1 | Buka `/menu` | Halaman termuat, hero "Frozen Food UMKM" terlihat | ☐ |
| 1.2 | Scroll ke bawah | Produk tampil dalam grid, foto + nama + harga terlihat | ☐ |
| 1.3 | Klik tab kategori (misal "Sambal") | Hanya produk kategori itu yang muncul | ☐ |
| 1.4 | Klik "Semua" | Semua produk muncul kembali | ☐ |
| 1.5 | Ketik di search bar, tekan Enter | Hanya produk yang mengandung kata kunci muncul | ☐ |
| 1.6 | Search kata yang tidak ada | Muncul pesan "Belum ada produk tersedia" | ☐ |
| 1.7 | Ada > 12 produk, scroll ke bawah | Pagination muncul, klik halaman 2 berfungsi | ☐ |
| 1.8 | Buka browser console (F12) | **Tidak ada error merah** | ☐ |

---

## 2. Detail Produk (`/produk/{slug}`)

| # | Langkah | Ekspektasi | OK |
|---|---------|------------|----|
| 2.1 | Klik salah satu produk | Halaman detail termuat: foto placeholder, nama, kategori, deskripsi | ☐ |
| 2.2 | Cek harga | Harga muncul format Rp (contoh: Rp 25.000) | ☐ |
| 2.3 | Cek stok | "Stok: X" muncul dengan angka yang benar | ☐ |
| 2.4 | Produk dengan `total_sold > 50` | Badge "Best Seller" muncul | ☐ |
| 2.5 | Produk dengan stok > 0 | Tombol "+" dan "Tambahkan ke Keranjang" muncul | ☐ |
| 2.6 | Klik "+" beberapa kali | Angka quantity naik | ☐ |
| 2.7 | Klik "−" | Angka quantity turun (minimal 1) | ☐ |
| 2.8 | Klik "Tambahkan ke Keranjang" | Notifikasi sukses muncul, badge cart update | ☐ |
| 2.9 | Produk dengan stok = 0 | Muncul "Stok Habis", tidak ada tombol add | ☐ |
| 2.10 | Scroll ke bawah | Section "Beli Bersama" muncul (jika ada rekomendasi) | ☐ |
| 2.11 | Klik produk di "Beli Bersama" | Navigasi ke detail produk tersebut | ☐ |
| 2.12 | Klik "Kembali ke Menu" | Kembali ke `/menu` | ☐ |
| 2.13 | Buka `/produk/99999` | Halaman 404 muncul | ☐ |

---

## 3. Keranjang (`/keranjang`)

| # | Langkah | Ekspektasi | OK |
|---|---------|------------|----|
| 3.1 | Buka `/keranjang` tanpa isi | Muncul "Keranjang masih kosong", tombol "Lihat Menu" | ☐ |
| 3.2 | Tambah produk, buka `/keranjang` | Produk muncul dengan: nama, harga satuan, quantity, subtotal | ☐ |
| 3.3 | Klik "−" (decrement) | Quantity berkurang 1, subtotal update | ☐ |
| 3.4 | Klik "+" (increment) | Quantity bertambah 1, subtotal update | ☐ |
| 3.5 | Klik "+" terus melebihi stok | Notifikasi error "Stok hanya tersedia X", quantity tidak berubah | ☐ |
| 3.6 | Klik ikon tong sampah (delete) | Item hilang, notifikasi sukses | ☐ |
| 3.7 | Keranjang kosong setelah hapus semua | Muncul "Keranjang masih kosong" | ☐ |
| 3.8 | Cek total harga | Total = jumlah semua (harga × qty), format Rp benar | ☐ |
| 3.9 | Section "Mungkin Anda Suka" | Produk rekomendasi muncul (jika ada) | ☐ |
| 3.10 | Klik "Lanjut ke Checkout" | Navigasi ke `/checkout` | ☐ |

---

## 4. Checkout — Pickup (Guest)

| # | Langkah | Ekspektasi | OK |
|---|---------|------------|----|
| 4.1 | Keranjang ada isi, buka `/checkout` | Halaman checkout termuat | ☐ |
| 4.2 | Pilih tab "Ambil Sendiri" | Form pickup muncul: tanggal + jam | ☐ |
| 4.3 | Pilih tanggal dan jam | Bisa pilih, tidak error | ☐ |
| 4.4 | Isi nama, nomor telepon (08xxx) | Validasi lolos | ☐ |
| 4.5 | Coba submit tanpa centang UU PDP | Error: checkbox wajib | ☐ |
| 4.6 | Centang UU PDP, klik "Pesan Sekarang" | Order dibuat, redirect ke halaman sukses | ☐ |
| 4.7 | Cek halaman sukses | Order number format `ALBA-YYYYMMDD-XXX` muncul | ☐ |
| 4.8 | Midtrans Snap popup | Popup pembayaran muncul (sandbox) | ☐ |
| 4.9 | Klik "Kembali ke Menu" dari Snap | Tidak error | ☐ |
| 4.10 | Coba checkout lagi — keranjang kosong | Redirect/tidak bisa checkout (keranjang sudah dikosongkan) | ☐ |
| 4.11 | Coba checkout tanpa isi nama | Validasi error muncul | ☐ |
| 4.12 | Coba nomor telepon format salah | Validasi error muncul | ☐ |

---

## 5. Checkout — Delivery (Guest)

| # | Langkah | Ekspektasi | OK |
|---|---------|------------|----|
| 5.1 | Keranjang ada isi, buka `/checkout` | Halaman checkout termuat | ☐ |
| 5.2 | Pilih tab "Kirim" | Form delivery muncul | ☐ |
| 5.3 | Isi nama penerima, telp, alamat lengkap, kode pos | Semua field bisa diisi | ☐ |
| 5.4 | Isi kode pos valid (≥ 4 digit) | Kurir otomatis muncul setelah input (cache 5 menit mungkin delay) | ☐ |
| 5.5 | Pilih kurir dari dropdown | Biaya kirim muncul | ☐ |
| 5.6 | Cek total = subtotal + ongkir | Total benar | ☐ |
| 5.7 | Klik "Pesan Sekarang" | Order dibuat, Snap muncul | ☐ |
| 5.8 | Isi kode pos tidak valid (< 4 digit) | Kurir tidak muncul / tidak fetch API | ☐ |

---

## 6. Cek Status Pesanan — Guest (`/cek-status`)

| # | Langkah | Ekspektasi | OK |
|---|---------|------------|----|
| 6.1 | Buka `/cek-status` | Form: input order number + nomor telepon | ☐ |
| 6.2 | Input order number salah | Error validasi / pesanan tidak ditemukan | ☐ |
| 6.3 | Input nomor telepon salah | Error: nomor telepon tidak cocok | ☐ |
| 6.4 | Input benar | Halaman status muncul: order number, status, timeline | ☐ |
| 6.5 | Cek timeline | Timeline status pesanan muncul | ☐ |
| 6.6 | Status `Pending` | Tombol "Batalkan Pesanan" muncul | ☐ |
| 6.7 | Klik "Batalkan Pesanan" | Status berubah ke Cancel (mungkin perlu refresh) | ☐ |
| 6.8 | Cek 5x dalam 1 menit | Rate limit: setelah 5x, error "terlalu banyak percobaan" | ☐ |

---

## 7. Autentikasi — Register

| # | Langkah | Ekspektasi | OK |
|---|---------|------------|----|
| 7.1 | Buka `/register` | Form: nama, email, password, konfirmasi password | ☐ |
| 7.2 | Isi semua field, klik "Daftar" | Redirect ke `/menu`, user logged in | ☐ |
| 7.3 | Cek navbar | Nama user muncul (bukan "Masuk"/"Daftar") | ☐ |
| 7.4 | Cek email verifikasi | Email terkirim (cek Mailpit / log) | ☐ |
| 7.5 | Email sudah terpakai | Error: email sudah terdaftar | ☐ |
| 7.6 | Password tidak cocok | Error: konfirmasi password tidak sesuai | ☐ |
| 7.7 | Klik tautan verifikasi di email | Redirect ke halaman verified, bisa akses fitur | ☐ |

---

## 8. Autentikasi — Login

| # | Langkah | Ekspektasi | OK |
|---|---------|------------|----|
| 8.1 | Buka `/login` | Form: email, password, "Ingat Saya", "Lupa Password" | ☐ |
| 8.2 | Input email + password benar | Redirect ke `/menu`, logged in | ☐ |
| 8.3 | Input password salah | Error: email atau password salah | ☐ |
| 8.4 | Coba 5x password salah berturut-turut | Rate limit: error throttle setelah 5x | ☐ |
| 8.5 | Klik "Logout" | Redirect ke home, navbar kembali "Masuk"/"Daftar" | ☐ |

---

## 9. Autentikasi — Lupa Password

| # | Langkah | Ekspektasi | OK |
|---|---------|------------|----|
| 9.1 | Klik "Lupa Password" dari halaman login | Form input email | ☐ |
| 9.2 | Input email terdaftar | Notifikasi: link reset terkirim | ☐ |
| 9.3 | Input email tidak terdaftar | Error: email tidak ditemukan | ☐ |
| 9.4 | Klik tautan reset di email | Form: password baru + konfirmasi | ☐ |
| 9.5 | Isi password baru, submit | Redirect ke login, bisa login dengan password baru | ☐ |

---

## 10. Keranjang — Registered User

| # | Langkah | Ekspektasi | OK |
|---|---------|------------|----|
| 10.1 | Login sebagai customer | Keranjang muncul sesuai user_id (jika ada) | ☐ |
| 10.2 | Tambah produk ke keranjang | Cart tersimpan dengan user_id (bukan session_id) | ☐ |
| 10.3 | Buka `/keranjang` | Produk yang ditambahkan muncul | ☐ |
| 10.4 | Logout, login kembali | Item keranjang masih ada (persist) | ☐ |

---

## 11. Checkout — Registered User

| # | Langkah | Ekspektasi | OK |
|---|---------|------------|----|
| 11.1 | Login, tambah ke cart, buka `/checkout` | Form terisi otomatis (nama dari profil) | ☐ |
| 11.2 | Pilih delivery | Bisa pilih dari buku alamat (jika ada) | ☐ |
| 11.3 | Pilih pickup | Bisa pilih waktu | ☐ |
| 11.4 | Klik "Pesan Sekarang" | Order dibuat atas user_id tersebut | ☐ |
| 11.5 | Cek `/cek-status` | Bisa lacak dengan login (riwayat pesanan) | ☐ |

---

## 12. Buku Alamat — Registered User

| # | Langkah | Ekspektasi | OK |
|---|---------|------------|----|
| 12.1 | Buka `/profile` atau `/addresses` | Daftar alamat muncul (jika ada) | ☐ |
| 12.2 | Klik "Tambah Alamat" | Form: label, nama penerima, telp, alamat | ☐ |
| 12.3 | Isi semua, simpan | Alamat baru muncul di daftar | ☐ |
| 12.4 | Klik "Edit" | Bisa ubah alamat | ☐ |
| 12.5 | Klik "Hapus" | Alamat hilang dari daftar | ☐ |
| 12.6 | Set sebagai default | Alamat default ditandai, muncul pertama di checkout | ☐ |

---

## 13. Admin Panel (`/admin`)

| # | Langkah | Ekspektasi | OK |
|---|---------|------------|----|
| 13.1 | Buka `/admin` tanpa login | Redirect ke `/admin/login` | ☐ |
| 13.2 | Login sebagai admin | Masuk ke dashboard Filament | ☐ |
| 13.3 | Dashboard | Widget muncul: ringkasan penjualan, rekonsiliasi, pending orders | ☐ |
| 13.4 | Sidebar: Produk → Products | Daftar produk muncul | ☐ |
| 13.5 | Klik "New Product" | Form tambah produk: nama, kategori, harga, stok, deskripsi, gambar | ☐ |
| 13.6 | Isi semua, simpan | Produk baru muncul di `/menu` | ☐ |
| 13.7 | Klik produk → Edit | Bisa ubah nama, harga, stok | ☐ |
| 13.8 | Klik produk → Delete | Produk hilang dari daftar | ☐ |
| 13.9 | Sidebar: Pesanan → Orders | Daftar pesanan muncul: order number, status, total | ☐ |
| 13.10 | Klik salah satu pesanan | Detail pesanan: info, items, total, riwayat status, pengiriman | ☐ |
| 13.11 | Klik action button (transisi) | Status pesanan berubah, audit trail tercatat | ☐ |
| 13.12 | Klik "Sinkronisasi Midtrans" | Sinkron status dari Midtrans | ☐ |
| 13.13 | Sidebar: Produk → Categories | Daftar kategori, bisa drag & drop urutan | ☐ |
| 13.14 | Sidebar: Produk → Admin Picks | Daftar pilihan admin (maks 5), bisa atur urutan | ☐ |
| 13.15 | Sidebar: Produk → Apriori Rules | Daftar rules, filter confidence/lift, dashboard Chart.js | ☐ |
| 13.16 | Sidebar: Sistem → Notification Logs | Daftar log notifikasi (read-only) | ☐ |
| 13.17 | Sidebar: Sistem → Anonymization Logs | Daftar log anonimisasi (read-only) | ☐ |
| 13.18 | Sidebar: Sistem → Failed Jobs | Daftar failed jobs, tombol Retry | ☐ |
| 13.19 | Widget "Pending > 30 menit" | Pesanan pending lama muncul | ☐ |
| 13.20 | Widget "Rekonsiliasi" | Selisih > Rp 10.000 muncul warning | ☐ |
| 13.21 | Klik user menu → Logout | Kembali ke `/admin/login` | ☐ |

---

## 14. Halaman Statis

| # | Langkah | Ekspektasi | OK |
|---|---------|------------|----|
| 14.1 | Buka `/kebijakan-cookie` | Halaman kebijakan cookie termuat | ☐ |
| 14.2 | Buka `/kebijakan-privasi` | Halaman kebijakan privasi termuat | ☐ |
| 14.3 | Buka `/syarat-ketentuan` | Halaman syarat & ketentuan termuat | ☐ |
| 14.4 | Kunjungan pertama (incognito) | Cookie consent banner muncul di bawah | ☐ |
| 14.5 | Klik "Setuju" | Banner hilang, cookie consent diset | ☐ |
| 14.6 | Klik "Tolak" | Banner hilang, hanya cookie esensial diset | ☐ |
| 14.7 | Refresh halaman setelah Setuju/Tolak | Banner tidak muncul lagi | ☐ |

---

## 15. Responsive & Mobile

> **Ubah viewport browser: 375px (iPhone), 768px (tablet)**

| # | Langkah | Ekspektasi | OK |
|---|---------|------------|----|
| 15.1 | `/menu` di 375px | Produk grid 1-2 kolom, tidak ada horizontal scroll | ☐ |
| 15.2 | `/produk/{x}` di 375px | Detail produk stack vertikal, tombol full width | ☐ |
| 15.3 | `/keranjang` di 375px | Tabel responsive, total + checkout button terlihat | ☐ |
| 15.4 | `/checkout` di 375px | Form tidak overflow, semua field accessible | ☐ |
| 15.5 | Semua halaman di 375px | Navbar tidak overlap konten | ☐ |
| 15.6 | Semua halaman di 375px | Tidak ada horizontal scroll | ☐ |
| 15.7 | `/admin` di 768px | Sidebar collapse/expand, tabel tidak overflow | ☐ |

---

## 16. Error State & Edge Cases

| # | Langkah | Ekspektasi | OK |
|---|---------|------------|----|
| 16.1 | Stok produk = 5, 2 user guest checkout masing-masing qty 3 (urutan) | User pertama berhasil, user kedua error "stok tidak mencukupi" | ☐ |
| 16.2 | Midtrans popup ditutup tanpa bayar | Tidak error, status tetap `Pending` | ☐ |
| 16.3 | Buka halaman sukses checkout tanpa session checkout | Redirect atau halaman tidak ditemukan | ☐ |
| 16.4 | Browser back setelah checkout | Tidak duplikat order | ☐ |
| 16.5 | URL random `/asdf` | Halaman 404 muncul | ☐ |
| 16.6 | Buka `/admin` sebagai customer login | Redirect atau forbidden | ☐ |
| 16.7 | Semua halaman — cek footer | Footer muncul, link berfungsi | ☐ |

---

## Catatan Bug

| # | Halaman | Langkah | Yang Terjadi | Yang Diharapkan | Severity |
|---|---------|---------|-------------|-----------------|----------|
| 1 | | | | | |
| 2 | | | | | |
| 3 | | | | | |

> **Severity:** 🔴 Critical (tidak bisa lanjut) · 🟡 Major (fitur tidak bekerja) · 🔵 Minor (tampilan/copy) · ⚪ Cosmetic (saran)

---

## Ringkasan

| Section | Total Check | Lolos | Gagal |
|---------|------------|-------|-------|
| 1. Katalog | 8 | | |
| 2. Detail Produk | 13 | | |
| 3. Keranjang | 10 | | |
| 4. Checkout Pickup | 12 | | |
| 5. Checkout Delivery | 8 | | |
| 6. Cek Status | 8 | | |
| 7. Register | 7 | | |
| 8. Login | 5 | | |
| 9. Lupa Password | 5 | | |
| 10. Cart Registered | 4 | | |
| 11. Checkout Registered | 5 | | |
| 12. Buku Alamat | 6 | | |
| 13. Admin Panel | 21 | | |
| 14. Halaman Statis | 7 | | |
| 15. Responsive | 7 | | |
| 16. Error State | 7 | | |
| **TOTAL** | **133** | | |

---

> **Setelah testing selesai:** centang item di `TASK.md` 5A.1, update `TAHAPAN-DEVELOPMENT.md` jika ada perubahan status.
