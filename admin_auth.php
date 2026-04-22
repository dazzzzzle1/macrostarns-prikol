<?php
declare(strict_types=1);

// Endpoint для проверки пароля из модального окна.
// Возвращает JSON и при успешной проверке ставит сессию.

session_start();

header('Content-Type: application/json; charset=utf-8');

$ADMIN_PASS = getenv('ADMIN_PASS');
if ($ADMIN_PASS === false || trim($ADMIN_PASS) === '') {
  // ВАЖНО: поменяйте пароль на свой.
  $ADMIN_PASS = 'admin123';
}

function input_json(): array {
  $raw = file_get_contents('php://input');
  if ($raw === false || trim($raw) === '') return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function respond(array $payload, int $statusCode = 200): void {
  http_response_code($statusCode);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

$data = input_json();
$pass = '';
if (isset($data['password'])) {
  $pass = (string)$data['password'];
} elseif (isset($_POST['password'])) {
  $pass = (string)($_POST['password']);
}

$pass = trim($pass);
if ($pass === '') {
  respond(['ok' => false, 'message' => 'Пароль не задан.'], 400);
}

if (!hash_equals((string)$ADMIN_PASS, $pass)) {
  respond(['ok' => false, 'message' => 'Неверный пароль.'], 403);
}

$_SESSION['admin_authed'] = true;
respond(['ok' => true, 'message' => 'OK'], 200);

