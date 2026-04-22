<?php
declare(strict_types=1);

// Админка: вход по паролю, заявки в SQLite, поиск, даты, статусы, удаление, экспорт CSV.

session_start();

require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';

$ADMIN_PASS = getenv('ADMIN_PASS');
if ($ADMIN_PASS === false || trim($ADMIN_PASS) === '') {
  $ADMIN_PASS = 'admin123';
}

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function status_label(string $st): string {
  if ($st === 'in_progress') {
    return 'В работе';
  }
  if ($st === 'done') {
    return 'Готово';
  }
  return 'Новая';
}

function format_submitted_display(string $iso): string {
  $t = strtotime($iso);
  if ($t === false) {
    return $iso;
  }
  return date('d.m.Y · H:i', $t);
}

function format_meeting_date_cell(string $ymd): string {
  if ($ymd === '') {
    return '—';
  }
  if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
    $t = strtotime($ymd . ' 12:00:00');
    return $t === false ? $ymd : date('d.m.Y', $t);
  }
  return $ymd;
}

function format_meeting_time_cell(string $hm): string {
  return $hm === '' ? '—' : $hm;
}

function render_login(): void {
  ?>
  <!doctype html>
  <html lang="ru">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1" />
      <title>Админка МакроТранс</title>
      <link rel="stylesheet" href="style.css" />
    </head>
    <body class="admin-page">
      <main class="container" style="padding: 40px 0;">
        <section class="footer-col" style="max-width: 720px; margin: 0 auto;">
          <h1 class="footer-title" style="font-size: 22px;">Админка МакроТранс</h1>
          <p class="footer-text">Введите пароль для просмотра заявок.</p>

          <form method="post" style="display:flex; flex-direction:column; gap:12px;">
            <label class="field">
              <span class="label-text">Пароль</span>
              <input class="input" type="password" name="password" required />
            </label>
            <button class="btn btn-primary" type="submit">Войти</button>
          </form>
        </section>
      </main>
    </body>
  </html>
  <?php
}

