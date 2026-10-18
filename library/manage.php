<?php
$title = 'Управление читателями';
$page  = 'manage';
require __DIR__ . '/inc/header.php';
require_admin();

$stats = [
    'books'   => (int)$pdo->query("SELECT COUNT(*) FROM books")->fetchColumn(),
    'issued'  => (int)$pdo->query("SELECT COUNT(*) FROM loans WHERE status IN ('active','return_pending')")->fetchColumn(),
    'overdue' => (int)$pdo->query("SELECT COUNT(*) FROM loans WHERE status='active' AND due_at < CURDATE()")->fetchColumn(),
    'pending' => (int)$pdo->query("SELECT COUNT(*) FROM requests WHERE status='pending'")->fetchColumn(),
];

$readers = $pdo->query(
    "SELECT id, full_name, email, card_number FROM users WHERE role='reader' ORDER BY full_name"
)->fetchAll();

$loanRows = $pdo->query(
    "SELECT l.*, b.title, a.name AS author
     FROM loans l
     JOIN books b   ON b.id = l.book_id
     JOIN authors a ON a.id = b.author_id
     WHERE l.status IN ('active','return_pending')
     ORDER BY (l.status = 'return_pending') DESC, l.due_at"
)->fetchAll();

$loansByUser = [];
foreach ($loanRows as $row) {
    $loansByUser[(int)$row['user_id']][] = $row;
}

/* Активные заявки на выдачу */
$requests = $pdo->query(
    "SELECT r.id, r.created_at,
            u.full_name, u.card_number, u.id AS user_id,
            b.title, a.name AS author,
            b.id AS book_id, b.available_copies
     FROM requests r
     JOIN users u   ON u.id = r.user_id
     JOIN books b   ON b.id = r.book_id
     JOIN authors a ON a.id = b.author_id
     WHERE r.status = 'pending'
     ORDER BY r.created_at"
)->fetchAll();

$availableBooks = $pdo->query(
    "SELECT b.id, b.title, a.name AS author
     FROM books b JOIN authors a ON a.id = b.author_id
     WHERE b.available_copies > 0 ORDER BY b.title"
)->fetchAll();
?>

<div class="stats">
  <div class="stat">
    <span class="stat__label">Всего книг</span>
    <span class="stat__value"><?= $stats['books'] ?></span>
  </div>
  <div class="stat stat--violet">
    <span class="stat__label">Выдано</span>
    <span class="stat__value"><?= $stats['issued'] ?></span>
  </div>
  <div class="stat stat--red">
    <span class="stat__label">Просрочено</span>
    <span class="stat__value"><?= $stats['overdue'] ?></span>
  </div>
</div>

<!-- ================= ЗАЯВКИ НА ВЫДАЧУ ================= -->
<section class="panel">
  <h2 class="panel__title">
    📨 Заявки на выдачу
    <?php if ($stats['pending'] > 0): ?>
      <span class="pill pill--violet" style="margin-left:8px"><?= $stats['pending'] ?></span>
    <?php endif; ?>
  </h2>
  <p class="muted">Подтвердите выдачу книги читателю или отклоните заявку</p>

  <?php if (!$requests): ?>
    <p class="empty">Активных заявок нет.</p>
  <?php else: ?>
    <div class="loan-list">
      <?php foreach ($requests as $r): $avail = (int)$r['available_copies'] > 0; ?>
        <div class="loan loan--request">
          <div class="book__cover book__cover--sm"
               style="background:<?= cover_gradient((int)$r['book_id']) ?>"><?= book_icon() ?></div>

          <div class="loan__body">
            <h3><?= e($r['title']) ?></h3>
            <p class="muted"><?= e($r['author']) ?></p>
            <p class="loan__dates">
              <span>👤 <?= e($r['full_name']) ?> · Билет №<?= e($r['card_number']) ?></span>
              <span>📅 <?= ru_date($r['created_at']) ?></span>
            </p>
          </div>

          <div class="loan__actions">
            <?php if ($avail): ?>
              <button class="btn btn--primary js-issue-request"
                      data-request="<?= (int)$r['id'] ?>"
                      data-user="<?= (int)$r['user_id'] ?>"
                      data-book="<?= (int)$r['book_id'] ?>">
                📕 Выдать
              </button>
            <?php else: ?>
              <span class="pill pill--green">Выдана</span>
            <?php endif; ?>

            <button class="btn btn--ghost js-reject-request"
                    data-request="<?= (int)$r['id'] ?>"
                    data-title="<?= e($r['title']) ?>"
                    data-reader="<?= e($r['full_name']) ?>">
              ✕ Отклонить
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<!-- ================= УПРАВЛЕНИЕ ЧИТАТЕЛЯМИ ================= -->
<section class="panel">
  <h2 class="panel__title">👥 Управление читателями</h2>
  <p class="muted">Выдавайте, принимайте и продлевайте книги</p>

  <?php foreach ($readers as $rd): ?>
    <div class="reader">
      <div class="reader__head">
        <span class="avatar avatar--lg"><?= e(mb_substr($rd['full_name'], 0, 1)) ?></span>
        <div class="reader__info">
          <b><?= e($rd['full_name']) ?></b>
          <small>Билет №<?= e($rd['card_number']) ?></small>
        </div>
        <button class="btn btn--ghost js-issue"
                data-user="<?= (int)$rd['id'] ?>">
          📕 Выдать
        </button>
      </div>

      <p class="reader__caption">АКТИВНЫЕ КНИГИ</p>

      <?php $myLoans = $loansByUser[(int)$rd['id']] ?? []; ?>
      <?php if (!$myLoans): ?>
        <p class="muted small">Нет активных книг</p>
      <?php else: ?>
        <?php foreach ($myLoans as $l):
            $od   = overdue_days($l['due_at']);
            $back = $l['status'] === 'return_pending'; ?>
          <div class="loan loan--compact <?= $back ? 'loan--return' : '' ?>">
            <div class="book__cover book__cover--xs"
                 style="background:<?= cover_gradient((int)$l['book_id']) ?>"><?= book_icon() ?></div>
            <div class="loan__body">
              <b><?= e($l['title']) ?></b>
              <small class="<?= $back ? 'text-green' : ($od ? 'text-red' : 'muted') ?>">
                <?php if ($back): ?>
                  ↩ Ожидает приёма администратором
                <?php else: ?>
                  до <?= ru_date($l['due_at']) ?><?= $od ? " · просрочено $od дн." : '' ?>
                <?php endif; ?>
              </small>
            </div>

            <?php if ($back): ?>
              <button class="icon-btn icon-btn--green js-accept is-pulsing"
                      data-loan="<?= (int)$l['id'] ?>"
                      data-title="<?= e($l['title']) ?>"
                      data-user="<?= e($rd['full_name']) ?>"
                      title="Принять возврат">✓</button>
            <?php else: ?>
              <button class="icon-btn js-extend"
                      data-loan="<?= (int)$l['id'] ?>"
                      data-title="<?= e($l['title']) ?>"
                      data-user="<?= e($rd['full_name']) ?>"
                      title="Продлить срок">🕒</button>
              <button class="icon-btn icon-btn--green js-accept"
                      data-loan="<?= (int)$l['id'] ?>"
                      data-title="<?= e($l['title']) ?>"
                      data-user="<?= e($rd['full_name']) ?>"
                      title="Принять книгу">✓</button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>

