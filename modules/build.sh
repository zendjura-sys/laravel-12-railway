#!/usr/bin/env bash
#
# Пересобирает ZIP-пакеты аддонов из исходников в modules/src.
#
# Зачем это здесь: сами модули ставятся в CMS загрузкой ZIP через админку,
# поэтому раньше их исходники жили только во временной папке сборки. Когда
# хостер закрыл VDS, единственная копия установленных модулей исчезла вместе
# с сервером. Теперь исходники лежат в репозитории, а этот скрипт в любой
# момент собирает из них ZIP заново — терять больше нечего.
#
# Usage:
#   ./modules/build.sh            # собрать все
#   ./modules/build.sh telegram   # собрать один

set -euo pipefail

cd "$(dirname "$0")"
SRC_DIR="src"
DIST_DIR="dist"

# Имя ZIP-файла в формате, который ожидает загрузчик админки:
# (Modules)(версия)Имя.zip
declare -A ZIP_NAME=(
    [reports]='(Modules)(1.0)Reports.zip'
    [progression]='(Modules)(1.0)Progression.zip'
    [member-center]='(Modules)(1.0)MemberCenter.zip'
    [family-goals]='(Modules)(1.0)FamilyGoals.zip'
    [notifications]='(Modules)(1.0)Notifications.zip'
    [telegram]='(Modules)(1.0)TelegramBot.zip'
)

command -v zip >/dev/null 2>&1 || { echo "Нужен zip: apt-get install -y zip" >&2; exit 1; }

mkdir -p "$DIST_DIR"
targets=("${@:-}")
[ -z "${targets[0]}" ] && targets=("${!ZIP_NAME[@]}")

for m in "${targets[@]}"; do
    out="${ZIP_NAME[$m]:-}"
    [ -n "$out" ] || { echo "Неизвестный модуль: $m" >&2; exit 1; }
    [ -f "$SRC_DIR/$m/manifest.json" ] || { echo "Нет manifest.json у $m" >&2; exit 1; }

    rm -f "$DIST_DIR/$out"
    # -j не используем: структура папок (src/, routes/, migrations/) — часть
    # контракта манифеста, установщик ищет файлы именно по этим путям.
    ( cd "$SRC_DIR/$m" && zip -rq "../../$DIST_DIR/$out" . -x '.*' )
    printf '%-16s -> %s (%s)\n' "$m" "$out" "$(du -h "$DIST_DIR/$out" | cut -f1)"
done
