#!/usr/bin/env bash
# Запуск сайта в Ubuntu / WSL.
#
# Не обязательно класть проект в ~/papkf — важно запускать скрипт ИЗ папки с сайтом.
# Пример для WSL, если проект на диске D: Windows:
#   cd "/mnt/d/chrome/papkf-20260326T150509Z-1-001/papkf"
#   bash run-ubuntu.sh
#
# Или одной строкой из любого места (подставьте свой путь):
#   bash "/mnt/d/chrome/papkf-20260326T150509Z-1-001/papkf/run-ubuntu.sh"
#
# Если видите ошибки про $'\r' или "set: invalid option" — файл с Windows-переводами строк.
# Исправление в WSL: sed -i 's/\r$//' run-ubuntu.sh
set -e
cd "$(dirname "$0")"

echo "=== МакроТранс — локальный сервер ==="
echo "Папка проекта: $(pwd)"
if ! command -v php >/dev/null 2>&1; then
  echo "PHP не найден. Установите:"
  echo "  sudo apt update && sudo apt install -y php-cli php-sqlite3 php-mbstring"
  exit 1
fi

if ! php -m 2>/dev/null | grep -qi pdo_sqlite; then
  echo "Нет расширения pdo_sqlite (база заявок не заработает)."
  echo "Установите: sudo apt install -y php-sqlite3 php-mbstring"
  echo "Продолжаю запуск сервера..."
fi

echo ""
echo "Откройте в браузере:  http://127.0.0.1:8080/"
echo "Админка: пароль по умолчанию admin123"
echo "Остановка: Ctrl+C"
echo ""

exec php -S 0.0.0.0:8080 router.php
