<?php
declare(strict_types=1);

/**
 * Если в PHP нет mbstring (часто на минимальной установке php-cli),
 * подставляем простые UTF-8-ограниченные заглушки, чтобы не было HTTP 500.
 * Для корректного поиска по-русски лучше: sudo apt install php-mbstring
 */
if (extension_loaded('mbstring')) {
  return;
}

if (!function_exists('mb_strlen')) {
  function mb_strlen($string, $encoding = null): int {
    return strlen((string) $string);
  }
}

if (!function_exists('mb_substr')) {
  function mb_substr($string, $start, $length = null, $encoding = null): string {
    $s = (string) $string;
    if ($length === null) {
      return substr($s, (int) $start);
    }
    return substr($s, (int) $start, (int) $length);
  }
}

if (!function_exists('mb_strtolower')) {
  function mb_strtolower($string, $encoding = null): string {
    return strtolower((string) $string);
  }
}

if (!function_exists('mb_strpos')) {
  /**
   * @return int|false
   */
  function mb_strpos($haystack, $needle, $offset = 0, $encoding = null) {
    return strpos((string) $haystack, (string) $needle, (int) $offset);
  }
}
