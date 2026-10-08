# Deploy Axelo Mart ke Easypanel

Panduan ini menjelaskan cara deploy **Axelo Mart** (Laravel + MySQL) dan **9router** (gateway LLM untuk chatbot) dalam **satu project Easypanel**.

> Nama menu/tab di bawah mengikuti dokumentasi resmi Easypanel (<https://easypanel.io/docs>). Tampilan panel bisa sedikit berbeda antar versi. Kalau label tidak sama persis, cari menu dengan fungsi yang sama.

## Gambaran arsitektur

```
Internet ──HTTPS──> Traefik (Easypanel) ──> app  (Axelo Mart, port 80)
                                    │
                    (opsional, domain + Basic Auth) ──> ninerouter (9router, port 20128)

Di dalam project (jaringan privat):
  app ──> mysql:3306           (database)
  app ──> ninerouter:20128/v1  (LLM chatbot)
```

Contoh di panduan ini memakai nama:

| Item | Contoh nama |
|---|---|
| Project | `axelo` |
| Service MySQL | `mysql` |
| Service Laravel | `app` |
| Service 9router | `ninerouter` |

Hostname internal antar service mengikuti format **`<project>_<service>`**. Contoh di dokumentasi Easypanel: `postgres://user:password@project_database:5432/app`. Dengan nama contoh di atas, hasilnya `axelo_mysql` dan `axelo_ninerouter`. Untuk MySQL, **selalu salin host persisnya dari tab Credentials**.

---

## 0. Persiapan

1. **Server Easypanel** sudah terpasang dan domain diarahkan ke IP server (record A). Contoh: `shop.domainanda.com`, dan opsional `ai.domainanda.com` untuk dashboard 9router.
2. **Token GitHub**, karena repo `muchlass19/axelo-mart` **private**. Easypanel butuh Personal Access Token:
   - *Fine-grained token*: pilih repo `axelo-mart`, beri permission **Contents: Read-only**, **Metadata: Read-only**, dan **Webhooks: Read and write** (hanya jika ingin auto deploy).
   - atau *classic token* dengan scope `repo`.
   - Di Easypanel buka **Settings → Github**, tempel token. Jika valid akan muncul pesan "Github token updated".
   - Alternatif tanpa token: pakai source **Git** (SSH) dan tambahkan SSH key service sebagai *deploy key* read-only di repo.
3. **Siapkan secret** (jalankan di komputer lokal):
   ```bash
   # APP_KEY Laravel
   echo "base64:$(openssl rand -base64 32)"
   # atau, jika ada PHP + project lokal: php artisan key:generate --show

   # CHATBOT_API_KEY (untuk API /api/v1)
   openssl rand -hex 24

   # Secret 9router
   openssl rand -hex 32   # JWT_SECRET
   openssl rand -hex 32   # API_KEY_SECRET
   openssl rand -hex 16   # MACHINE_ID_SALT
   ```
   Simpan baik-baik. **Jangan pernah commit secret ke repo.**

---

## 1. Buat project

Di dashboard Easypanel buat project baru, misalnya `axelo`.

## 2. Service MySQL

1. Di project, pilih **New Service → MySQL**, beri nama `mysql`.
2. (Opsional) isi nama database, user, dan password. Kalau dikosongkan, Easypanel memakai nama database = nama project, user `mysql`, dan password acak.
3. Buat service dan tunggu sampai berjalan.
4. Buka tab **Credentials**, catat:
   - **internal host** (misal `axelo_mysql`) dan port `3306`
   - database name, user, user password

MySQL bersifat privat secara default. **Tidak perlu** di-*Expose* karena app mengaksesnya lewat jaringan internal.

## 3. Service App (Laravel)

### 3.1 Source & build

1. **New Service → App**, beri nama `app`.
2. Tab **Source → GitHub**:
   - Repository: `muchlass19/axelo-mart` (format `owner/repo`)
   - Branch: `main`
   - Build Path: `/`
3. Bagian **Build**: pilih **Dockerfile**, dengan path file `Dockerfile` (ada di root repo).

Image yang dihasilkan:

- PHP 8.4 + Apache, dan **listen di port 80**.
- Saat container start, entrypoint (`docker/entrypoint.sh`) otomatis:
  1. menunggu database siap (maks `DB_WAIT_TIMEOUT` detik, default 60)
  2. `php artisan migrate --force`
  3. jika `RUN_SEEDER=true`: seed data dummy **hanya bila database masih kosong**
  4. `storage:link`, lalu `php artisan optimize` (cache config, route, view, event)
  5. menjalankan Apache

### 3.2 Environment

Buka tab **Environment** dan isi (format `.env`). Sesuaikan nilai yang diberi tanda `<...>`:

```dotenv
APP_NAME="Axelo Mart"
APP_ENV=production
APP_DEBUG=false
APP_KEY=<base64:hasil-perintah-di-langkah-0>
APP_URL=https://<domain-anda>
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id
APP_FALLBACK_LOCALE=en

# Database: ambil dari tab Credentials service MySQL
DB_CONNECTION=mysql
DB_HOST=<internal-host-mysql, mis. axelo_mysql>
DB_PORT=3306
DB_DATABASE=<nama-database>
DB_USERNAME=<user>
DB_PASSWORD=<password>

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=public
LOG_CHANNEL=stderr
LOG_LEVEL=info

# Di belakang Traefik/HTTPS (default sudah aman, boleh tidak ditulis)
TRUSTED_PROXIES=*
FORCE_HTTPS=true

# Seed data dummy saat deploy pertama (aman: dilewati jika DB sudah berisi data)
RUN_SEEDER=true

LOW_STOCK_THRESHOLD=5

# API chatbot /api/v1 (header X-API-KEY)
CHATBOT_API_KEY=<hasil-openssl-rand-hex-24>

# Midtrans Sandbox
MIDTRANS_SERVER_KEY=<SB-Mid-server-...>
MIDTRANS_CLIENT_KEY=<SB-Mid-client-...>
MIDTRANS_IS_PRODUCTION=false

# Chatbot via 9router (diisi setelah langkah 5; boleh kosong dulu)
NINEROUTER_BASE_URL=http://<project>_<service-9router>:20128/v1
NINEROUTER_API_KEY=
NINEROUTER_MODEL=
NINEROUTER_TIMEOUT=60
```

Catatan:

- Easypanel juga mendukung placeholder `$(PROJECT_NAME)`, `$(SERVICE_NAME)`, dan `$(PRIMARY_DOMAIN)`. Contohnya `APP_URL=https://$(PRIMARY_DOMAIN)` dan `NINEROUTER_BASE_URL=http://$(PROJECT_NAME)_ninerouter:20128/v1`.
- Alih-alih `DB_HOST/DB_PORT/...`, Anda juga boleh mengisi satu variabel `DB_URL` dengan **internal connection URL** dari tab Credentials MySQL.
- Kalau `APP_KEY` kosong, container sengaja berhenti dengan pesan error yang jelas.
- Perubahan Environment baru berlaku setelah **Deploy** ulang.

### 3.3 Storage (wajib, untuk gambar produk)

Gambar produk (upload admin dan placeholder seeder) disimpan di `storage/app/public`. Agar tidak hilang saat redeploy, buka tab **Storage** dan tambahkan **Volume**:

| Field | Nilai |
|---|---|
| Tipe | Volume |
| Mount path | `/var/www/html/storage/app/public` |

Entrypoint otomatis membuat folder dan mengatur permission (`www-data`) pada volume tersebut. Tab Storage juga bisa menjadwalkan backup volume.

### 3.4 Domain & HTTPS

Buka tab **Domains**, tambahkan domain Anda (misal `shop.domainanda.com`):

- aktifkan **HTTPS** (sertifikat otomatis dari Easypanel/Traefik)
- **target port: `80`**
- jadikan domain ini **primary**

Laravel sudah dikonfigurasi untuk mempercayai header `X-Forwarded-*` dari Traefik, dan saat `APP_ENV=production` semua URL dipaksa `https`. Dengan begitu link, asset, redirect, cookie, dan CSRF bekerja normal di belakang HTTPS.

### 3.5 Deploy pertama

1. Klik **Deploy**, lalu pantau output build di tab **Deployments** dan log runtime di **Logs**.
2. Log yang diharapkan:
   ```
   [axelo] Menunggu database...
   [axelo] Database siap (axelo_mysql:3306).
   ... migrations DONE
   [axelo] Data dummy berhasil di-seed.
   [axelo] Siap. Menjalankan: apache2-foreground
   ```
3. Buka `https://<domain-anda>`. Login admin: `admin@axelo.test` / `password`.
4. **Segera ganti password akun demo** (atau hapus akun demo) karena aplikasi bisa diakses publik.
5. Setelah seeding berhasil, ubah `RUN_SEEDER=false` (opsional, karena seeding tetap dilewati jika DB sudah berisi data).

Seed manual juga bisa dilakukan lewat **Shell** service app:

```bash
php artisan app:seed-demo           # seed hanya jika DB kosong
php artisan app:seed-demo --force   # paksa seed (menambah 20 produk & order dummy lagi)
```

### 3.6 Midtrans webhook

Di dashboard Midtrans Sandbox (**Settings → Payment → Notification URL**; di dashboard lama: *Settings → Configuration*), isi:

```
https://<domain-anda>/midtrans/notification
```

Tombol **"Cek Status Pembayaran"** di halaman pesanan tetap bisa dipakai sebagai cadangan jika webhook belum diatur.

---

## 4. Service 9router (gateway LLM) di project yang sama

Sumber: README & DOCKER.md resmi 9router (<https://github.com/decolua/9router>). Image resmi: **`decolua/9router`** di Docker Hub (juga `ghcr.io/decolua/9router`), multi-arch amd64/arm64.

### 4.1 Buat service

1. **New Service → App**, beri nama `ninerouter`.
2. Tab **Source → Docker Image**: `decolua/9router:0.5.95`. Sebaiknya pakai tag versi (bukan `latest`) supaya deploy bisa diulang dengan hasil yang sama. Cek tag terbaru di Docker Hub.
3. Tab **Environment**:
   ```dotenv
   DATA_DIR=/app/data
   NODE_ENV=production
   INITIAL_PASSWORD=<password-dashboard-yang-kuat>
   JWT_SECRET=<openssl rand -hex 32>
   API_KEY_SECRET=<openssl rand -hex 32>
   MACHINE_ID_SALT=<openssl rand -hex 16>
   REQUIRE_API_KEY=true
   # isi true jika dashboard diakses lewat domain HTTPS
   AUTH_COOKIE_SECURE=true
   ```
   - `INITIAL_PASSWORD` adalah password login pertama dashboard. **Default-nya `123456` jika tidak diisi, jadi wajib diganti.**
   - Tetapkan `API_KEY_SECRET` dan `MACHINE_ID_SALT` **sebelum** membuat API key, lalu jangan diubah lagi. Keduanya dipakai untuk HMAC/identitas API key, sehingga mengubahnya kemungkinan besar membuat key lama tidak valid.
4. Tab **Storage** → tambahkan **Volume** dengan mount path **`/app/data`**. Isinya database SQLite (`/app/data/db/data.sqlite`: provider, API key, setting), backup otomatis, `jwt-secret`, dan `machine-id`. Tanpa volume ini semua konfigurasi hilang saat redeploy.
5. Port aplikasi: **20128** (sudah default di image, `HOSTNAME=0.0.0.0`).
6. Klik **Deploy**. Health check internal tersedia di `GET /api/health`, yang mengembalikan `{"ok":true}`.

> Catatan soal error `sql.js`: saat 9router dipasang via `npm install -g 9router` (v0.5.95), dependency `sql.js` tidak ikut terpasang sehingga muncul error *"No SQLite driver available"*. **Image Docker resmi tidak terkena masalah ini** (memakai driver `better-sqlite3`, sudah diuji). Jadi di Easypanel pakai image Docker, bukan npm.

### 4.2 Akses dashboard 9router dengan aman

Dashboard (`/dashboard`) dibutuhkan untuk login, menghubungkan provider, dan membuat API key. Pilih salah satu cara:

- **A. Domain + Basic Auth (disarankan).** Tambahkan domain (misal `ai.domainanda.com`) di tab **Domains** dengan HTTPS dan **target port `20128`**. Lalu aktifkan **HTTP Basic Auth** di tab **Security** service `ninerouter`. Hasilnya ada dua lapis proteksi: Basic Auth dari proxy Easypanel, lalu login password 9router. Endpoint `/v1/*` dari internet juga tetap butuh API key (`REQUIRE_API_KEY=true`).
- **B. Domain sementara.** Tambahkan domain hanya selama setup (hubungkan provider dan buat API key), lalu hapus domainnya. App Laravel tetap bisa mengakses 9router lewat jaringan internal tanpa domain.

Komunikasi app → 9router lewat hostname internal **tidak melewati Traefik**, jadi Basic Auth di domain tidak mengganggu chatbot.

### 4.3 Hubungkan provider & buat API key

1. Buka dashboard 9router dan login dengan `INITIAL_PASSWORD`.
2. Menu **Providers**: hubungkan minimal satu provider. Ada provider tanpa login (misalnya OpenCode Free, dengan daftar model yang berubah-ubah), provider berbasis API key (OpenRouter, Groq, Gemini, DeepSeek, dll), dan provider OAuth.
   - Provider berbasis **API key** paling mudah dipakai di server.
   - Sebagian provider **OAuth** memakai callback login di port lokal tertentu. Alur ini kemungkinan perlu langkah tambahan di server remote, jadi ikuti petunjuk di dashboard/README 9router.
3. Buat **API key** di dashboard (halaman Endpoint / API Keys). Key berbentuk `sk-...`.
   - Akses `/v1` dari service lain **selalu butuh API key**. Tanpa key, 9router membalas `401 "API key required for remote API access"`.

### 4.4 Pilih model yang mendukung tool calling

Chatbot memakai *function/tool calling* untuk membaca data produk dan laporan. Lihat daftar model, lalu pilih yang `capabilities.tools` bernilai `true` **dan** provider-nya sudah terhubung. Jalankan dari **Shell** service `app` (image app berisi `curl`):

```bash
curl -s -H "Authorization: Bearer $NINEROUTER_API_KEY" "$NINEROUTER_BASE_URL/models" | head -c 2000
```

Uji satu model:

```bash
curl -s -H "Authorization: Bearer $NINEROUTER_API_KEY" -H "Content-Type: application/json" \
  "$NINEROUTER_BASE_URL/chat/completions" \
  -d '{"model":"<id-model>","messages":[{"role":"user","content":"Balas satu kata: halo"}]}'
```

`GET /v1/models` menampilkan katalog lengkap (ratusan model), termasuk model dari provider yang **belum** terhubung. Pastikan uji `chat/completions` di atas berhasil sebelum memakai model tersebut.

### 4.5 Sambungkan ke Laravel

Di Environment service `app`:

```dotenv
NINEROUTER_BASE_URL=http://axelo_ninerouter:20128/v1   # format <project>_<service>
NINEROUTER_API_KEY=sk-...
NINEROUTER_MODEL=<id-model-yang-lolos-uji>
```

Lalu **Deploy** ulang service `app`. Buka web dan klik tombol 💬:

- Sebagai guest/customer, tanyakan "ada kopi apa saja?"
- Sebagai admin, tanyakan "omzet bulan ini berapa?"

Kalau hostname internal tidak terhubung, cek dari Shell service `app`:

```bash
curl -s http://axelo_ninerouter:20128/api/health
```

---

## 5. Update aplikasi

- Push ke branch `main`, lalu klik **Deploy**. Atau aktifkan **Auto Deploy** (butuh permission Webhooks pada token GitHub).
- Migrasi baru otomatis dijalankan oleh entrypoint saat container start.
- Gunakan **Force Rebuild** jika build terlihat memakai cache lama.

---

## 6. Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| Container berhenti dengan `APP_KEY kosong` | Isi `APP_KEY` (lihat langkah 0), lalu Deploy ulang. |
| Log terus `DB belum siap ... ` lalu `database tidak bisa dihubungi` | `DB_HOST`/user/password salah, atau MySQL belum jalan. Salin ulang dari tab **Credentials** MySQL. Hostnya adalah host *internal* (`<project>_<service>`), bukan `localhost`/`127.0.0.1`. Naikkan `DB_WAIT_TIMEOUT` jika MySQL lambat start. |
| `502 Bad Gateway` / domain tidak bisa dibuka | Target port domain harus **80** untuk app dan **20128** untuk 9router. Cek tab **Logs** apakah Apache sudah jalan. |
| Halaman tampil tanpa CSS / link mengarah ke `http://` | Pastikan `APP_URL` memakai `https://` dan `APP_ENV=production` (atau `FORCE_HTTPS=true`). Deploy ulang supaya config cache diperbarui. |
| `419 Page Expired` saat login/form | Biasanya cookie tidak tersimpan. Akses lewat domain HTTPS (bukan IP/HTTP) karena `SESSION_SECURE_COOKIE=true`. Bersihkan cookie browser. |
| Gambar produk 404 | Volume `/var/www/html/storage/app/public` belum dipasang, atau sudah dipasang tapi data seed dibuat sebelum volume ada. Jalankan `php artisan app:seed-demo --force` atau upload ulang gambar. Pastikan `storage:link` berjalan (otomatis di entrypoint). |
| Upload gambar gagal "too large" | Batas PHP 10 MB per file / 25 MB per request (`docker/php.ini`). Validasi aplikasi maksimal 2 MB per gambar. |
| Error 500 tanpa detail | Lihat tab **Logs** (`LOG_CHANNEL=stderr`). Untuk debugging sementara boleh set `APP_DEBUG=true`, lalu **kembalikan ke `false`**. |
| Perubahan env tidak berpengaruh | Config di-cache saat container start. Deploy ulang setelah mengubah env. |
| Chatbot: "asisten AI belum dikonfigurasi" | `NINEROUTER_MODEL` kosong. |
| Chatbot: "tidak bisa dihubungi" | `NINEROUTER_BASE_URL` salah atau service 9router mati. Cek `curl http://<project>_<service>:20128/api/health` dari Shell app. |
| Chatbot: "sedang bermasalah (kode 401)" | `NINEROUTER_API_KEY` kosong/salah, **atau** provider menolak model (misalnya model tidak didukung / provider belum login). Uji dengan `curl .../chat/completions`. |
| Chatbot menjawab tapi tidak pernah memakai data toko | Model tidak mendukung tool calling. Pilih model dengan `capabilities.tools: true`. |
| Dashboard 9router minta password tapi `INITIAL_PASSWORD` tidak cocok | `INITIAL_PASSWORD` hanya berlaku saat belum ada password tersimpan di volume `/app/data`. Jika sudah pernah diganti, gunakan password terakhir (atau reset sesuai dokumentasi 9router). |
| API key 9router tiba-tiba tidak valid setelah redeploy | Pastikan volume `/app/data` terpasang dan `API_KEY_SECRET`/`MACHINE_ID_SALT` tidak berubah. |
| Midtrans webhook tidak mengubah status | Cek Notification URL `https://<domain>/midtrans/notification` dan pastikan server key di `.env` sama dengan dashboard Midtrans. Gunakan tombol "Cek Status Pembayaran" sebagai cadangan. |

---

## Referensi

- Easypanel App Service: <https://easypanel.io/docs/services/app>
- Easypanel MySQL Service: <https://easypanel.io/docs/services/mysql>
- Easypanel GitHub token: <https://easypanel.io/docs/code-sources/github>
- 9router README: <https://github.com/decolua/9router>
- 9router Docker: <https://github.com/decolua/9router/blob/master/DOCKER.md>
- Image 9router: <https://hub.docker.com/r/decolua/9router>
