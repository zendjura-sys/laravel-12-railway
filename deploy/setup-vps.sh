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

# ------------------------------------------------------- deploy status/log
# Пишем сюда же, откуда сайт читает статус кнопки "Задеплоїти" в адмінці —
# systemd-таймер запускает этот скрипт от root по изменению TRIGGER_FILE,
# сам сайт root не получает никогда, только пишет метку в этот файл
# (см. deploy/systemd/README.md).
DEPLOY_DIR="${APP_DIR}/storage/app/deploy"
TRIGGER_FILE="${DEPLOY_DIR}/trigger"
STATUS_FILE="${DEPLOY_DIR}/status.json"
LOG_FILE="${DEPLOY_DIR}/log.txt"
mkdir -p "$DEPLOY_DIR"
: > "$LOG_FILE"
exec > >(tee -a "$LOG_FILE") 2>&1

DEPLOY_STARTED_AT="$(date -Iseconds)"
printf '{"status":"running","started_at":"%s","finished_at":null,"exit_code":null}\n' "$DEPLOY_STARTED_AT" > "$STATUS_FILE"

deploy_finish() {
    local code=$?
    local finished_at
    finished_at="$(date -Iseconds)"
    if [ "$code" -eq 0 ]; then
        printf '{"status":"success","started_at":"%s","finished_at":"%s","exit_code":0}\n' "$DEPLOY_STARTED_AT" "$finished_at" > "$STATUS_FILE"
    else
        printf '{"status":"failed","started_at":"%s","finished_at":"%s","exit_code":%s}\n' "$DEPLOY_STARTED_AT" "$finished_at" "$code" > "$STATUS_FILE"
    fi
    rm -f "$TRIGGER_FILE"
    # Скрипт в итоге всегда запускается от root — эта строка гарантирует,
    # что www-data (читающий статус для кнопки в адмінці) сможет прочитать
    # файл вне зависимости от текущего umask, а не полагается на него.
    chmod 644 "$STATUS_FILE" "$LOG_FILE" 2>/dev/null || true
}
trap deploy_finish EXIT

# ------------------------------------------------------------- base packages
export DEBIAN_FRONTEND=noninteractive
log "Обновляю списки пакетов"
apt-get update -qq

# Некоторые образы хостера несут apache2 из коробки — он держит порт 80
# и не даёт nginx стартовать.
if systemctl list-unit-files 2>/dev/null | grep -q '^apache2\.service'; then
    log "Отключаю apache2 (держит порт 80)"
    systemctl stop apache2 2>/dev/null || true
    systemctl disable apache2 2>/dev/null || true
fi

log "Ставлю nginx"
if ! command -v nginx >/dev/null 2>&1; then
    apt-get install -y -qq nginx
fi

log "Ставлю MariaDB"
if ! command -v mysql >/dev/null 2>&1; then
    apt-get install -y -qq mariadb-server
    systemctl enable --now mariadb >/dev/null 2>&1 || true
fi

log "Ставлю Node.js"
# Нужен только для сборки фронтенда (Vite/Vue) во время деплоя — в рантайме
# сайту Node не требуется. Ubuntu 24.04 несёт в штатных репозиториях Node 18,
# а @vitejs/plugin-vue 6.x требует Node >=20.19 (использует crypto.hash,
# которого в 18-й ветке нет) — ставим 20 LTS через NodeSource.
if ! command -v node >/dev/null 2>&1 || [ "$(node -v | sed -E 's/^v([0-9]+).*/\1/')" -lt 20 ]; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - >/dev/null 2>&1
    apt-get install -y -qq nodejs
fi

