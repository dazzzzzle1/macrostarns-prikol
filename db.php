<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'mb_compat.php';

/**
 * SQLite-хранилище заявок. Файл: data/submissions.sqlite
 * При первом запуске создаёт таблицу и импортирует legacy data/submissions.jsonl (если есть).
 */

function db_path(): string {
  return __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'submissions.sqlite';
}

function db_jsonl_path(): string {
  return __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'submissions.jsonl';
}

function db_pdo(): PDO {
  $dir = dirname(db_path());
  if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
  }

  $pdo = new PDO('sqlite:' . db_path(), null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  $pdo->exec('PRAGMA foreign_keys = ON;');
  return $pdo;
}

function db_init(PDO $pdo): void {
  $pdo->exec(
    'CREATE TABLE IF NOT EXISTS submissions (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      uid TEXT NOT NULL UNIQUE,
      created_at TEXT NOT NULL,
      name TEXT NOT NULL,
      phone TEXT NOT NULL,
      brand TEXT NOT NULL,
      message TEXT NOT NULL,
      meeting_date TEXT NOT NULL DEFAULT \'\',
      meeting_time TEXT NOT NULL DEFAULT \'\',
      status TEXT NOT NULL DEFAULT \'new\'
        CHECK (status IN (\'new\', \'in_progress\', \'done\'))
    );'
  );
  $pdo->exec('CREATE INDEX IF NOT EXISTS idx_submissions_created ON submissions (created_at);');
  $pdo->exec('CREATE INDEX IF NOT EXISTS idx_submissions_status ON submissions (status);');
}

/** Таблицы, созданные до появления полей встречи — добавляем столбцы. */
function db_migrate_schema(PDO $pdo): void {
  $info = $pdo->query('PRAGMA table_info(submissions)');
  if ($info === false) {
    return;
  }
  $rows = $info->fetchAll(PDO::FETCH_ASSOC);
  $names = [];
  foreach ($rows as $row) {
    if (isset($row['name'])) {
      $names[] = (string) $row['name'];
    }
  }
  if (!in_array('meeting_date', $names, true)) {
    $pdo->exec('ALTER TABLE submissions ADD COLUMN meeting_date TEXT NOT NULL DEFAULT \'\'');
  }
  if (!in_array('meeting_time', $names, true)) {
    $pdo->exec('ALTER TABLE submissions ADD COLUMN meeting_time TEXT NOT NULL DEFAULT \'\'');
  }
}

function db_migrate_jsonl(PDO $pdo): void {
  $path = db_jsonl_path();
  if (!is_file($path)) {
    return;
  }

  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (!is_array($lines)) {
    return;
  }

  $ins = $pdo->prepare(
    'INSERT OR IGNORE INTO submissions (uid, created_at, name, phone, brand, message, meeting_date, meeting_time, status)
     VALUES (:uid, :created_at, :name, :phone, :brand, :message, :meeting_date, :meeting_time, :status)'
  );

  foreach ($lines as $line) {
    $line = trim((string)$line);
    if ($line === '') {
      continue;
    }
    $data = json_decode($line, true);
    if (!is_array($data)) {
      continue;
    }

    $uid = isset($data['uid']) && is_string($data['uid']) && trim($data['uid']) !== ''
      ? trim((string)$data['uid'])
      : substr(hash('sha256', $line), 0, 16);

    $ts = isset($data['ts']) ? (string)$data['ts'] : date('c');
    if ($ts === '') {
      $ts = date('c');
    }

    $status = isset($data['status']) && is_string($data['status']) ? $data['status'] : 'new';
    if (!in_array($status, ['new', 'in_progress', 'done'], true)) {
      $status = 'new';
    }

    $ins->execute([
      ':uid' => $uid,
      ':created_at' => $ts,
      ':name' => (string)($data['name'] ?? ''),
      ':phone' => (string)($data['phone'] ?? ''),
      ':brand' => (string)($data['brand'] ?? ''),
      ':message' => (string)($data['message'] ?? ''),
      ':meeting_date' => (string)($data['meeting_date'] ?? ''),
      ':meeting_time' => (string)($data['meeting_time'] ?? ''),
      ':status' => $status,
    ]);
  }
}

function db_ensure(): PDO {
  $pdo = db_pdo();
  db_init($pdo);
  db_migrate_schema($pdo);
  db_migrate_jsonl($pdo);
  return $pdo;
}

function db_ts_epoch(string $ts): int {
  $t = strtotime($ts);
  return $t === false ? 0 : (int)$t;
}

/**
 * @return list<array{uid:string, ts:string, tsEpoch:int, data:array<string,mixed>}>
 */
function db_read_all(PDO $pdo): array {
  $stmt = $pdo->query(
    'SELECT uid, created_at AS ts, name, phone, brand, message, meeting_date, meeting_time, status
     FROM submissions
     ORDER BY datetime(created_at) DESC, id DESC'
  );
  $rows = $stmt->fetchAll();
  $items = [];
  foreach ($rows as $r) {
    $ts = (string)($r['ts'] ?? '');
    $items[] = [
      'uid' => (string)($r['uid'] ?? ''),
      'ts' => $ts,
      'tsEpoch' => db_ts_epoch($ts),
      'data' => [
        'uid' => (string)($r['uid'] ?? ''),
        'ts' => $ts,
        'name' => (string)($r['name'] ?? ''),
        'phone' => (string)($r['phone'] ?? ''),
        'brand' => (string)($r['brand'] ?? ''),
        'message' => (string)($r['message'] ?? ''),
        'meeting_date' => (string)($r['meeting_date'] ?? ''),
        'meeting_time' => (string)($r['meeting_time'] ?? ''),
        'status' => (string)($r['status'] ?? 'new'),
      ],
    ];
  }
  return $items;
}

/**
 * @param list<array{uid:string, ts:string, tsEpoch:int, data:array<string,mixed>}> $items
 * @return list<array{uid:string, ts:string, tsEpoch:int, data:array<string,mixed>}>
 */
function db_filter_items(array $items, array $filters): array {
  $q = trim((string)($filters['q'] ?? ''));
  $from = trim((string)($filters['from'] ?? ''));
  $to = trim((string)($filters['to'] ?? ''));
  $showDone = isset($filters['show_done']) && (string) $filters['show_done'] === '1';

  $qLow = mb_strtolower($q);
  $fromEpoch = $from !== '' ? db_ts_epoch($from . ' 00:00:00') : null;
  $toEpoch = $to !== '' ? db_ts_epoch($to . ' 23:59:59') : null;

  $out = [];
  foreach ($items as $it) {
    $d = $it['data'];
    if (!$showDone && (($d['status'] ?? '') === 'done')) {
      continue;
    }

    if ($qLow !== '') {
      $hay = mb_strtolower(
        ($d['name'] ?? '') . ' ' .
        ($d['phone'] ?? '') . ' ' .
        ($d['brand'] ?? '') . ' ' .
        ($d['message'] ?? '') . ' ' .
        ($d['meeting_date'] ?? '') . ' ' .
        ($d['meeting_time'] ?? '') . ' ' .
        ($d['status'] ?? '')
      );
      if (mb_strpos($hay, $qLow) === false) {
        continue;
      }
    }

    if ($fromEpoch !== null && $it['tsEpoch'] < $fromEpoch) {
      continue;
    }
    if ($toEpoch !== null && $it['tsEpoch'] > $toEpoch) {
      continue;
    }

    $out[] = $it;
  }

  return $out;
}

function db_insert_submission(PDO $pdo, array $record): void {
  $stmt = $pdo->prepare(
    'INSERT INTO submissions (uid, created_at, name, phone, brand, message, meeting_date, meeting_time, status)
     VALUES (:uid, :created_at, :name, :phone, :brand, :message, :meeting_date, :meeting_time, \'new\')'
  );
  $stmt->execute([
    ':uid' => $record['uid'],
    ':created_at' => $record['ts'],
    ':name' => $record['name'],
    ':phone' => $record['phone'],
    ':brand' => $record['brand'],
    ':message' => $record['message'],
    ':meeting_date' => $record['meeting_date'] ?? '',
    ':meeting_time' => $record['meeting_time'] ?? '',
  ]);
}

function db_delete_by_uid(PDO $pdo, string $uid): int {
  $stmt = $pdo->prepare('DELETE FROM submissions WHERE uid = :uid');
  $stmt->execute([':uid' => $uid]);
  return $stmt->rowCount();
}

function db_clear_all(PDO $pdo): void {
  $pdo->exec('DELETE FROM submissions;');
}

function db_update_status(PDO $pdo, string $uid, string $status): int {
  if (!in_array($status, ['new', 'in_progress', 'done'], true)) {
    return 0;
  }
  $stmt = $pdo->prepare('UPDATE submissions SET status = :st WHERE uid = :uid');
  $stmt->execute([':st' => $status, ':uid' => $uid]);
  return $stmt->rowCount();
}
