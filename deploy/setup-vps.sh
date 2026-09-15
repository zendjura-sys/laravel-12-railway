#!/usr/bin/env bash
#
# Provisions this Laravel app on a fresh Ubuntu LEMP server (nginx + PHP-FPM + MariaDB/MySQL).
# Written for an OpenVZ container, so it never assumes it can touch the kernel.
#
# Usage (as root):
#   APP_DOMAIN=example.com ./deploy/setup-vps.sh
#   APP_DOMAIN=_ ./deploy/setup-vps.sh          # serve on the bare IP
#
# Re-running is safe: every step checks for its own result first.

set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/laravel}"
APP_DOMAIN="${APP_DOMAIN:-_}"
DB_NAME="${DB_NAME:-laravel}"
DB_USER="${DB_USER:-laravel}"
DB_PASSWORD="${DB_PASSWORD:-}"
CRED_FILE="/root/laravel-deploy-credentials.txt"

log()  { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m[!] %s\033[0m\n' "$*"; }
die()  { printf '\033[1;31m[x] %s\033[0m\n' "$*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "Запускать от root."
[ -f "${APP_DIR}/artisan" ] || [ -f ./artisan ] || die "Не вижу artisan. Сначала помести код проекта в ${APP_DIR}."

# ---------------------------------------------------------------- PHP version
log "Определяю версию PHP"
PHP_VER="${PHP_VER:-$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)}"
[ -n "$PHP_VER" ] || die "PHP не найден. Стек LEMP должен был его поставить."
php -r 'exit(PHP_VERSION_ID < 80200 ? 1 : 0);' || die "PHP ${PHP_VER} слишком старый — Laravel 12 требует 8.2+."
echo "PHP ${PHP_VER}"

# ------------------------------------------------------------------- packages
log "Ставлю расширения PHP и утилиты"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
# soap обязателен: его требуют sped-nfe, sped-common и sped-gtin
apt-get install -y -qq \
    "php${PHP_VER}-fpm" "php${PHP_VER}-cli" "php${PHP_VER}-soap" "php${PHP_VER}-mysql" \
    "php${PHP_VER}-mbstring" "php${PHP_VER}-xml" "php${PHP_VER}-curl" "php${PHP_VER}-zip" \
    "php${PHP_VER}-bcmath" "php${PHP_VER}-intl" "php${PHP_VER}-gd" \
    git unzip curl

for ext in soap pdo_mysql mbstring xml curl zip bcmath; do
    php -m | grep -qix "$ext" || warn "Расширение ${ext} не активировалось — проверь вручную."
done

if ! command -v composer >/dev/null 2>&1; then
    log "Ставлю Composer"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
    rm -f /tmp/composer-setup.php
fi

# ----------------------------------------------------------------------- swap
# На OpenVZ swap почти всегда запрещён хостом. Пробуем, но падать из-за этого не станем.
if [ "$(swapon --show --noheadings | wc -l)" -eq 0 ]; then
    log "Пробую создать swap (2 ГБ)"
    if fallocate -l 2G /swapfile 2>/dev/null && chmod 600 /swapfile && mkswap -q /swapfile 2>/dev/null && swapon /swapfile 2>/dev/null; then
        grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
        echo "swap подключён"
    else
        rm -f /swapfile
        warn "Swap создать нельзя — ограничение OpenVZ. Composer запущу с лимитом памяти."
    fi
fi

# ------------------------------------------------------------------- database
log "Готовлю базу данных"
if command -v mysql >/dev/null 2>&1; then
    if [ -z "$DB_PASSWORD" ]; then
        DB_PASSWORD="$(head -c 18 /dev/urandom | base64 | tr -d '/+=' | head -c 24)"
    fi
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
    umask 077
    printf 'DB_DATABASE=%s\nDB_USERNAME=%s\nDB_PASSWORD=%s\n' "$DB_NAME" "$DB_USER" "$DB_PASSWORD" > "$CRED_FILE"
    echo "База ${DB_NAME} и пользователь ${DB_USER} готовы. Пароль сохранён в ${CRED_FILE}"
else
    die "Клиент mysql не найден — база в стек не вошла."
fi

# ---------------------------------------------------------------- application
cd "$APP_DIR"

if [ ! -f .env ]; then
    log "Создаю .env"
    cp .env.example .env
    # php ниже читает эти значения через getenv(), поэтому их обязательно экспортировать
    if [ "$APP_DOMAIN" = "_" ]; then
        APP_URL_VALUE="http://$(hostname -I | awk '{print $1}')"
    else
        APP_URL_VALUE="http://${APP_DOMAIN}"
    fi
    export APP_URL_VALUE DB_NAME DB_USER DB_PASSWORD
    php -r '
        $f = ".env"; $s = file_get_contents($f);
        $set = function ($k, $v) use (&$s) {
            $line = $k . "=" . $v;
            $s = preg_match("/^#?\s*" . preg_quote($k, "/") . "=.*$/m", $s)
                ? preg_replace("/^#?\s*" . preg_quote($k, "/") . "=.*$/m", $line, $s)
                : $s . "\n" . $line;
        };
        $set("APP_ENV", "production");
        $set("APP_DEBUG", "false");
        $set("APP_URL", getenv("APP_URL_VALUE"));
        $set("DB_CONNECTION", "mysql");
        $set("DB_HOST", "127.0.0.1");
        $set("DB_PORT", "3306");
        $set("DB_DATABASE", getenv("DB_NAME"));
        $set("DB_USERNAME", getenv("DB_USER"));
        $set("DB_PASSWORD", getenv("DB_PASSWORD"));
        file_put_contents($f, $s);
    ' 
else
    warn ".env уже есть — оставляю как есть."
fi

log "Устанавливаю зависимости (без dev)"
export COMPOSER_ALLOW_SUPERUSER=1
export COMPOSER_MEMORY_LIMIT=-1
composer install --no-dev --optimize-autoloader --no-interaction

grep -q '^APP_KEY=base64:' .env || { log "Генерирую APP_KEY"; php artisan key:generate --force; }

log "Применяю миграции"
php artisan migrate --force

log "Права на storage и bootstrap/cache"
chown -R www-data:www-data "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
find "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" -type d -exec chmod 775 {} \;

log "Кэширую конфигурацию"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ---------------------------------------------------------------------- nginx
log "Настраиваю nginx"
PHP_SOCK="/run/php/php${PHP_VER}-fpm.sock"
[ -S "$PHP_SOCK" ] || warn "Сокет ${PHP_SOCK} не найден — проверь, запущен ли php${PHP_VER}-fpm."

sed -e "s|__SERVER_NAME__|${APP_DOMAIN}|g" \
    -e "s|__APP_DIR__|${APP_DIR}|g" \
    -e "s|__PHP_SOCK__|${PHP_SOCK}|g" \
    "${APP_DIR}/deploy/nginx-laravel.conf" > /etc/nginx/sites-available/laravel

ln -sf /etc/nginx/sites-available/laravel /etc/nginx/sites-enabled/laravel
rm -f /etc/nginx/sites-enabled/default

nginx -t || die "Конфиг nginx не прошёл проверку."
systemctl reload nginx
systemctl enable --now "php${PHP_VER}-fpm" >/dev/null 2>&1 || true

log "Готово"
echo "Приложение:  ${APP_DIR}"
echo "Домен:       ${APP_DOMAIN}"
echo "Доступы БД:  ${CRED_FILE}"
