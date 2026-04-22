<?php
declare(strict_types=1);

/**
 * Маршрутизатор для встроенного сервера PHP:
 *   php -S 0.0.0.0:8080 router.php
 * Открывайте http://127.0.0.1:8080/ — подставится index.html.
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = is_string($uri) ? $uri : '/';

if ($uri === '/' || $uri === '') {
  header('Content-Type: text/html; charset=utf-8');
  readfile(__DIR__ . DIRECTORY_SEPARATOR . 'index.html');
  return true;
}

return false;