function render_admin(array $items, array $filters, bool $cleared = false, int $deletedCount = 0, int $updatedCount = 0): void {
  $count = count($items);
  $q = (string)($filters['q'] ?? '');
  $from = (string)($filters['from'] ?? '');
  $to = (string)($filters['to'] ?? '');
  $showDone = isset($filters['show_done']) && (string) $filters['show_done'] === '1';
  ?>
  <!doctype html>
  <html lang="ru">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1" />
      <title>Админка МакроТранс | Заявки</title>
      <link rel="stylesheet" href="style.css" />
    </head>
    <body class="admin-page">
      <header class="site-header">
        <div class="container header-inner">
          <div class="brand">
            <span class="brand-name">МакроТранс</span>
          </div>
          <nav class="nav" aria-label="Админ-навигация">
            <a class="nav-link" href="index.html">На сайт</a>
            <a class="nav-link" href="admin.php?export=csv&amp;q=<?= h(urlencode($q)) ?>&amp;from=<?= h(urlencode($from)) ?>&amp;to=<?= h(urlencode($to)) ?><?= $showDone ? '&amp;show_done=1' : '' ?>" download>Скачать CSV</a>
            <a class="nav-link" href="admin.php?logout=1">Выход</a>
          </nav>
        </div>
      </header>

      <main class="container" style="padding: 30px 0 60px;">
        <section class="footer-col admin-panel">
          <h1 class="footer-title admin-panel__title">Заявки с формы</h1>
          <p class="footer-text admin-panel__meta">
            Найдено: <b><?= h((string)$count) ?></b>
            <?php if (!$showDone): ?>
              <span class="admin-hint"> (без статуса «Готово» — они скрыты; включите ниже «Показать выполненные»)</span>
            <?php endif; ?>
            <?= $cleared ? ' — все заявки удалены из базы.' : '' ?>
            <?= $deletedCount > 0 ? ' — удалено записей: ' . h((string)$deletedCount) . '.' : '' ?>
            <?= $updatedCount > 0 ? ' — обновлено статусов: ' . h((string)$updatedCount) . '.' : '' ?>
          </p>

          <form method="get" class="admin-filters">
            <label class="field admin-filters__field">
              <span class="label-text">Поиск</span>
              <input class="input" type="text" name="q" value="<?= h($q) ?>" placeholder="Имя / телефон / марка / статус" />
            </label>

            <label class="field admin-filters__field">
              <span class="label-text">С</span>
              <input class="input" type="date" name="from" value="<?= h($from) ?>" />
            </label>

            <label class="field admin-filters__field">
              <span class="label-text">По</span>
              <input class="input" type="date" name="to" value="<?= h($to) ?>" />
            </label>

            <label class="field admin-filters__checkbox" style="margin:0; flex-direction:row; align-items:center; gap:10px; min-width:220px;">
              <input type="checkbox" name="show_done" value="1" <?= $showDone ? ' checked' : '' ?> />
              <span class="label-text" style="margin:0;">Показать выполненные</span>
            </label>

            <button class="btn btn-primary" type="submit">Применить</button>
            <a class="btn btn-ghost" href="admin.php">Сбросить</a>
          </form>

          <?php if ($count === 0): ?>
            <p class="footer-text">Пока нет сохраненных заявок.</p>
          <?php else: ?>
            <div class="table-wrap admin-table-wrap">
              <table class="price-table admin-table">
                <thead>
                  <tr>
                    <th scope="col">Отправлено</th>
                    <th scope="col">Дата встречи</th>
                    <th scope="col">Время встречи</th>
                    <th scope="col">Имя</th>
                    <th scope="col">Телефон</th>
                    <th scope="col">Марка</th>
                    <th scope="col">Проблема</th>
                    <th scope="col">Статус</th>
                    <th scope="col">Действия</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($items as $it):
                    $r = $it['data'];
                    $uid = (string)($it['uid'] ?? '');
                    $st = (string)($r['status'] ?? 'new');
                    ?>
                    <tr>
                      <td data-label="Отправлено"><?= h(format_submitted_display((string)($r['ts'] ?? ''))) ?></td>
                      <td data-label="Дата встречи"><?= h(format_meeting_date_cell((string)($r['meeting_date'] ?? ''))) ?></td>
                      <td data-label="Время встречи"><?= h(format_meeting_time_cell((string)($r['meeting_time'] ?? ''))) ?></td>
                      <td data-label="Имя"><?= h((string)($r['name'] ?? '')) ?></td>
                      <td data-label="Телефон"><?= h((string)($r['phone'] ?? '')) ?></td>
                      <td data-label="Марка"><?= h((string)($r['brand'] ?? '')) ?></td>
                      <td data-label="Проблема" class="admin-cell-msg"><?= h((string)($r['message'] ?? '')) ?></td>
                      <td data-label="Статус">
                        <form method="post" class="admin-inline-form">
                          <input type="hidden" name="q" value="<?= h($q) ?>" />
                          <input type="hidden" name="from" value="<?= h($from) ?>" />
                          <input type="hidden" name="to" value="<?= h($to) ?>" />
                          <input type="hidden" name="show_done" value="<?= $showDone ? '1' : '' ?>" />
                          <input type="hidden" name="update_status_uid" value="<?= h($uid) ?>" />
                          <select class="input admin-select" name="new_status" aria-label="Статус заявки">
                            <option value="new" <?= $st === 'new' ? ' selected' : '' ?>><?= h(status_label('new')) ?></option>
                            <option value="in_progress" <?= $st === 'in_progress' ? ' selected' : '' ?>><?= h(status_label('in_progress')) ?></option>
                            <option value="done" <?= $st === 'done' ? ' selected' : '' ?>><?= h(status_label('done')) ?></option>
                          </select>
                          <button class="btn btn-ghost admin-btn-sm" type="submit">OK</button>
                        </form>
                      </td>
                      <td data-label="Действия">
                        <form method="post" class="admin-inline-form" onsubmit="return confirm('Удалить эту заявку?');">
                          <input type="hidden" name="delete_uid" value="<?= h($uid) ?>" />
                          <input type="hidden" name="q" value="<?= h($q) ?>" />
                          <input type="hidden" name="from" value="<?= h($from) ?>" />
                          <input type="hidden" name="to" value="<?= h($to) ?>" />
                          <input type="hidden" name="show_done" value="<?= $showDone ? '1' : '' ?>" />
                          <button class="btn btn-danger admin-btn-sm" type="submit">Удалить</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

          <form method="post" class="admin-danger-zone" onsubmit="return confirm('Удалить ВСЕ заявки из базы? Это необратимо.');">
            <input type="hidden" name="clear" value="1" />
            <input type="hidden" name="q" value="<?= h($q) ?>" />
            <input type="hidden" name="from" value="<?= h($from) ?>" />
            <input type="hidden" name="to" value="<?= h($to) ?>" />
            <input type="hidden" name="show_done" value="<?= $showDone ? '1' : '' ?>" />
            <button class="btn btn-danger" type="submit">Очистить базу заявок</button>
          </form>
        </section>
      </main>
    </body>
  </html>
  <?php
}

if (isset($_GET['logout'])) {
  $_SESSION = [];
  session_destroy();
  header('Location: admin.php');
  exit;
}

if (isset($_POST['password'])) {
  $pass = (string)($_POST['password'] ?? '');
  if (hash_equals((string)$ADMIN_PASS, $pass)) {
    $_SESSION['admin_authed'] = true;
    header('Location: admin.php');
    exit;
  }
  unset($_SESSION['admin_authed']);
}

