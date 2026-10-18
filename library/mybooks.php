<?php
$title = 'Мои книги';
$page  = 'mybooks';
require __DIR__ . '/inc/header.php';
require_login();

/* Активные выдачи и книги, ожидающие приёма */
$st = $pdo->prepare(
    "SELECT l.*, b.title, a.name AS author
     FROM loans l
     JOIN books b   ON b.id = l.book_id
     JOIN authors a ON a.id = b.author_id
     WHERE l.user_id = ? AND l.status IN ('active','return_pending')
     ORDER BY (l.status = 'return_pending') DESC, l.due_at"
);
$st->execute([$user['id']]);
$loans = $st->fetchAll();

/* Активные заявки */
$st = $pdo->prepare(
    "SELECT r.id, r.created_at, b.id AS book_id, b.title, a.name AS author
     FROM requests r
     JOIN books b   ON b.id = r.book_id
     JOIN authors a ON a.id = b.author_id
     WHERE r.user_id = ? AND r.status = 'pending'
     ORDER BY r.created_at DESC"
);
$st->execute([$user['id']]);
$requests = $st->fetchAll();
?>

<h1 class="page-title">Мои книги</h1>

<?php if ($requests): ?>
<section class="panel">
  <h2 class="panel__title">📨 Мои заявки</h2>
  <p class="muted">Ожидают подтверждения администратором</p>

  <div class="loan-list">
    <?php foreach ($requests as $r): ?>
      <div class="loan loan--request">
        <div class="book__cover book__cover--sm"
             style="background:<?= cover_gradient((int)$r['book_id']) ?>"><?= book_icon() ?></div>

        <div class="loan__body">
          <h3><?= e($r['title']) ?></h3>
          <p class="muted"><?= e($r['author']) ?></p>
          <p class="loan__dates">
            <span>📅 Подана: <?= ru_date($r['created_at']) ?></span>
          </p>
        </div>

        <span class="pill pill--violet">⏳ В обработке</span>

        <button class="btn btn--ghost js-cancel-request"
                data-request="<?= (int)$r['id'] ?>"
                data-title="<?= e($r['title']) ?>">Отменить</button>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="panel">
  <h2 class="panel__title">📚 Выданные книги</h2>
  <p class="muted">Книги, которые сейчас находятся у вас</p>

  <?php if (!$loans): ?>
    <p class="empty">У вас нет выданных книг.</p>
  <?php else: ?>
    <div class="loan-list">
      <?php foreach ($loans as $l):
          $od   = overdue_days($l['due_at']);
          $back = $l['status'] === 'return_pending'; ?>
        <div class="loan <?= $back ? 'loan--return' : ($od ? 'loan--overdue' : '') ?>">
          <div class="book__cover book__cover--sm"
               style="background:<?= cover_gradient((int)$l['book_id']) ?>"><?= book_icon() ?></div>

          <div class="loan__body">
            <h3><?= e($l['title']) ?></h3>
            <p class="muted"><?= e($l['author']) ?></p>
            <p class="loan__dates">
              <span>📅 Взята: <?= ru_date($l['issued_at']) ?></span>
              <span>📅 Возврат до: <?= ru_date($l['due_at']) ?></span>
            </p>
          </div>

          <?php if ($back): ?>
            <span class="pill pill--green">↩ Ожидает приёма</span>
          <?php elseif ($od): ?>
            <span class="pill pill--red">⏱ Просрочено <?= $od ?> дн.</span>
            <button class="btn btn--primary js-return"
                    data-loan="<?= (int)$l['id'] ?>"
                    data-title="<?= e($l['title']) ?>">↩ Вернуть книгу</button>
          <?php else: ?>
            <span class="pill pill--green">✓ В срок</span>
            <button class="btn btn--primary js-return"
                    data-loan="<?= (int)$l['id'] ?>"
                    data-title="<?= e($l['title']) ?>">↩ Вернуть книгу</button>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<!-- Модалка подтверждения возврата -->
<div class="modal" id="returnModal">
  <div class="modal__backdrop" data-close></div>
  <div class="modal__window modal__window--sm">
    <button class="modal__close" data-close>&times;</button>
    <h3 class="modal__title">↩ Вернуть книгу</h3>
    <p class="muted">Сдать книгу «<span id="retTitle"></span>» в библиотеку?
       Администратор подтвердит приём.</p>
    <div class="modal__actions">
      <button class="btn btn--ghost" data-close>Отмена</button>
      <button class="btn btn--primary" id="retConfirm">↩ Вернуть</button>
    </div>
  </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>