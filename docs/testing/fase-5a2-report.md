# Fase 5A.2 — Load & Security Report

> **Tanggal eksekusi:** _(isi setelah tes selesai)_
> **Branch:** `feature/5a2-load-security`
> **Acuan:** PRD v5.15 — U-33, U-34, U-35

---

## 1. K6 Load Testing

### Environment
- **App URL:** _(isi)_
- **K6 version:** _(isi — `k6 version`)_
- **Server:** _(isi: Nginx + PHP-FPM / Laravel Octane / php artisan serve)_
- **Database:** _(isi)_

### Scenario A — Browsing (50 VU, 3 menit)

| Metric | Target | Actual | Pass? |
|--------|--------|--------|-------|
| p95 catalog | < 500ms | | |
| p95 product | < 500ms | | |
| Error rate | < 1% | | |

**Catatan:** _(isi)_

### Scenario B — Checkout Concurrent (10 VU, 1 iterasi)

| Metric | Target | Actual | Pass? |
|--------|--------|--------|-------|
| p95 checkout page | < 3s | | |
| p95 product page | < 1s | | |
| Error rate | < 1% | | |

**Catatan:** _(isi)_

### Scenario C — Mixed 70/30 (50 VU, 3 menit)

| Metric | Target | Actual | Pass? |
|--------|--------|--------|-------|
| p95 read | < 1s | | |
| p95 write | < 3s | | |
| Error rate | < 1% | | |

**Catatan:** _(isi)_

---

## 2. OWASP ZAP Security Scan

### Environment
- **ZAP version:** _(isi — `docker run ghcr.io/zaproxy/zaproxy:stable zap-baseline.py --version`)_
- **Scan date:** _(isi)_

### Results

| Category | Alerts | Pass? |
|----------|--------|-------|
| SQL Injection | | |
| XSS (Reflected) | | |
| XSS (Stored) | | |
| CSRF | | |
| Path Traversal | | |
| Other High/Medium | | |

**Catatan:** _(isi — false positive? perlu difix di Fase 5B?)_

---

## 3. Cross-Browser Compatibility

> **Tanggal eksekusi:** 13 Juli 2026
> **Metode:** Playwright MCP (Chromium)
> **Panduan:** `docs/testing/playwright-smoke-guide.md`
> Checklist lengkap: `docs/testing/cross-browser-checklist.md`

### Hasil Smoke Test (Chromium)

| # | Halaman | URL | Status | Console Errors | Notes |
|---|---------|-----|--------|---------------|-------|
| 1 | Katalog | `/menu` | ✅ | 0 | Horizontal overflow fixed (navbar hamburger menu ditambahkan 13 Jul). Verified: scrollWidth=360px = clientWidth di 375px viewport. |
| 2 | Detail Produk | `/produk/1` | ✅ | 0 | Layout 2-kolom rapi. "Beli Bersama" section muncul. |
| 3 | Keranjang | `/keranjang` | ✅ | 0 | Empty state "Keranjang masih kosong" muncul. |
| 4 | Checkout | `/checkout` | ✅ | 0 | Livewire hydrate OK. Guest form + Pickup/Delivery toggle. |
| 5 | Cek Status | `/cek-status` | ✅ | 0 | Form lookup: order number + nomor telepon. |
| 6 | Kebijakan Privasi | `/kebijakan-privasi` | ✅ | 0 | Konten statis, tipografi rapi. |
| 7 | 404 Page | `/halaman-tidak-ada` | ✅ | 1 (expected) | HTTP 404 returned. Console error = halaman not found (expected). |

### Browser Coverage

| Target | Status | Metode |
|--------|--------|--------|
| Chrome Desktop | ✅ Smoke tested | Playwright MCP |
| Chrome Mobile (375px) | ✅ Smoke tested | Playwright MCP |

> **Scope dipersempit 13 Jul 2026:** Firefox, Edge, Safari out-of-scope V1.
> Alasan: Edge = Chromium engine (hasil identik), Firefox & Safari perlu device fisik / BrowserStack (biaya).
> PRD U-35 di-update sesuai keputusan ini.

### Temuan Cross-Browser → Fase 5B

| # | Severity | Deskripsi | Halaman | Status |
|---|----------|-----------|---------|--------|
| CB-1 | 🟡 Major | ~~Horizontal overflow di mobile 375px — navbar auth links overflow~~ | `/menu` | ✅ Fixed (navbar hamburger menu, 13 Jul) |
| CB-2 | 🔵 Minor | ~~Tidak ada hamburger menu di mobile~~ | Semua halaman | ✅ Fixed (Alpine.js mobile toggle, 13 Jul) |

### Verdict Final

- **Chrome Desktop + Mobile:** ✅ **PASS** — 0 console errors, 0 horizontal overflow, navbar mobile fixed.
- Tidak ada temuan cross-browser tersisa untuk Fase 5B.

---

## 4. Manual Test Checklist

> Checklist lengkap: `docs/testing/manual-test-checklist.md`

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

## 5. Acceptance Criteria (PRD)

| ID | Kriteria | Status |
|----|----------|--------|
| U-33 | OWASP ZAP — tidak ada SQLi, XSS, CSRF | ☐ |
| U-34 | K6 Load Test — response < 3s, error < 1% | ☐ |
| U-35 | Cross-Browser — Chrome, Firefox, Safari, Edge | ☐ |

---

## 6. Bug Ditemukan (→ Fase 5B)

| # | Severity | Deskripsi | Halaman |
|---|----------|-----------|---------|
| 1 | | | |
| 2 | | | |

> **Severity:** 🔴 Critical · 🟡 Major · 🔵 Minor · ⚪ Cosmetic

---

> **Setelah report lengkap:** update `TASK.md` dan `TAHAPAN-DEVELOPMENT.md`, commit, merge ke `develop`.
