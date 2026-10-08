#!/bin/sh
# Entrypoint Axelo Mart: tunggu DB -> migrate -> (opsional) seed -> storage:link -> cache -> jalankan Apache.
set -e
cd /var/www/html

if [ -z "$APP_KEY" ]; then
    echo "[axelo] ERROR: APP_KEY kosong. Buat dengan: echo \"base64:\$(openssl rand -base64 32)\"" >&2
    exit 1
fi

# Volume storage bisa masih kosong saat pertama kali dipasang.
mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

echo "[axelo] Menunggu database..."
php -r '
$url = getenv("DB_URL") ? parse_url(getenv("DB_URL")) : [];
$host = $url["host"] ?? (getenv("DB_HOST") ?: "127.0.0.1");
$port = $url["port"] ?? (getenv("DB_PORT") ?: 3306);
$user = isset($url["user"]) ? urldecode($url["user"]) : getenv("DB_USERNAME");
$pass = isset($url["pass"]) ? urldecode($url["pass"]) : getenv("DB_PASSWORD");
$timeout = (int) (getenv("DB_WAIT_TIMEOUT") ?: 60);
for ($i = 1; $i <= $timeout; $i++) {
    try { new PDO("mysql:host=$host;port=$port", $user, $pass, [PDO::ATTR_TIMEOUT => 2]); echo "[axelo] Database siap ($host:$port).\n"; exit(0); }
    catch (Throwable $e) { if ($i % 5 === 1) fwrite(STDERR, "[axelo] DB belum siap ($host:$port): ".$e->getMessage()."\n"); sleep(1); }
}
fwrite(STDERR, "[axelo] ERROR: database tidak bisa dihubungi setelah {$timeout} detik.\n"); exit(1);
'

php artisan migrate --force

if [ "$RUN_SEEDER" = "true" ]; then
    # Hanya mengisi data dummy jika database masih kosong (aman walau RUN_SEEDER lupa dimatikan).
    php artisan app:seed-demo
fi

php artisan storage:link --force >/dev/null 2>&1 || true
php artisan optimize   # config, event, route, view cache

# Semua file yang dibuat oleh perintah artisan di atas harus bisa ditulis oleh Apache (www-data).
chown -R www-data:www-data storage bootstrap/cache

echo "[axelo] Siap. Menjalankan: $*"
exec "$@"
