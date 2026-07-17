# Panduan Eksekusi K6 Load Test — AlbaSambosa Fase 5A.2

> **Branch:** `feature/5a2-load-security`
> **Scripts:** `docs/testing/k6/`

---

## 1. Install K6

**Windows:**
```
winget install k6
```
Verifikasi:
```
k6 version
```

---

## 2. Pastikan App Berjalan

```
php artisan serve
```
Harusnya di `http://localhost:8000`. Kalau pakai port beda, set env `BASE_URL`:
```
set BASE_URL=http://localhost:8000
```

Verifikasi endpoint utama:
```
curl http://localhost:8000/menu
curl http://localhost:8000/produk/13
curl http://localhost:8000/keranjang
curl http://localhost:8000/checkout
curl http://localhost:8000/cek-status
```
Semua harus return `200`, bukan `500`.

---

## 3. Seed Database (Kalau Belum)

```
php artisan db:seed
```
Pastikan product ID `13-17` ada dan customer `customer@test.com` / `password` bisa login.

---

## 4. Jalankan K6 — Scenario A (Browsing)

```
k6 run docs/testing/k6/scenario-a-browsing.js
```

**Apa yang terjadi:** 50 virtual user mengakses `/menu` dan 1-2 halaman detail produk secara acak selama 3 menit.

**Yang dicek di output:**
- `http_req_duration` — p95 < 500ms untuk `{endpoint:catalog}` dan `{endpoint:product}`
- `http_req_failed` — rate < 1%
- `checks` — semua harus 100% pass (status 200, body mengandung 'produk')

---

## 5. Jalankan K6 — Scenario B (Checkout)

```
k6 run docs/testing/k6/scenario-b-checkout.js
```

**Apa yang terjadi:** 10 VU mengakses halaman produk → checkout → keranjang bersamaan.

**Yang dicek:**
- `http_req_duration` — p95 < 3s `{endpoint:checkout_page}`, p95 < 1s `{endpoint:product_page}`
- `http_req_failed` — rate < 1%
- `checks` — halaman checkout mengandung "Buat Pesanan" atau "Keranjang masih kosong"

> **Catatan:** Skenario ini HANYA load-test page load, bukan submit checkout. Submit via Livewire butuh ekstraksi fingerprint yang fragile. Race condition checkout di-test via Pest (`StockRaceConditionTest.php`).

---

## 6. Jalankan K6 — Scenario C (Mixed 70/30)

```
k6 run docs/testing/k6/scenario-c-mixed.js
```

**Apa yang terjadi:** 50 VU selama 3 menit — 70% baca (katalog, produk, halaman statis), 30% tulis (keranjang, checkout, tracking).

**Yang dicek:**
- `http_req_duration{type:read}` — p95 < 1s
- `http_req_duration{type:write}` — p95 < 3s
- `http_req_failed` — rate < 1%

---

## 7. Catat Hasil ke Laporan

Setelah ketiga skenario selesai, isi Section 1 di `docs/testing/fase-5a2-report.md`:

```
### Scenario A — Browsing
| Metric | Target | Actual | Pass? |
|--------|--------|--------|-------|
| p95 catalog | < 500ms | 342ms | ✅ |
| p95 product | < 500ms | 287ms | ✅ |
| Error rate  | < 1%   | 0.2%  | ✅ |
```

---

## 8. Target vs Realita

> ⚠️ `php artisan serve` pakai PHP built-in server yang **single-threaded**. Hasil K6 di local development TIDAK representatif untuk produksi. Target threshold (< 500ms, < 3s) baru relevan saat jalan di **Nginx + PHP-FPM + Docker** (staging).

Untuk sekarang, yang penting:
- Script jalan tanpa error
- Tidak ada crash / memory exhaustion
- Response time masuk akal untuk dev environment
- Format output K6 bisa dibaca

Eksekusi sebenarnya dengan threshold ketat → tunggu staging.