log "Ставлю Postfix (только исходящая почта)"
# MAIL_MAILER=sendmail в Laravel вызывает /usr/sbin/sendmail — на Ubuntu эту
# команду предоставляет Postfix. Настраиваем его как send-only спутник:
# не принимает входящую почту, не слушает публичные интерфейсы, только
# инжектит письма наружу через локальный сокет.
if ! command -v sendmail >/dev/null 2>&1 && ! command -v /usr/sbin/sendmail >/dev/null 2>&1; then
    MAIL_DOMAIN="${APP_DOMAIN%% *}"
    [ "$MAIL_DOMAIN" = "_" ] && MAIL_DOMAIN="$(hostname -f 2>/dev/null || hostname)"
    debconf-set-selections <<< "postfix postfix/main_mailer_type select Internet Site"
    debconf-set-selections <<< "postfix postfix/mailname string ${MAIL_DOMAIN}"
    apt-get install -y -qq postfix
    postconf -e "inet_interfaces = loopback-only"
    postconf -e "mydestination = localhost"
    postconf -e "myhostname = ${MAIL_DOMAIN}"
    systemctl restart postfix
fi

# ---------------------------------------------------------------- PHP version
# Ubuntu 24.04 (noble) несёт PHP 8.3 в штатных репозиториях — отдельный PPA не нужен.
PHP_VER="${PHP_VER:-8.3}"

log "Ставлю PHP ${PHP_VER} и расширения"
# soap обязателен: его требуют sped-nfe, sped-common и sped-gtin
apt-get install -y -qq \
    "php${PHP_VER}-fpm" "php${PHP_VER}-cli" "php${PHP_VER}-soap" "php${PHP_VER}-mysql" \
    "php${PHP_VER}-mbstring" "php${PHP_VER}-xml" "php${PHP_VER}-curl" "php${PHP_VER}-zip" \
    "php${PHP_VER}-bcmath" "php${PHP_VER}-intl" "php${PHP_VER}-gd" \
    git unzip curl

command -v php >/dev/null 2>&1 || die "PHP не установился — проверь вывод apt-get выше."
php -r 'exit(PHP_VERSION_ID < 80200 ? 1 : 0);' || die "PHP $(php -r 'echo PHP_VERSION;') слишком старый — Laravel 12 требует 8.2+."
echo "PHP $(php -r 'echo PHP_VERSION;')"

# У части пакетов post-install триггер включает модуль не сразу,
# поэтому явно доключаем и перечитываем список загруженных расширений.
for ext in soap pdo_mysql mbstring xml curl zip bcmath; do
    phpenmod -v "$PHP_VER" "$ext" >/dev/null 2>&1 || true
done
systemctl restart "php${PHP_VER}-fpm" >/dev/null 2>&1 || true

for ext in soap pdo_mysql mbstring xml curl zip bcmath; do
    php -m | grep -qix "$ext" || warn "Расширение ${ext} не активировалось — проверь вручную."
done

