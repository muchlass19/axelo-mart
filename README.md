# Axelo Mart 🛒

Project **dummy marketplace** sederhana berbasis **Laravel 13 + MySQL**, disiapkan untuk nanti dihubungkan ke **AI chatbot (web & WhatsApp)** yang membaca data produk, stok, dan laporan penjualan lewat API.

Hanya untuk dijalankan **lokal** (bukan production).

## Fitur

- **Auth sederhana**: login, register (otomatis jadi `customer`), logout.
- **Role**: `customer` (belanja di marketplace) dan `admin` (dashboard admin). Dicek oleh middleware `role:...`.
- **Admin**
  - Dashboard ringkasan: total penjualan, penjualan 30 hari, order pending, produk terlaris, stok menipis, pesanan terbaru.
  - CRUD produk: nama, harga, stok, status aktif/nonaktif, deskripsi, **multi gambar** (upload banyak, hapus per gambar).
  - Toggle aktif/nonaktif produk dengan sekali klik.
  - Daftar pesanan (filter status / cari) + ubah status pesanan.
- **Customer**
  - Katalog (hanya produk **aktif** dan **stok > 0**), pencarian, detail produk dengan galeri gambar.
  - Keranjang (session), checkout, riwayat pesanan.
  - Pembayaran **Midtrans Snap (Sandbox)** + tombol **Cek Status Pembayaran**.
- **API chatbot** read-only di `/api/v1` dengan header `X-API-KEY`.
- **Widget chatbot AI** (tombol 💬 di semua halaman) memakai LLM lewat **9router**. Akses data dibatasi per role di server.
- UI: Blade + Bootstrap 5 via CDN (tanpa npm / build step).

## Kebutuhan

- PHP **8.3+** dengan ekstensi: `pdo_mysql`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `intl`, `fileinfo` (+ `pdo_sqlite` untuk menjalankan test)
- Composer 2
- MySQL 8 / MariaDB 10.6+
- (Opsional) akun Midtrans Sandbox dan ngrok

## Setup

```bash
git clone https://github.com/muchlass19/axelo-mart.git
cd axelo-mart

composer install
cp .env.example .env
php artisan key:generate
```

Buat database kosong, misalnya:

```sql
CREATE DATABASE axelo_mart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Lalu sesuaikan `.env`:

```dotenv
DB_DATABASE=axelo_mart
DB_USERNAME=root
DB_PASSWORD=

CHATBOT_API_KEY=isi-dengan-string-acak-panjang   # contoh: openssl rand -hex 24

