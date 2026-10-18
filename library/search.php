<?php
$title = 'Расширенный поиск';
$page  = 'search';
require __DIR__ . '/inc/header.php';

$f = [
    'title'  => trim($_GET['title']  ?? ''),
    'author' => trim($_GET['author'] ?? ''),
    'isbn'   => trim($_GET['isbn']   ?? ''),
    'genre'  => trim($_GET['genre']  ?? ''),
];
$searched = isset($_GET['go']);
$books = [];

if ($searched) {
    $sql = "SELECT b.*, a.name AS author, g.name AS genre
            FROM books b
            JOIN authors a ON a.id = b.author_id
            JOIN genres  g ON g.id = b.genre_id
            WHERE 1=1";
    $params = [];
    if ($f['title']  !== '') { $sql .= " AND b.title LIKE ?"; $params[] = '%' . $f['title'] . '%'; }
    if ($f['author'] !== '') { $sql .= " AND a.name  LIKE ?"; $params[] = '%' . $f['author'] . '%'; }
    if ($f['isbn']   !== '') { $sql .= " AND b.isbn  LIKE ?"; $params[] = '%' . $f['isbn'] . '%'; }
    if ($f['genre']  !== '') { $sql .= " AND g.name  LIKE ?"; $params[] = '%' . $f['genre'] . '%'; }
    $sql .= " ORDER BY b.id";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $books = $st->fetchAll();
}
?>

<section class="panel">
  <h1 class="panel__title">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#8B5CF6"
         stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/>
      <path d="m21 21-4.3-4.3"/></svg>
    Расширенный поиск
  </h1>
  <p class="muted">Найдите нужную книгу по различным критериям</p>

  <form method="get" action="search.php" class="grid-2">
    <label class="field">
      <span>Название книги</span>
      <input type="text" name="title" value="<?= e($f['title']) ?>" placeholder="Например: 1984">
    </label>
    <label class="field">
      <span>Автор</span>
      <input type="text" name="author" value="<?= e($f['author']) ?>" placeholder="Например: Оруэлл">
    </label>
    <label class="field">
      <span>ISBN</span>
      <input type="text" name="isbn" value="<?= e($f['isbn']) ?>" placeholder="978-5-17-098825-6">
    </label>
    <label class="field">
      <span>Жанр</span>
      <input type="text" name="genre" value="<?= e($f['genre']) ?>" placeholder="Например: Фэнтези">
    </label>

    <input type="hidden" name="go" value="1">
    <button class="btn btn--primary btn--block grid-2__full" type="submit">
      🔍 Найти книги
    </button>
  </form>
</section>

<?php if ($searched): ?>
  <?php if (!$books): ?>
    <p class="empty">По заданным критериям ничего не найдено.</p>
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
          <div class="book__cover" style="background:<?= cover_gradient((int)$b['id']) ?>"><?= book_icon() ?></div>
          <h3 class="book__title"><?= e($b['title']) ?></h3>
          <p class="book__author"><?= e($b['author']) ?></p>
          <span class="tag"><?= e($b['genre']) ?></span>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>

<!-- то же модальное окно, что и в index.php -->
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