<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$user    = current_user();
$overdue = overdue_count($pdo);
$pending = pending_requests_count($pdo);
$returns = pending_returns_count($pdo);
$page    = $page ?? '';

$tabs = [
    'catalog' => ['index.php',   'Каталог',   '📖'],
    'search'  => ['search.php',  'Поиск',     '🔍'],
    'mybooks' => ['mybooks.php', 'Мои книги', '📚'],
];
if (is_admin()) {
    $tabs['manage'] = ['manage.php', 'Управление', '⚙️'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Библиотека') ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body data-logged="<?= is_logged() ? '1' : '0' ?>">

<header class="topbar">
  <div class="container topbar__inner">
    <a class="logo" href="index.php">
      <span class="logo__icon"><?= book_icon() ?></span>
      <span class="logo__text">
        <b>Библиотека</b>
        <small>Система управления</small>
      </span>
    </a>

    <div class="topbar__right">
      <?php if ($overdue > 0): ?>
        <span class="pill pill--red" title="Просроченные книги">
          ⏱ Просрочено <?= $overdue ?> дн.
        </span>
      <?php endif; ?>

      <?php if ($pending > 0): ?>
        <span class="pill pill--violet" title="Активные заявки">
          📨 Заявок: <?= $pending ?>
        </span>
      <?php endif; ?>

      <?php if (is_admin() && $returns > 0): ?>
        <span class="pill pill--green" title="Книги, ожидающие приёма">
          ↩ К возврату: <?= $returns ?>
        </span>
      <?php endif; ?>

      <?php if ($user): ?>
        <span class="user-chip">
          <span class="avatar"><?= e(mb_substr($user['full_name'], 0, 1)) ?></span>
          <span class="user-chip__name"><?= e($user['full_name']) ?></span>
        </span>
        <a class="btn btn--ghost btn--sm" href="logout.php">Выход</a>
      <?php else: ?>
        <a class="btn btn--ghost btn--sm" href="login.php">Вход</a>
        <a class="btn btn--primary btn--sm" href="register.php">Регистрация</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<?php if (is_logged()): ?>
<nav class="tabs">
  <div class="container tabs__inner">
    <?php foreach ($tabs as $key => [$href, $label, $icon]): ?>
      <a class="tab <?= $page === $key ? 'is-active' : '' ?>" href="<?= $href ?>">
        <span class="tab__icon"><?= $icon ?></span>
        <span class="tab__label"><?= $label ?></span>
        <?php if ($key === 'manage' && ($pending + $returns) > 0): ?>
          <span class="tab__badge"><?= $pending + $returns ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</nav>
<?php endif; ?>

<main class="container">