# opsional (lihat bagian Midtrans)
MIDTRANS_SERVER_KEY=
MIDTRANS_CLIENT_KEY=
MIDTRANS_IS_PRODUCTION=false
```

Jalankan migrasi + data dummy, link storage, dan server:

```bash
php artisan migrate --seed        # atau migrate:fresh --seed untuk reset total
php artisan storage:link
php artisan serve                 # http://localhost:8000
```

> Shortcut: `composer run setup` (install, copy .env, key:generate, migrate --seed, storage:link). Tetap isi kredensial DB di `.env` dulu.

## Akun Demo (dari seeder)

Semua password: **`password`**

| Role     | Email              |
|----------|--------------------|
| Admin    | `admin@axelo.test` |
| Customer | `budi@axelo.test`  |
| Customer | `siti@axelo.test`  |
| Customer | `andi@axelo.test`  |

Data dummy lainnya: 20 produk (2 nonaktif, 1 stok habis, beberapa stok menipis) masing-masing 2 gambar placeholder SVG yang **dibuat lokal saat seeding** (tanpa hotlink), dan ±90 order tersebar di 60 hari terakhir dengan berbagai status.

## Status Pesanan & Aturan Stok

| Status      | Arti                          | Dihitung sebagai penjualan? |
|-------------|-------------------------------|-----------------------------|
| `pending`   | Menunggu pembayaran           | ❌ |
| `paid`      | Sudah dibayar                 | ✅ |
| `shipped`   | Dikirim                       | ✅ |
| `completed` | Selesai                       | ✅ |
| `failed`    | Pembayaran gagal / ditolak    | ❌ |
| `expired`   | Pembayaran kedaluwarsa        | ❌ |
| `cancelled` | Dibatalkan                    | ❌ |

**Kapan stok dipotong?** Saat **pembayaran sukses** (status berubah ke `paid`/`shipped`/`completed`), bukan saat checkout.

- Saat checkout stok hanya **dicek** (cukup atau tidak), belum dipotong. Jadi order yang tidak dibayar (expired/failed/cancelled) tidak mengunci stok dan tidak perlu dikembalikan.
- Pemotongan stok terjadi di `Order::transitionTo()` di dalam transaksi DB dengan `lockForUpdate`, dan ditandai kolom `stock_deducted` → **idempotent** (webhook yang dikirim berkali-kali + tombol cek status tidak memotong stok dua kali).
- Jika order yang sudah dibayar kemudian di-set admin ke `cancelled`/`failed`/`expired`/`pending`, stok **dikembalikan**.
- Konsekuensi: kalau dua orang checkout barang terakhir bersamaan, keduanya bisa membayar (oversold). Stok tidak akan minus (dipotong maksimal sampai 0) dan kejadian ini dicatat di `storage/logs/laravel.log` sebagai warning, untuk ditangani manual (refund). Untuk project dummy ini dianggap cukup.
- Laporan penjualan memakai tanggal **`paid_at`** (tanggal bayar).

## Pembayaran Midtrans (Sandbox)

1. Daftar / login di <https://dashboard.sandbox.midtrans.com>.
2. Buka **Settings → Access Keys**, salin **Server Key** & **Client Key** (diawali `SB-Mid-...`) ke `.env`:
   ```dotenv
   MIDTRANS_SERVER_KEY=SB-Mid-server-xxxx
   MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxx
   MIDTRANS_IS_PRODUCTION=false
   ```
3. Login sebagai customer → tambah produk ke keranjang → **Buat Pesanan** → popup Snap terbuka otomatis.
4. Bayar memakai data uji sandbox, misalnya:
   - Kartu kredit `4811 1111 1111 1114`, CVV `123`, expiry bulan/tahun mana saja di masa depan, OTP `112233`.
   - Virtual Account / QRIS / e-wallet: selesaikan di simulator <https://simulator.sandbox.midtrans.com>.
5. Setelah popup selesai, aplikasi otomatis memanggil **Cek Status Pembayaran**. Tombol ini juga bisa diklik manual kapan saja di halaman detail pesanan: aplikasi memanggil **Midtrans Transaction Status API** lalu menyinkronkan status order. Cara ini jalan **tanpa webhook**, jadi cocok untuk lokal.

Jika key Midtrans kosong, aplikasi **tidak error**: pesanan tetap dibuat (status `pending`) dan muncul pesan bahwa Midtrans belum dikonfigurasi. Admin tetap bisa mengubah status pesanan secara manual untuk testing.

### Webhook (HTTP Notification) via ngrok

Endpoint: `POST /midtrans/notification` (dikecualikan dari CSRF, signature diverifikasi dengan `sha512(order_id + status_code + gross_amount + server_key)`, dan `gross_amount` dicocokkan dengan total order).

Karena Midtrans tidak bisa mengakses `localhost`, expose dengan ngrok:

```bash
php artisan serve
ngrok http 8000
```

Lalu di dashboard sandbox Midtrans buka **Settings → Payment → Notification URL** (di dashboard lama: *Settings → Configuration → Payment Notification URL*) dan isi:

```
https://xxxx-xx-xx.ngrok-free.app/midtrans/notification
```

Mapping status Midtrans → order: `settlement`/`capture(accept)` → `paid`, `pending` → `pending`, `deny`/`failure` → `failed`, `expire` → `expired`, `cancel` → `cancelled`. Order yang sudah `paid/shipped/completed` tidak akan diturunkan statusnya oleh notifikasi Midtrans.

## API Chatbot (`/api/v1`)

- Semua endpoint **GET**, read-only, respons JSON.
- Wajib header **`X-API-KEY: <CHATBOT_API_KEY>`**.
  - Tanpa / salah key → `401`.
  - `CHATBOT_API_KEY` kosong di `.env` → `503` (API tertutup).
- Rate limit 120 request/menit.
- Penjualan hanya menghitung order berstatus `paid`, `shipped`, `completed`.

Siapkan variabel untuk contoh di bawah:

```bash
BASE=http://localhost:8000/api/v1
KEY=isi-CHATBOT_API_KEY-anda
```

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/products` | List / cari produk |
| GET | `/products/{id}` | Detail produk |
| GET | `/stock` | Cek stok berdasarkan `id` atau `name` |
| GET | `/stock/low` | Daftar stok menipis |
| GET | `/reports/sales` | Laporan penjualan per periode |
| GET | `/orders/recent` | Ringkasan pesanan terbaru |

### 1. List / cari produk

Parameter (opsional): `search` (nama), `status` (`active`/`inactive`), `available=1` (hanya aktif & stok > 0), `per_page` (1–100, default 20), `page`.

```bash
curl -H "X-API-KEY: $KEY" "$BASE/products"
curl -H "X-API-KEY: $KEY" "$BASE/products?search=kopi&status=active"
curl -H "X-API-KEY: $KEY" "$BASE/products?available=1&per_page=5"
```