log "Поднимаю лимиты загрузки файлов (админка аддонов грузит ZIP-пакеты)"
cat > "/etc/php/${PHP_VER}/fpm/conf.d/99-uploads.ini" <<'INI'
upload_max_filesize = 120M
post_max_size = 130M
max_execution_time = 120
max_input_time = 120
INI
systemctl restart "php${PHP_VER}-fpm" >/dev/null 2>&1 || true

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
    # .env пишется только при первом запуске и дальше не трогается — он и есть
    # единственный источник истины для пароля, который реально использует приложение.
    # credentials-файл — просто памятка для человека и может отставать от .env
    # (например, если его подправили руками отдельно от .env). Поэтому сначала смотрим
    # в уже существующий .env, и только если его ещё нет — в credentials-файл, и только
    # если нет и его — генерируем новый.
    if [ -z "$DB_PASSWORD" ] && [ -f "${APP_DIR}/.env" ]; then
        DB_PASSWORD="$(grep -m1 '^DB_PASSWORD=' "${APP_DIR}/.env" | cut -d= -f2-)"
    fi
    if [ -z "$DB_PASSWORD" ] && [ -f "$CRED_FILE" ]; then
        DB_PASSWORD="$(grep -m1 '^DB_PASSWORD=' "$CRED_FILE" | cut -d= -f2-)"
    fi
    if [ -z "$DB_PASSWORD" ]; then
        DB_PASSWORD="$(head -c 18 /dev/urandom | base64 | tr -d '/+=' | head -c 24)"
    fi
    # .env использует DB_HOST=127.0.0.1 (TCP), а mysql CLI подключается через unix-сокет
    # (хост 'localhost') — в MariaDB это разные учётки, поэтому заводим обе.
    mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
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
    # php ниже читает эти значения через getenv(), поэтому их обязательно экспортировать.
    # APP_DOMAIN может содержать несколько доменов через пробел (для nginx server_name) —
    # для APP_URL берём только первый, иначе в .env попадёт значение с пробелом.
    PRIMARY_DOMAIN="${APP_DOMAIN%% *}"
    if [ "$PRIMARY_DOMAIN" = "_" ]; then
        APP_URL_VALUE="http://$(hostname -I | awk '{print $1}')"
    else
        APP_URL_VALUE="http://${PRIMARY_DOMAIN}"
    fi
    MAIL_FROM_VALUE="noreply@${PRIMARY_DOMAIN}"
    [ "$PRIMARY_DOMAIN" = "_" ] && MAIL_FROM_VALUE="noreply@localhost"
    export APP_URL_VALUE DB_NAME DB_USER DB_PASSWORD MAIL_FROM_VALUE
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
        // Postfix (send-only) поставлен рядом — sendmail подключает его напрямую,
        // без хоста/порта/логина, в отличие от smtp-драйвера.
        $set("MAIL_MAILER", "sendmail");
        $set("MAIL_FROM_ADDRESS", getenv("MAIL_FROM_VALUE"));
        file_put_contents($f, $s);
    ' 
else
    warn ".env уже есть — оставляю как есть."
fi

# Локаль сайта — украинская. В отличие от остального .env (который трогаем
# только при первом создании), эти два ключа поправляем всегда: сайт уже мог
# быть развёрнут раньше со старым APP_LOCALE=en, и без этого редеплой не
# донесёт до продакшена системную украинскую локализацию.
php -r '
    $f = ".env"; $s = file_get_contents($f);
    $set = function ($k, $v) use (&$s) {
        $line = $k . "=" . $v;
        $s = preg_match("/^#?\s*" . preg_quote($k, "/") . "=.*$/m", $s)
            ? preg_replace("/^#?\s*" . preg_quote($k, "/") . "=.*$/m", $line, $s)
            : $s . "\n" . $line;
    };
    $set("APP_LOCALE", "uk");
    $set("APP_FALLBACK_LOCALE", "en");
    $set("APP_NAME", "\"Monsory Connect\"");
    file_put_contents($f, $s);
'

log "Устанавливаю зависимости (без dev)"
export COMPOSER_ALLOW_SUPERUSER=1
export COMPOSER_MEMORY_LIMIT=-1
composer install --no-dev --optimize-autoloader --no-interaction

if [ -f "${APP_DIR}/package.json" ]; then
    log "Собираю фронтенд (npm)"
    # npm ci чувствителен к памяти на маленьких VPS — если упадёт по OOM,
    # смотри `dmesg | tail`, а не считай это ошибкой конфигурации.
    npm ci --no-audit --no-fund
    npm run build
fi

grep -q '^APP_KEY=base64:' .env || { log "Генерирую APP_KEY"; php artisan key:generate --force; }

log "Применяю миграции"
php artisan migrate --force

log "Сею RBAC (право addons.manage + роль admin)"
# Идемпотентно (firstOrCreate) — не выдаёт роль никому, только гарантирует,
# что само право и роль существуют. Назначение роли конкретному
# пользователю — отдельный ручной шаг.
php artisan db:seed --class="Database\\Seeders\\AddonPermissionsSeeder" --force
php artisan db:seed --class="Database\\Seeders\\SystemPermissionsSeeder" --force

log "Кэширую конфигурацию"
php artisan config:cache
php artisan route:cache
php artisan view:cache

log "Публикую storage-симлинк"
if [ ! -L "${APP_DIR}/public/storage" ]; then
    php artisan storage:link
fi

