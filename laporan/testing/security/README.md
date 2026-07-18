# Panduan Eksekusi OWASP ZAP — AlbaSambosa Fase 5A.2

> **Config:** `docs/testing/security/zap-context.context`
> **PRD:** U-33 — SQL Injection, XSS, CSRF

---

## 1. Prasyarat

- **Docker** terinstall (`docker --version`)
- **App berjalan** di `http://localhost:8000`

---

## 2. Jalankan Scan

Dari root project:

```bash
docker run --network host \
  -v $(pwd)/docs/testing/security:/zap/wrk \
  ghcr.io/zaproxy/zaproxy:stable zap-baseline.py \
  -t http://localhost:8000 \
  -c /zap/wrk/zap-context.context \
  -r /zap/wrk/zap-report.html
```

> **Windows (Command Prompt):** ganti `$(pwd)` dengan `%cd%`
> **Windows (Git Bash):** `$(pwd)` jalan seperti di atas

---

## 3. Output

Setelah scan selesai (5-15 menit), akan muncul:

| File | Deskripsi |
|------|-----------|
| `docs/testing/security/zap-report.html` | Laporan HTML lengkap — buka di browser |
| Terminal output | Ringkasan alert ditemukan |

---

## 4. Apa yang di-scan

| Fokus | Endpoint |
|-------|----------|
| SQL Injection | Semua form input, query params di halaman customer |
| XSS (Reflected) | Semua field yang dirender kembali |
| CSRF | Semua POST/PUT/DELETE forms |

**Tidak di-scan (excluded di config):**
- `/admin/*` — Filament built-in CSRF + auth terpisah
- `/build/*`, `/vendor/*`, `/livewire/*` — static assets

---

## 5. Interpretasi Hasil

| Alert Level | Tindakan |
|-------------|----------|
| High / Medium | Difix di **Fase 5B**. Prioritaskan yang bukan false positive. |
| Low / Info | Catat, fix jika sempat. Biasanya informational. |
| False Positive | Laporkan di laporan, abaikan. |

---

## 6. Catat Hasil ke Laporan

Isi Section 2 di `docs/testing/fase-5a2-report.md`:

```
### Results
| Category | Alerts | Pass? |
|----------|--------|-------|
| SQL Injection | 0 | ✅ |
| XSS (Reflected) | 0 | ✅ |
| CSRF | 0 | ✅ |
| Path Traversal | 0 | ✅ |
```