```json
{
  "data": [
    {
      "id": 1, "name": "Kopi Arabika Gayo 250g", "price": 85000, "price_formatted": "Rp 85.000",
      "stock": 40, "status": "active", "is_available": true,
      "description": "Biji kopi arabika asli Aceh Gayo...",
      "images": ["http://localhost:8000/storage/products/seed/1-1.svg", "..."],
      "url": "http://localhost:8000/products/1", "updated_at": "2026-10-08T09:23:27+07:00"
    }
  ],
  "links": { "...": "..." },
  "meta": { "current_page": 1, "total": 2, "...": "..." }
}
```

### 2. Detail produk

```bash
curl -H "X-API-KEY: $KEY" "$BASE/products/1"
```

Respons `{"data": {...}}` (format sama seperti di atas). Produk tidak ada → `404 {"message": "Produk tidak ditemukan."}`.

### 3. Cek stok (by id atau nama)

```bash
curl -H "X-API-KEY: $KEY" "$BASE/stock?id=1"
curl -H "X-API-KEY: $KEY" "$BASE/stock?name=kopi"
```

```json
{
  "query": "kopi",
  "count": 2,
  "data": [
    { "id": 1, "name": "Kopi Arabika Gayo 250g", "stock": 40, "status": "active", "is_available": true, "price": 85000 },
    { "id": 2, "name": "Kopi Robusta Lampung 250g", "stock": 35, "status": "active", "is_available": true, "price": 55000 }
  ]
}
```

Tanpa `id` maupun `name` → `422`.

### 4. Stok menipis

Parameter: `threshold` (default `LOW_STOCK_THRESHOLD` di `.env`, default 5). Mengembalikan produk dengan `stock <= threshold` (termasuk produk nonaktif, lihat field `status`).

```bash
curl -H "X-API-KEY: $KEY" "$BASE/stock/low"
curl -H "X-API-KEY: $KEY" "$BASE/stock/low?threshold=10"
```

```json
{ "threshold": 5, "count": 5, "data": [ { "id": 11, "name": "Sandal Jepit Karet Premium", "stock": 0, "status": "active", "is_available": false, "price": 35000 } ] }
```

### 5. Laporan penjualan per periode

Parameter: `from`, `to` (format `YYYY-MM-DD`, default 30 hari terakhir), `limit` (jumlah top produk, default 5, maks 50).

```bash
curl -H "X-API-KEY: $KEY" "$BASE/reports/sales"
curl -H "X-API-KEY: $KEY" "$BASE/reports/sales?from=2026-09-01&to=2026-09-30&limit=3"
```

```json
{
  "period": { "from": "2026-09-01", "to": "2026-09-30" },
  "counted_statuses": ["paid", "shipped", "completed"],
  "total_revenue": 14132000,
  "total_orders": 36,
  "items_sold": 135,
  "average_order_value": 392556,
  "total_revenue_formatted": "Rp 14.132.000",
  "top_products": [
    { "product_id": 9, "product_name": "Kemeja Batik Pria Lengan Pendek", "quantity_sold": 12, "revenue": 2100000 }
  ]
}
```

### 6. Ringkasan pesanan terbaru

Parameter: `limit` (default 10, maks 50), `status` (filter list), `days` (periode ringkasan per status, default 7).

```bash
curl -H "X-API-KEY: $KEY" "$BASE/orders/recent"
curl -H "X-API-KEY: $KEY" "$BASE/orders/recent?limit=5&status=pending&days=30"
```

```json
{
  "summary": { "days": 7, "total_orders": 23, "by_status": { "completed": 4, "paid": 8, "pending": 3 } },
  "data": [
    {
      "order_number": "AXM-261008-7DAECB", "customer_name": "Budi", "status": "paid", "status_label": "Dibayar",
      "total_amount": 290000, "total_amount_formatted": "Rp 290.000", "items_count": 3,
      "items": [ { "product_name": "Kopi Arabika Gayo 250g", "quantity": 2, "subtotal": 170000 } ],
      "payment_type": "bank_transfer", "created_at": "2026-10-08T09:23:00+07:00", "paid_at": "2026-10-08T09:25:00+07:00"
    }
  ]
}
```

### Contoh tanpa API key

```bash
curl -i "$BASE/products"
# HTTP/1.1 401 Unauthorized
# {"message":"API key tidak valid atau tidak dikirim (header X-API-KEY)."}
```

## Chatbot AI (Widget Web) via 9router

Di pojok kanan bawah setiap halaman (marketplace & admin) ada tombol 💬. Widget mengirim pesan ke `POST /chatbot` (dilindungi CSRF, maksimal 20 pesan/menit per user atau per IP untuk guest). Riwayat percakapan disimpan di session (20 pesan terakhir) dan bisa dihapus dengan tombol **Reset**.

