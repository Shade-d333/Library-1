<?php
$title = 'Каталог';
$page  = 'catalog';
require __DIR__ . '/inc/header.php';

$q   = trim($_GET['q'] ?? '');
$sql = "SELECT b.*, a.name AS author, g.name AS genre
        FROM books b
        JOIN authors a ON a.id = b.author_id
        JOIN genres  g ON g.id = b.genre_id";
$params = [];
if ($q !== '') {
    $sql .= " WHERE b.title LIKE ? OR a.name LIKE ? OR g.name LIKE ?";
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$sql .= " ORDER BY b.id";

$st = $pdo->prepare($sql);
$st->execute($params);
$books = $st->fetchAll();
?>

<?php if (is_admin()): ?>
  <div class="page-head">
    <h1 class="page-title">Каталог книг</h1>
    <a class="btn btn--primary" href="add_book.php">＋ Добавить книгу</a>
  </div>
<?php endif; ?>

<form class="searchbar" method="get" action="index.php">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
       stroke-width="2" stroke-linecap="round">
    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
  </svg>
  <input type="search" name="q" value="<?= e($q) ?>"
         placeholder="Найти книгу, автора или жанр…">
</form>

<?php if (!$books): ?>
  <p class="empty">Ничего не найдено.</p>
<?php else: ?>
<div class="books">
  <?php foreach ($books as $b): $available = (int)$b['available_copies'] > 0; ?>
    <article class="book js-book"
             data-id="<?= (int)$b['id'] ?>"
             data-title="<?= e($b['title']) ?>"
             data-author="<?= e($b['author']) ?>"
             data-genre="<?= e($b['genre']) ?>"
             data-isbn="<?= e($b['isbn']) ?>"
             data-desc="<?= e($b['description']) ?>"
             data-available="<?= $available ? 1 : 0 ?>"
             data-gradient="<?= e(cover_gradient((int)$b['id'])) ?>">

      <span class="badge <?= $available ? 'badge--violet' : 'badge--green' ?>">
        <?= $available ? 'Доступна' : 'Выдана' ?>
      </span>

      <div class="book__cover" style="background:<?= cover_gradient((int)$b['id']) ?>">
        <?= book_icon() ?>
      </div>

      <h3 class="book__title"><?= e($b['title']) ?></h3>
      <p class="book__author"><?= e($b['author']) ?></p>
      <span class="tag"><?= e($b['genre']) ?></span>
    </article>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Модальное окно книги -->
<div class="modal" id="bookModal">
  <div class="modal__backdrop" data-close></div>
  <div class="modal__window">
    <button class="modal__close" data-close>&times;</button>

    <div class="modal__head">
      <div class="book__cover book__cover--lg" id="mCover"><?= book_icon() ?></div>
      <div>
        <h3 id="mTitle"></h3>
        <p class="muted" id="mAuthor"></p>
        <div class="modal__tags">
          <span class="pill pill--violet" id="mStatus"></span>
          <span class="tag" id="mGenre"></span>
        </div>
      </div>
    </div>

    <p class="muted" id="mIsbn"></p>
    <p id="mDesc"></p>

    <button class="btn btn--primary btn--block" id="mTake">Взять книгу</button>
    <p class="modal__hint" id="mHint"></p>
  </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>