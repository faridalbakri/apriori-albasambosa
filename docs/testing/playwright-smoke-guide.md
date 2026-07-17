# Playwright Smoke Guide — Cross-Browser Testing

> **Fase 5A.2** · U-35 · 13 Juli 2026

---

## Prasyarat

1. **Dev server jalan:** `php artisan serve` di terminal (default `http://localhost:8000`)
2. **Playwright MCP** tersedia (sudah terpasang di project — cek `.playwright-mcp/`)
3. Browser: Chromium (default), Firefox, WebKit

---

## Target Halaman & URL

| # | Halaman | URL |
|---|---------|-----|
| 1 | Katalog | `http://localhost:8000/menu` |
| 2 | Detail Produk | `http://localhost:8000/produk/{slug}` (pakai slug produk pertama) |
| 3 | Keranjang | `http://localhost:8000/keranjang` |
| 4 | Checkout | `http://localhost:8000/checkout` |
| 5 | Cek Status | `http://localhost:8000/cek-status` |
| 6 | Kebijakan Privasi | `http://localhost:8000/kebijakan-privasi` |
| 7 | 404 Page | `http://localhost:8000/halaman-tidak-ada` |

---

## Viewport Breakpoints

| Label | Width | Height |
|-------|-------|--------|
| Mobile | 375 | 812 |
| Tablet | 768 | 1024 |
| Desktop | 1024 | 768 |
| Wide | 1440 | 900 |

---

## Per-Halaman Checklist (via Playwright MCP)

### Untuk setiap halaman × browser:

```
1. browser_navigate(url)
2. browser_wait_for(time=2)  — tunggu render
3. browser_console_messages(level="error")  — catat jika ada error
4. Untuk setiap viewport (375→768→1024→1440):
   a. browser_resize(width, height)
   b. browser_take_screenshot(filename="page-browser-width.png")
   c. browser_snapshot()  — cek accessibility tree
   d. Cek: tidak ada "horizontal-scroll" indikator
5. browser_take_screenshot(filename="page-browser-full.png", fullPage=true)
```

### Perintah MCP yang dipakai:

| Tool | Kegunaan |
|------|----------|
| `browser_navigate` | Buka halaman |
| `browser_resize` | Ubah viewport |
| `browser_snapshot` | Accessibility snapshot (cek layout) |
| `browser_take_screenshot` | Screenshot visual |
| `browser_console_messages` | Tangkap console error |
| `browser_wait_for` | Tunggu render/network idle |

---

## Browser Switch

Playwright MCP default-nya Chromium. Untuk ganti browser:

```
browser_run_code_unsafe(code: "await page.context().browser().browserType().name()")
```

> **Batasan MCP:** MCP tool tidak expose switch browser langsung. Alternatif: jalankan sesi terpisah dengan config Playwright berbeda, atau catat Firefox/WebKit sebagai "belum terverifikasi".

---

## Hasil

Setelah smoke test selesai:
1. Screenshot tersimpan di `.playwright-mcp/` (format: `page-{timestamp}.yml`)
2. Console log di `.playwright-mcp/console-*.log`
3. Isi hasil ke `fase-5a2-report.md` Section 3

---

## Catatan

- **Livewire pages** (`/keranjang`, `/checkout`) mungkin perlu `browser_wait_for(time=3)` lebih lama untuk hydration
- **Detail produk** butuh `slug` valid — cek dulu `Product::first()->slug` via tinker
- **Keranjang kosong** akan tampil empty state — bukan error
- **Safari & Edge** tidak tersedia di Playwright MCP — dicatat sebagai batasan di report
