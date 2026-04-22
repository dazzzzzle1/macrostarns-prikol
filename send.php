<?php
declare(strict_types=1);

// Обработчик формы заявки: валидация, SQLite, опционально email/Telegram.

require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';

header('Content-Type: application/json; charset=utf-8');

function respond(array $payload, int $statusCode = 200): void {
  http_response_code($statusCode);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

function input_json(): array {
  $raw = file_get_contents('php://input');
  if ($raw === false || trim($raw) === '') return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function sanitize_string($value, int $maxLen = 500): string {
  if (!is_string($value)) return '';
  $value = trim($value);
  $value = strip_tags($value);
  $value = preg_replace('/\s+/', ' ', $value);
  if (mb_strlen($value) > $maxLen) {
    $value = mb_substr($value, 0, $maxLen);
  }
  return $value;
}

function sanitize_phone($value): string {
  $value = sanitize_string($value, 40);
  // Оставляем только цифры
  $digits = preg_replace('/\D+/', '', $value);
  // Допускаем 10 или 11 цифр. Если 11 и первая 7 — оставляем.
  if (strlen($digits) === 11 && str_starts_with($digits, '7')) return $digits;
  if (strlen($digits) === 10) return '7' . $digits;
  return '';
}

$data = input_json();

// Honeypot: поле website должно быть пустым
$botCheck = isset($data['website']) ? trim((string)$data['website']) : '';
if ($botCheck !== '') {
  respond(['ok' => false, 'message' => 'SPAM detected'], 400);
}

$name = sanitize_string($data['name'] ?? '', 80);
$phoneDigits = sanitize_phone($data['phone'] ?? '');
$brand = sanitize_string($data['brand'] ?? '', 120);
$message = sanitize_string($data['message'] ?? '', 1200);
$meetingDate = sanitize_string($data['meeting_date'] ?? '', 12);
$meetingTimeRaw = sanitize_string($data['meeting_time'] ?? '', 12);
// Браузеры иногда отдают время как HH:MM:SS — для БД оставляем HH:MM
$meetingTime = '';
if ($meetingTimeRaw !== '' && preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/', $meetingTimeRaw, $tm)) {
  $meetingTime = $tm[1] . ':' . $tm[2];
}

if ($meetingDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $meetingDate)) {
  $meetingDate = '';
}

// Базовая серверная валидация
if ($name === '' || $phoneDigits === '' || $brand === '' || $message === '' || $meetingDate === '' || $meetingTime === '') {
  respond(['ok' => false, 'message' => 'Заполните все поля: дата и время встречи обязательны.'], 422);
}

// Доп. антиспам: ограничим длину сообщения и наличие подозрительных паттернов
$lowerMsg = mb_strtolower($message);
$spamPatterns = ['viagra', 'casino', 'биткоин', 'крипто', 'free money', 'casino'];
foreach ($spamPatterns as $p) {
  if ($p !== '' && mb_strpos($lowerMsg, mb_strtolower($p)) !== false) {
    respond(['ok' => false, 'message' => 'SPAM detected'], 400);
  }
}

// Имитируем сохранение в БД/отправку на почту:
// Запишем заявку в файл в формате JSONL.
$record = [
  'uid' => bin2hex(random_bytes(8)),
  'ts' => date('c'),
  'name' => $name,
  'phone' => $phoneDigits,
  'brand' => $brand,
  'message' => $message,
  'meeting_date' => $meetingDate,
  'meeting_time' => $meetingTime,
];

try {
  $pdo = db_ensure();
  db_insert_submission($pdo, $record);
} catch (Throwable $e) {
  respond(['ok' => false, 'message' => 'Не удалось сохранить заявку. Попробуйте позже.'], 500);
}

// Опциональные уведомления (включаются через переменные окружения).
// EMAIL_TO — адрес получателя, Telegram_BOT_TOKEN/TELEGRAM_CHAT_ID — данные для отправки.
$EMAIL_TO = getenv('EMAIL_TO') ?: '';
$TELEGRAM_BOT_TOKEN = getenv('TELEGRAM_BOT_TOKEN') ?: '';
$TELEGRAM_CHAT_ID = getenv('TELEGRAM_CHAT_ID') ?: '';

function notify_email(string $to, array $record): void {
  if ($to === '') return;
  $subject = 'Заявка МакроТранс';
  $body = "Новая заявка:\n\n"
    . "Отправлено: {$record['ts']}\n"
    . "Имя: {$record['name']}\n"
    . "Телефон: {$record['phone']}\n"
    . "Марка: {$record['brand']}\n"
    . "Дата встречи: {$record['meeting_date']}\n"
    . "Время встречи: {$record['meeting_time']}\n"
    . "Сообщение: {$record['message']}\n";
  // Пробуем отправку, но ошибки не ломаем основной поток.
  @mail($to, $subject, $body, "Content-Type: text/plain; charset=utf-8\r\n");
}

function notify_telegram(string $botToken, string $chatId, array $record): void {
  if ($botToken === '' || $chatId === '') return;
  $text = "Новая заявка МакроТранс\n"
    . "Отправлено: {$record['ts']}\n"
    . "Имя: {$record['name']}\n"
    . "Телефон: {$record['phone']}\n"
    . "Марка: {$record['brand']}\n"
    . "Встреча: {$record['meeting_date']} {$record['meeting_time']}\n"
    . "Сообщение: {$record['message']}";

  $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
  $payload = json_encode([
    'chat_id' => $chatId,
    'text' => $text
  ], JSON_UNESCAPED_UNICODE);

  // cURL лучше, но если его нет — используем file_get_contents.
  if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $payload,
      CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => 2,
      CURLOPT_TIMEOUT => 5
    ]);
    @curl_exec($ch);
    @curl_close($ch);
  } else {
    $opts = [
      'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json; charset=utf-8\r\n",
        'content' => $payload,
        'timeout' => 5
      ]
    ];
    @file_get_contents($url, false, stream_context_create($opts));
  }
}

notify_email($EMAIL_TO, $record);
notify_telegram($TELEGRAM_BOT_TOKEN, $TELEGRAM_CHAT_ID, $record);

// Здесь можно заменить на реальную отправку email/сохранение в БД по вашему учебному примеру.
respond([
  'ok' => true,
  'message' => 'Заявка успешно отправлена! Мы скоро свяжемся.'
], 200);

