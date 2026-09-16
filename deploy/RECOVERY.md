# Восстановление сайта на новом сервере

Этот файл появился после того, как хостер закрыл VDS и сайт исчез вместе с
машиной. Здесь записано, что переживает потерю сервера, что нет, и как поднять
всё заново с нуля.

## Что переживает потерю сервера

Всё это лежит в git и восстанавливается одним `git clone`:

- код приложения (Laravel + Vue), вся вёрстка и дизайн;
- `deploy/setup-vps.sh` — разворачивает nginx + PHP-FPM + MariaDB + очередь;
- `deploy/systemd/` — юниты для кнопки «Задеплоїти» в админке;
- `modules/src/` — исходники всех шести аддонов;
- `modules/dist/` — готовые ZIP-пакеты для загрузки через админку
  (пересобираются из исходников: `./modules/build.sh`).

## Что теряется вместе с сервером

Этого в репозитории нет и быть не должно — восстанавливается руками:

| Что | Где было | Как вернуть |
|---|---|---|
| `.env` | `/var/www/laravel/.env` | скрипт создаёт заново; секреты вписать руками (см. ниже) |
| База MariaDB | сервер | пользователи, роли, настройки, данные модулей — только из бэкапа |
| SSL-сертификат | `/etc/letsencrypt` | certbot выпускает новый |
| Регистрация webhook в Telegram | у Telegram | кнопка «Встановити webhook» в админке |

**База данных не восстанавливается ниоткуда, если не было бэкапа.** Учётки
участников, роли, настройки и данные модулей придётся завести заново. Поэтому
сразу после подъёма сайта настройте бэкап (последний раздел).

## Порядок подъёма на чистом сервере

Требования: Ubuntu 24.04, root по SSH, панель управления **не установлена**
(cPanel/Plesk/HestiaCP поднимут свой стек и подерутся со скриптом).

### 1. DNS

A-запись домена → новый IP. Проверить, что уже применилось:

```bash
dig +short monsory.net
```

Пока домен смотрит на старый IP, HTTPS выпустить не получится.

### 2. Код

```bash
apt-get update && apt-get install -y git
mkdir -p /var/www && cd /var/www
git clone https://github.com/zendjura-sys/laravel-12-railway.git laravel
cd laravel
git checkout claude/vds-connection-3piazs
```

### 3. Разворачивание

```bash
APP_DOMAIN="monsory.net www.monsory.net" ./deploy/setup-vps.sh
```

Скрипт идемпотентный — повторный запуск ничего не сломает. Он ставит пакеты,
заводит базу, пишет `.env`, генерирует `APP_KEY`, гоняет миграции, сеет роли,
собирает фронтенд, настраивает nginx, cron и systemd-сервис очереди.
Пароль к базе останется в `/root/laravel-deploy-credentials.txt`.

### 4. HTTPS

Скрипт поднимает только HTTP — сертификат выпускается отдельно, уже после
того, как домен реально смотрит на этот сервер:

```bash
apt-get install -y certbot python3-certbot-nginx
certbot --nginx -d monsory.net -d www.monsory.net
```

Certbot сам допишет 443 в конфиг nginx и настроит автопродление. После этого
поправить в `.env` схему, иначе ссылки в письмах уйдут с `http://`:

```bash
sed -i 's|^APP_URL=.*|APP_URL=https://monsory.net|' .env
php artisan config:cache
```

### 5. Первый администратор

```bash
php artisan tinker --execute="
\$u = App\Models\User::create([
  'name' => 'Admin',
  'email' => 'admin@monsory.net',
  'password' => Illuminate\Support\Facades\Hash::make('ЗАМЕНИ_ПАРОЛЬ'),
]);
\$u->forceFill(['email_verified_at' => now()])->save();
\$u->assignRole('admin');
"
```

### 6. Модули

Через админку (Аддони → загрузка ZIP) залить файлы из `modules/dist/` и для
каждого нажать «Активувати», затем «Міграції». Порядок важен: `Reports`
первым — на его события подписаны `Progression`, `FamilyGoals` и
`Notifications`.

### 7. Почта

Скрипт ставит Postfix и включает `MAIL_MAILER=sendmail`, но на свежем IP
письма почти наверняка не дойдут: провайдеры блокируют исходящий 25 порт, а
без SPF/DKIM/PTR письма летят в спам. Рабочая схема — внешний SMTP-релей:

```bash
sed -i \
  -e 's|^MAIL_MAILER=.*|MAIL_MAILER=smtp|' \
  -e 's|^MAIL_HOST=.*|MAIL_HOST=smtp.gmail.com|' \
  -e 's|^MAIL_PORT=.*|MAIL_PORT=587|' \
  -e 's|^MAIL_USERNAME=.*|MAIL_USERNAME=admin.monsory@gmail.com|' \
  -e 's|^MAIL_PASSWORD=.*|MAIL_PASSWORD="app-пароль"|' \
  -e 's|^MAIL_FROM_ADDRESS=.*|MAIL_FROM_ADDRESS="admin.monsory@gmail.com"|' \
  .env
grep -q '^MAIL_ENCRYPTION=' .env || echo 'MAIL_ENCRYPTION=tls' >> .env
php artisan config:cache
systemctl restart php8.3-fpm
```

`MAIL_PASSWORD` — это app-пароль Google (нужна включённая двухфакторка), не
пароль от аккаунта: обычный пароль Gmail SMTP не принимает.

Перезапуск php-fpm обязателен: OPcache держит скомпилированный
`bootstrap/cache/config.php` в памяти мастер-процесса, и без рестарта сайт
продолжит слать почту по старым настройкам, хотя `php artisan tinker` из
консоли уже будет работать правильно. Именно на этом в прошлый раз потерялось
полчаса.

Проверка:

```bash
php artisan tinker --execute="Illuminate\Support\Facades\Mail::raw('test', fn(\$m) => \$m->to('admin.monsory@gmail.com')->subject('Monsory test'));"
```

### 8. Telegram

В админке (Налаштування → Telegram) вписать `telegram_bot_token` и
`telegram_bot_username` (`monsory_bot`), затем в разделе «Telegram-бот» нажать
«Встановити webhook». Старая регистрация указывала на мёртвый сервер, Telegram
сам её не обновит.

### 9. Кнопка «Задеплоїти» в админке

Однократная настройка systemd-юнитов — см. `deploy/systemd/README.md`.

## Бэкап базы

Без этого следующая потеря сервера снова унесёт всех участников и настройки.
Ежедневный дамп с хранением за две недели:

```bash
mkdir -p /root/backups
cat > /etc/cron.daily/laravel-db-backup <<'SH'
#!/bin/sh
set -e
DB=$(grep -m1 '^DB_DATABASE=' /var/www/laravel/.env | cut -d= -f2-)
OUT="/root/backups/${DB}-$(date +%F).sql.gz"
mysqldump --single-transaction --quick "$DB" | gzip > "$OUT"
find /root/backups -name '*.sql.gz' -mtime +14 -delete
SH
chmod +x /etc/cron.daily/laravel-db-backup
/etc/cron.daily/laravel-db-backup && ls -lh /root/backups
```

Дампы лежат на том же сервере, поэтому при его потере исчезнут вместе с ним —
копию стоит регулярно забирать наружу:

```bash
scp root@monsory.net:/root/backups/*.sql.gz ~/monsory-backups/
```
