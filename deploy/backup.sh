#!/bin/sh
#
# Ежедневный бэкап базы И загруженных файлов (storage/app/public —
# аватарки участников, галерея семьи, скрины к отчётам, лого). Раньше
# бэкапилась только база: mysqldump спасает строки БД, но не сами файлы
# на диске — при потере сервера (это уже раз случилось, отсюда и
# deploy/RECOVERY.md) все фото пропали бы снова, даже имея свежий дамп базы.
#
# Устанавливается автоматически через deploy/setup-vps.sh в
# /etc/cron.daily/laravel-backup. Можно запустить и руками для проверки.
set -eu

APP_DIR="${APP_DIR:-/var/www/laravel}"
BACKUP_DIR="${BACKUP_DIR:-/root/backups}"
KEEP_DAYS="${KEEP_DAYS:-14}"
DATE="$(date +%F)"

mkdir -p "$BACKUP_DIR"

DB="$(grep -m1 '^DB_DATABASE=' "${APP_DIR}/.env" | cut -d= -f2-)"

mysqldump --single-transaction --quick "$DB" | gzip > "${BACKUP_DIR}/${DB}-db-${DATE}.sql.gz"

if [ -d "${APP_DIR}/storage/app/public" ]; then
    tar -czf "${BACKUP_DIR}/${DB}-files-${DATE}.tar.gz" -C "${APP_DIR}/storage/app" public
fi

find "$BACKUP_DIR" -name '*.sql.gz' -mtime "+${KEEP_DAYS}" -delete
find "$BACKUP_DIR" -name '*.tar.gz' -mtime "+${KEEP_DAYS}" -delete

# Опционально: копия наружу, если на сервере настроен rclone-remote —
# дампы на этом же сервере пропадут вместе с ним при повторной потере VDS.
# Настройка: rclone config (один раз) + записать имя remote в файл
# /etc/laravel-backup-remote, напр. "gdrive:monsory-backups".
REMOTE_FILE="/etc/laravel-backup-remote"
if [ -f "$REMOTE_FILE" ]; then
    if command -v rclone >/dev/null 2>&1; then
        REMOTE="$(cat "$REMOTE_FILE")"
        rclone copy "$BACKUP_DIR" "$REMOTE" --min-age 1m \
            || echo "[backup] rclone copy не удался — дампы остались только локально" >&2
    else
        echo "[backup] /etc/laravel-backup-remote есть, но rclone не установлен — копия наружу не сделана" >&2
    fi
fi
