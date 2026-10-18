<?php
$title = 'Добавить книгу';
$page  = 'catalog';
require __DIR__ . '/inc/header.php';
require_admin();

$errors = [];
$ok     = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t      = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $genre  = trim($_POST['genre'] ?? '');
    $isbn   = trim($_POST['isbn'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $copies = max(1, (int)($_POST['copies'] ?? 1));

    if ($t === '')      $errors[] = 'Укажите название книги.';
    if ($author === '') $errors[] = 'Укажите автора.';
    if ($genre === '')  $errors[] = 'Укажите жанр.';

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("INSERT IGNORE INTO authors (name) VALUES (?)");
            $st->execute([$author]);
            $st = $pdo->prepare("SELECT id FROM authors WHERE name = ?");
            $st->execute([$author]);
            $authorId = (int)$st->fetchColumn();

            $st = $pdo->prepare("INSERT IGNORE INTO genres (name) VALUES (?)");
            $st->execute([$genre]);
            $st = $pdo->prepare("SELECT id FROM genres WHERE name = ?");
            $st->execute([$genre]);
            $genreId = (int)$st->fetchColumn();

            $st = $pdo->prepare(
                "INSERT INTO books (title, author_id, genre_id, isbn, description, total_copies, available_copies)
                 VALUES (?,?,?,?,?,?,?)"
            );
            $st->execute([$t, $authorId, $genreId, $isbn, $desc, $copies, $copies]);
            $pdo->commit();
            $ok = true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Не удалось сохранить книгу.';
        }
    }
}
?>

<div class="page-head">
  <h1 class="page-title">Добавить книгу</h1>
  <a class="btn btn--ghost" href="index.php">← В каталог</a>
</div>

<?php if ($ok): ?><div class="alert alert--ok">Книга успешно добавлена в каталог.</div><?php endif; ?>
<?php foreach ($errors as $e): ?>
  <div class="alert alert--err"><?= e($e) ?></div>
<?php endforeach; ?>

<section class="panel">
  <form method="post" class="grid-2">
    <label class="field grid-2__full">
      <span>Название книги *</span>
      <input type="text" name="title" value="<?= e($_POST['title'] ?? '') ?>" required>
    </label>
    <label class="field">
      <span>Автор *</span>
      <input type="text" name="author" list="authorsList" value="<?= e($_POST['author'] ?? '') ?>" required>
      <datalist id="authorsList">
        <?php foreach ($pdo->query("SELECT name FROM authors ORDER BY name") as $a): ?>
          <option value="<?= e($a['name']) ?>"></option>
        <?php endforeach; ?>
      </datalist>
    </label>
    <label class="field">
      <span>Жанр *</span>
      <input type="text" name="genre" list="genresList" value="<?= e($_POST['genre'] ?? '') ?>" required>
      <datalist id="genresList">
        <?php foreach ($pdo->query("SELECT name FROM genres ORDER BY name") as $g): ?>
          <option value="<?= e($g['name']) ?>"></option>
        <?php endforeach; ?>
      </datalist>
    </label>
    <label class="field">
      <span>ISBN</span>
      <input type="text" name="isbn" value="<?= e($_POST['isbn'] ?? '') ?>" placeholder="978-5-17-098825-6">
    </label>
    <label class="field">
      <span>Количество экземпляров</span>
      <input type="number" name="copies" min="1" value="<?= (int)($_POST['copies'] ?? 1) ?>">
    </label>
    <label class="field grid-2__full">
      <span>Описание</span>
      <textarea name="description" rows="3"><?= e($_POST['description'] ?? '') ?></textarea>
    </label>
    <button class="btn btn--primary btn--block grid-2__full" type="submit">Сохранить книгу</button>
  </form>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>