<!-- ================= МОДАЛКА «Выдать книгу» ================= -->
<div class="modal" id="issueModal">
  <div class="modal__backdrop" data-close></div>
  <div class="modal__window">
    <button class="modal__close" data-close>&times;</button>
    <h3 class="modal__title">📕 Выдать книгу: <span id="issueUser"></span></h3>
    <p class="muted">Выберите книгу из доступных для выдачи</p>

    <?php if (!$availableBooks): ?>
      <p class="empty">Нет доступных книг.</p>
    <?php else: ?>
      <div class="issue-grid">
        <?php foreach ($availableBooks as $b): ?>
          <button class="issue-item js-pick-book"
                  data-id="<?= (int)$b['id'] ?>">
            <span class="book__cover book__cover--xs"
                  style="background:<?= cover_gradient((int)$b['id']) ?>"><?= book_icon() ?></span>
            <span>
              <b><?= e($b['title']) ?></b>
              <small class="muted"><?= e($b['author']) ?></small>
            </span>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ================= МОДАЛКА «Продлить срок» ================= -->
<div class="modal" id="extendModal">
  <div class="modal__backdrop" data-close></div>
  <div class="modal__window modal__window--sm">
    <button class="modal__close" data-close>&times;</button>
    <h3 class="modal__title">🕒 Продлить срок</h3>
    <p class="muted">Продление срока возврата для <span id="extUser"></span></p>
    <div class="mini-book">
      <span class="book__cover book__cover--xs" style="background:#8B5CF6"><?= book_icon() ?></span>
      <b id="extTitle"></b>
    </div>
    <label class="field">
      <span>Продлить на (дней)</span>
      <select id="extDays">
        <option value="7">7</option>
        <option value="14" selected>14</option>
        <option value="30">30</option>
      </select>
    </label>
    <div class="modal__actions">
      <button class="btn btn--ghost" data-close>Отмена</button>
      <button class="btn btn--primary" id="extConfirm">🕒 Продлить</button>
    </div>
  </div>
</div>

<!-- ================= МОДАЛКА «Принять книгу» ================= -->
<div class="modal" id="acceptModal">
  <div class="modal__backdrop" data-close></div>
  <div class="modal__window modal__window--sm">
    <button class="modal__close" data-close>&times;</button>
    <h3 class="modal__title modal__title--green">✓ Принять книгу</h3>
    <p class="muted">Подтвердите приём книги от <span id="accUser"></span></p>
    <div class="mini-book">
      <span class="book__cover book__cover--xs" style="background:#22C55E"><?= book_icon() ?></span>
      <b id="accTitle"></b>
    </div>
    <div class="modal__actions">
      <button class="btn btn--ghost" data-close>Отмена</button>
      <button class="btn btn--success" id="accConfirm">✓ Принять</button>
    </div>
  </div>
</div>

<!-- ================= МОДАЛКА «Отклонить заявку» ================= -->
<div class="modal" id="rejectModal">
  <div class="modal__backdrop" data-close></div>
  <div class="modal__window modal__window--sm">
    <button class="modal__close" data-close>&times;</button>
    <h3 class="modal__title">✕ Отклонить заявку?</h3>
    <p class="muted">Читатель <span id="rejReader"></span> не получит книгу
       «<span id="rejTitle"></span>»</p>
    <div class="modal__actions">
      <button class="btn btn--ghost" data-close>Отмена</button>
      <button class="btn btn--danger" id="rejConfirm">✕ Отклонить</button>
    </div>
  </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>