log "Права на приложение"
# Всё, что artisan создал выше (bootstrap/cache/*.php, public/storage и т.п.),
# ещё принадлежит root — chown должен идти ПОСЛЕДНИМ шагом, иначе следующая
# artisan-команда пересоздаст файл заново от root и www-data опять не сможет
# его прочитать. nginx/php-fpm работают от www-data и должны иметь доступ ко
# всему дереву проекта (vendor/, .env и т.д.), а не только к storage/cache —
# иначе public/index.php не сможет подключить vendor/autoload.php.
chown -R www-data:www-data "${APP_DIR}"
chmod 600 "${APP_DIR}/.env"
find "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" -type d -exec chmod 775 {} \;
# .git теперь тоже принадлежит www-data — без этого следующий `git pull` от root
# откажется работать с "detected dubious ownership in repository".
git config --global --add safe.directory "${APP_DIR}"

# ------------------------------------------------------------------ scheduler
# routes/console.php сейчас ничего не планирует через Schedule::, но команда
# безвредна и стандартна для Laravel — заранее готовим, чтобы не забыть,
# когда появится первая запланированная задача.
log "Настраиваю cron для планировщика"

# В минимальных образах Ubuntu пакета cron нет вовсе, а значит нет и команды
# crontab. При set -e это роняло весь деплой ровно здесь — уже после миграций,
# но ДО настройки nginx и сервиса очереди, то есть сайт оставался недоступным.
if ! command -v crontab >/dev/null 2>&1; then
    apt-get install -y -qq cron
fi
systemctl enable --now cron >/dev/null 2>&1 || true

CRON_LINE="* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1"

# Вторая ловушка на том же месте: у пустого crontab `crontab -l` возвращает 1,
# и grep без совпадений — тоже 1. Внутри подоболочки с set -e это обрывало её
# ДО echo, и в crontab уходил пустой ввод: строка планировщика молча не
# добавлялась, а сам скрипт шёл дальше как ни в чём не бывало.
EXISTING_CRON="$( { crontab -u www-data -l 2>/dev/null || true; } | grep -vF 'schedule:run' || true )"
printf '%s\n%s\n' "$EXISTING_CRON" "$CRON_LINE" | sed '/^[[:space:]]*$/d' | crontab -u www-data - \
    || warn "Не удалось прописать cron планировщика — проверь: crontab -u www-data -l"

# --------------------------------------------------------------- queue worker
log "Настраиваю systemd-сервис очереди"
cat > /etc/systemd/system/laravel-worker.service <<UNIT
[Unit]
Description=Laravel queue worker (${APP_DOMAIN})
After=network.target mariadb.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=${APP_DIR}
# --max-time перезапускает воркер раз в час, чтобы не копилась память на
# долгоживущем PHP-процессе — Restart=always поднимет его обратно сразу.
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable --now laravel-worker >/dev/null 2>&1 || warn "Не удалось запустить laravel-worker — проверь: systemctl status laravel-worker"

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
# При первой установке apache2 занимал порт 80, поэтому nginx мог ни разу не запуститься —
# reload тогда бессилен (сервис не активен), нужен именно start.
if systemctl is-active --quiet nginx; then
    systemctl reload nginx
else
    systemctl enable --now nginx
fi
# Перезапускаем (не reload и не enable --now, который на уже работающий
# сервис ничего не делает) обязательно КАЖДЫЙ прогон: OPcache держит
# скомпилированный код всех .php файлов в разделяемой памяти мастер-процесса,
# и живёт дольше одного деплоя. Единственный более ранний restart в этом
# скрипте — сразу после установки расширений, до того как код вообще
# появился на диске — так что без этого шага воркеры годами обслуживали бы
# запросы байт-кодом от предыдущих деплоев вперемешку с новым.
systemctl enable "php${PHP_VER}-fpm" >/dev/null 2>&1 || true
systemctl restart "php${PHP_VER}-fpm"

log "Готово"
echo "Приложение:  ${APP_DIR}"
echo "Домен:       ${APP_DOMAIN}"
echo "Доступы БД:  ${CRED_FILE}"