LLM-nya diakses lewat [9router](https://github.com/decolua/9router), gateway lokal yang kompatibel dengan OpenAI (`POST /v1/chat/completions` + function/tool calling).

### Setup 9router

```bash
npm install -g 9router
9router                      # dashboard: http://localhost:20128/dashboard, API: http://localhost:20128/v1
```

1. Buka dashboard 9router → **Providers** → hubungkan minimal satu provider (mis. OpenCode Free, Kiro, Gemini, atau provider API key lain).
2. Salin **API key** dari dashboard 9router (kosongkan kalau autentikasi API dimatikan).
3. Lihat daftar model yang tersedia lalu pilih satu, **sebaiknya yang mendukung tool calling** (`"tools": true` di field `capabilities`):
   ```bash
   curl -H "Authorization: Bearer <API_KEY_9ROUTER>" http://localhost:20128/v1/models
   ```
4. Isi `.env`:
   ```dotenv
   NINEROUTER_BASE_URL=http://localhost:20128/v1
   NINEROUTER_API_KEY=isi-api-key-9router
   NINEROUTER_MODEL=id-model-dari-/v1/models
   NINEROUTER_TIMEOUT=60
   ```
   Tidak ada model yang di-hardcode. Kalau `NINEROUTER_MODEL` kosong, widget menampilkan pesan "asisten AI belum dikonfigurasi".

### Akses per role (dicek di server, bukan hanya lewat prompt)

| Role | Tool yang dikirim ke LLM | Data yang bisa diakses |
|---|---|---|
| Guest & customer | `search_products`, `get_product`, `check_stock` | Hanya produk **aktif**: cari/daftar produk, detail, harga, ketersediaan stok |
| Admin | semua tool di atas + `low_stock`, `sales_report`, `recent_orders` | Semua produk (termasuk nonaktif), stok menipis, laporan penjualan per periode, pesanan terbaru & jumlah per status |

- Hanya definisi tool yang diizinkan untuk role tersebut yang dikirim ke LLM.
- Saat LLM memanggil tool, role **dicek ulang**. Tool di luar izin ditolak ("Akses ditolak"), dan tool produk untuk guest/customer selalu dibatasi ke produk aktif, meskipun LLM mengirim parameter lain.
- Customer **tidak** bisa melihat data pesanan lewat chatbot (termasuk pesanan sendiri); bot akan mengarahkan ke menu "Pesanan Saya".
- Tool memakai service yang sama dengan API `/api/v1` (`App\Services\CatalogService` & `ReportService`), tidak memanggil HTTP API secara internal.

### Perilaku saat ada masalah

- Model tidak mengembalikan `tool_calls` → teks jawabannya langsung dipakai.
- Loop tool calling dibatasi **5 putaran**, lalu bot diminta memberi jawaban akhir tanpa tool.
- Provider menolak parameter `tools` (HTTP 400/422) → dicoba ulang sekali tanpa tools.
- 9router mati, timeout, error, atau belum dikonfigurasi → widget menampilkan pesan error ramah dalam Bahasa Indonesia (HTTP 503), aplikasi tidak crash. Detail error dicatat di `storage/logs/laravel.log`.

## Testing

```bash
php artisan test
```

Test memakai SQLite in-memory (lihat `phpunit.xml`), jadi butuh ekstensi `pdo_sqlite`. Untuk menjalankan test ke MySQL:

```bash
DB_CONNECTION=mysql DB_DATABASE=axelo_mart_test DB_USERNAME=root DB_PASSWORD= php artisan test
```

Cakupan test: register/login & akses per role, CRUD produk + upload multi gambar, potong/kembalikan stok saat ubah status, katalog, checkout tanpa key Midtrans, webhook Midtrans (signature, idempotent, expire), proteksi API key, pencarian produk & cek stok, laporan penjualan, dan chatbot (9router di-mock dengan `Http::fake`: tool per role, penolakan tool call palsu, batas loop, error saat 9router mati/belum dikonfigurasi, riwayat, rate limit).

## Struktur Singkat

```
app/
  Http/Controllers/
    Admin/        DashboardController, ProductController, OrderController
    Api/V1/       ProductController, StockController, ReportController, OrderController
    Auth/         AuthController
    ShopController, CartController, CheckoutController, OrderController, MidtransNotificationController, ChatbotController
  Http/Middleware/ EnsureRole (role:admin|customer), ChatbotApiKey (X-API-KEY)
  Models/          User, Product, ProductImage, Order, OrderItem
  Services/        MidtransService, ReportService, CatalogService
    Chatbot/       NineRouterClient, ChatbotService, ChatbotTools
database/seeders/  UserSeeder, ProductSeeder, OrderSeeder
routes/            web.php, api.php
```
