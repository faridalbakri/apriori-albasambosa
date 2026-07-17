# AlbaSambosa — Sistem Penjualan Online

Sistem _e-commerce_ untuk UMKM **AlbaSambosa** (kuliner _frozen food_), mengintegrasikan _Market Basket Analysis_ (Apriori), _Payment Gateway_ (Midtrans), dan _Delivery Aggregator_ (Biteship).

## Tech Stack

| Lapisan     | Teknologi             |
| ----------- | --------------------- |
| Backend     | PHP 8.3 · Laravel 13  |
| Frontend    | Livewire · Alpine.js · Tailwind CSS 4 |
| Admin       | Filament 5            |
| Database    | MySQL 8.4             |
| Queue       | Laravel Queue (database) |

## Setup Lokal

```bash
git clone <repo-url> albasambosa
cd albasambosa

composer install
cp .env.example .env
php artisan key:generate

# Sesuaikan .env (database, Midtrans, Biteship, Mail)
php artisan migrate --seed
```

```bash
# Jalankan dev server
npm install && npm run build
composer run dev
```

### Requirement

- PHP 8.3 + ekstensi: `pdo_mysql`, `mbstring`, `xml`, `curl`
- MySQL 8.4
- Composer 2.x
- Node.js 20+

## Commands

| Command | Keterangan |
| --- | --- |
| `composer run dev` | Server + Queue + Vite |
| `php artisan test --compact` | Jalankan semua test |
| `vendor/bin/pint --format agent` | Format kode |
| `php artisan scribe:generate` | Generate dokumentasi API (OpenAPI) |
| `php artisan privacy:anonymize-guests` | Anonimisasi data guest > 24 bulan |
| `php artisan privacy:anonymize-registered` | Anonimisasi akun > 36 bulan |

## Struktur Proyek

```
├── app/                        # Source code
├── database/                   # Migration & Seeder
├── tests/                      # Unit & Feature (Pest)
├── docs/                       # Diagram, wireframe, API spec
└── scripts/                    # Script utility
```

## Branching

| Branch | Tujuan |
| --- | --- |
| `main` | Produksi |
| `develop` | Integrasi fitur |
| `feature/*` | Pengembangan modul |

## Commit Convention

`feat:` · `fix:` · `docs:` · `test:` · `chore:`

## Lisensi

Proprietary — © AlbaSambosa.