if (empty($_SESSION['admin_authed'])) {
  render_login();
  exit;
}

try {
  $pdo = db_ensure();
} catch (Throwable $e) {
  http_response_code(500);
  echo '<!doctype html><html lang="ru"><head><meta charset="UTF-8"><title>Ошибка</title></head><body style="font-family:sans-serif;padding:24px;">';
  echo '<p>Не удалось открыть базу данных. Установите: <code>sudo apt install php-sqlite3</code> (нужен модуль pdo_sqlite).</p>';
  echo '<p>Если проект на диске Windows (<code>/mnt/d/...</code>), скопируйте папку в домашний каталог Linux — SQLite там иногда даёт сбои.</p></body></html>';
  exit;
}

$filters = [
  'q' => $_GET['q'] ?? ($_POST['q'] ?? ''),
  'from' => $_GET['from'] ?? ($_POST['from'] ?? ''),
  'to' => $_GET['to'] ?? ($_POST['to'] ?? ''),
  'show_done' => $_GET['show_done'] ?? ($_POST['show_done'] ?? ''),
];

$cleared = false;
$deletedCount = 0;
$updatedCount = 0;

function admin_redirect_after_post(array $filters, array $flash = []): void {
  $params = [
    'q' => (string)($filters['q'] ?? ''),
    'from' => (string)($filters['from'] ?? ''),
    'to' => (string)($filters['to'] ?? ''),
    'cleared' => $flash['cleared'] ?? '',
    'deleted' => isset($flash['deleted']) ? (string)(int)$flash['deleted'] : '',
    'updated' => isset($flash['updated']) ? (string)(int)$flash['updated'] : '',
  ];
  if (isset($filters['show_done']) && (string) $filters['show_done'] === '1') {
    $params['show_done'] = '1';
  }
  $q = http_build_query($params);
  header('Location: admin.php?' . $q);
  exit;
}

if (isset($_GET['export']) && (string)$_GET['export'] === 'csv') {
  $items = db_read_all($pdo);
  $items = db_filter_items($items, $filters);

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="macrotrans-zayavki.csv"');
  $out = fopen('php://output', 'w');
  if ($out !== false) {
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Отправлено', 'Дата встречи', 'Время встречи', 'Имя', 'Телефон', 'Марка', 'Проблема', 'Статус'], ';');
    foreach ($items as $it) {
      $r = $it['data'];
      fputcsv($out, [
        format_submitted_display((string)($r['ts'] ?? '')),
        format_meeting_date_cell((string)($r['meeting_date'] ?? '')),
        format_meeting_time_cell((string)($r['meeting_time'] ?? '')),
        (string)($r['name'] ?? ''),
        (string)($r['phone'] ?? ''),
        (string)($r['brand'] ?? ''),
        (string)($r['message'] ?? ''),
        status_label((string)($r['status'] ?? 'new')),
      ], ';');
    }
    fclose($out);
  }
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postFilters = [
    'q' => $_POST['q'] ?? '',
    'from' => $_POST['from'] ?? '',
    'to' => $_POST['to'] ?? '',
    'show_done' => $_POST['show_done'] ?? '',
  ];

  if (isset($_POST['update_status_uid']) && is_string($_POST['update_status_uid']) && trim($_POST['update_status_uid']) !== '') {
    $uid = trim((string)$_POST['update_status_uid']);
    $newSt = isset($_POST['new_status']) ? (string)$_POST['new_status'] : '';
    $n = db_update_status($pdo, $uid, $newSt);
    admin_redirect_after_post($postFilters, ['updated' => $n]);
  }

  if (isset($_POST['delete_uid']) && is_string($_POST['delete_uid']) && trim($_POST['delete_uid']) !== '') {
    $uid = trim((string)$_POST['delete_uid']);
    $n = db_delete_by_uid($pdo, $uid);
    admin_redirect_after_post($postFilters, ['deleted' => $n]);
  }

  if (isset($_POST['clear']) && $_POST['clear'] === '1') {
    db_clear_all($pdo);
    admin_redirect_after_post($postFilters, ['cleared' => '1']);
  }
}

if (isset($_GET['cleared']) && (string)$_GET['cleared'] === '1') {
  $cleared = true;
}
if (isset($_GET['deleted']) && is_numeric($_GET['deleted'])) {
  $deletedCount = (int)$_GET['deleted'];
}
if (isset($_GET['updated']) && is_numeric($_GET['updated'])) {
  $updatedCount = (int)$_GET['updated'];
}

$items = db_read_all($pdo);
$items = db_filter_items($items, $filters);
render_admin($items, $filters, $cleared, $deletedCount, $updatedCount);
